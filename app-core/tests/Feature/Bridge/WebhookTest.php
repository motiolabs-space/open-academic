<?php

declare(strict_types=1);

use App\DTOs\Akademik\KeputusanWaliData;
use App\Enums\SemesterType;
use App\Enums\WebhookDeliveryStatus;
use App\Jobs\PublishBridgeEventJob;
use App\Models\Akademik\Krs;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Bridge\BridgeWebhookDelivery;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Services\Akademik\KrsService;
use App\Services\Bridge\BridgeEventPublisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['bridge.enabled' => true]);
    $this->publisher = app(BridgeEventPublisher::class);

    // Suite berjalan dengan QUEUE_CONNECTION=sync, sehingga publish() akan
    // langsung menjalankan job dan menembak HTTP sungguhan. Tes ini menguji
    // penjadwalannya, jadi antreannya dipalsukan; tes yang memang ingin
    // menjalankan job memanggil handle() sendiri.
    Queue::fake();
});

function pelanggan(array $events = ['krs.approved'], string $url = 'https://open-campus.test/webhook'): BridgeConsumer
{
    return BridgeConsumer::create([
        'nama' => 'Open Campus',
        'slug' => 'open-campus-'.uniqid(),
        'scopes' => ['students.read'],
        'webhook_url' => $url,
        'webhook_secret' => 'rahasia-uji',
        'webhook_events' => $events,
        'is_active' => true,
    ]);
}

describe('penerbitan', function () {
    it('mengantre satu pengiriman per aplikasi yang berlangganan', function () {
        pelanggan();
        pelanggan();
        pelanggan(['grade.finalized']); // berlangganan event lain

        expect($this->publisher->publish('krs.approved', ['nim' => '123']))->toBe(2);

        Queue::assertPushed(PublishBridgeEventJob::class, 2);
    });

    it('tidak mengirim apa pun ke aplikasi tanpa alamat webhook', function () {
        $consumer = pelanggan();
        $consumer->update(['webhook_url' => null]);

        expect($this->publisher->publish('krs.approved', []))->toBe(0);
    });

    it('menolak nama event yang tidak terdaftar', function () {
        expect(fn () => $this->publisher->publish('event.karangan', []))
            ->toThrow(InvalidArgumentException::class, 'tidak terdaftar');
    });

    it('membungkus payload dengan amplop yang dapat dilacak konsumen', function () {
        $amplop = $this->publisher->amplop('krs.approved', ['nim' => '123']);

        // id memungkinkan konsumen mendeteksi kiriman ganda; occurred_at
        // memungkinkan mengurutkan event yang tiba terbalik setelah retry.
        expect($amplop)->toHaveKeys(['id', 'event', 'occurred_at', 'institution', 'data'])
            ->and($amplop['data'])->toBe(['nim' => '123']);
    });
});

describe('tanda tangan', function () {
    it('menandatangani badan bersama timestamp', function () {
        $body = '{"a":1}';
        $ts = '1750000000';

        $tanda = $this->publisher->tandaTangan($body, $ts, 'rahasia');

        expect($tanda)->toBe(hash_hmac('sha256', $ts.'.'.$body, 'rahasia'));
    });

    it('menghasilkan tanda tangan berbeda untuk timestamp berbeda', function () {
        $body = '{"a":1}';

        // Kiriman yang tertangkap tidak dapat diputar ulang dengan timestamp baru.
        expect($this->publisher->tandaTangan($body, '1750000000', 'rahasia'))
            ->not->toBe($this->publisher->tandaTangan($body, '1750000001', 'rahasia'));
    });

    it('mengirim tanda tangan dan timestamp pada header', function () {
        Http::fake(['*' => Http::response('ok', 200)]);

        $consumer = pelanggan();
        $this->publisher->publish('krs.approved', ['nim' => '123']);

        $delivery = BridgeWebhookDelivery::firstOrFail();
        app(PublishBridgeEventJob::class, ['deliveryId' => $delivery->id])->handle($this->publisher);

        Http::assertSent(function ($request) use ($consumer): bool {
            $tanda = $request->header(config('bridge.webhooks.signature_header'))[0] ?? '';
            $ts = $request->header(config('bridge.webhooks.timestamp_header'))[0] ?? '';

            return $tanda === hash_hmac('sha256', $ts.'.'.$request->body(), $consumer->webhook_secret);
        });

        expect($delivery->fresh()->status)->toBe(WebhookDeliveryStatus::Delivered);
    });
});

describe('percobaan ulang', function () {
    it('menjadwalkan ulang dengan jeda ketika konsumen membalas galat', function () {
        Http::fake(['*' => Http::response('kacau', 500)]);
        pelanggan();
        $this->publisher->publish('krs.approved', []);

        $delivery = BridgeWebhookDelivery::firstOrFail();
        app(PublishBridgeEventJob::class, ['deliveryId' => $delivery->id])->handle($this->publisher);

        $delivery->refresh();

        expect($delivery->status)->toBe(WebhookDeliveryStatus::Failed)
            ->and($delivery->attempts)->toBe(1)
            ->and($delivery->response_code)->toBe(500)
            ->and($delivery->next_attempt_at)->not->toBeNull();
    });

    it('memakai jeda bertingkat, bukan mencoba ulang seketika', function () {
        pelanggan();
        $this->publisher->publish('krs.approved', []);

        $delivery = BridgeWebhookDelivery::firstOrFail();
        $backoff = config('bridge.webhooks.backoff');

        // Konsumen yang sedang mati tetap mati beberapa menit; menghajarnya
        // berulang kali tidak menolong siapa pun.
        expect($delivery->backoffSeconds())->toBe($backoff[0]);

        $delivery->update(['attempts' => 2]);
        expect($delivery->fresh()->backoffSeconds())->toBe($backoff[2]);
    });

    it('menyerah setelah percobaan maksimum dan menyimpan jejaknya', function () {
        Http::fake(['*' => Http::response('kacau', 500)]);

        pelanggan();
        $this->publisher->publish('krs.approved', []);

        $delivery = BridgeWebhookDelivery::firstOrFail();
        $delivery->update(['attempts' => (int) config('bridge.webhooks.max_attempts')]);

        app(PublishBridgeEventJob::class, ['deliveryId' => $delivery->id])->handle($this->publisher);

        expect($delivery->fresh()->status)->toBe(WebhookDeliveryStatus::Exhausted);
    });

    it('tidak mengirim ulang kiriman yang sudah berhasil', function () {
        Http::fake(['*' => Http::response('ok', 200)]);

        pelanggan();
        $this->publisher->publish('krs.approved', []);

        $delivery = BridgeWebhookDelivery::firstOrFail();
        $delivery->update(['status' => WebhookDeliveryStatus::Delivered]);

        app(PublishBridgeEventJob::class, ['deliveryId' => $delivery->id])->handle($this->publisher);

        Http::assertNothingSent();
    });
});

describe('integrasi alur akademik', function () {
    it('menerbitkan krs.approved saat dosen wali menyetujui', function () {
        pelanggan(['krs.approved']);

        $term = TahunAkademik::factory()
            ->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();

        $prodi = Prodi::factory()->create();
        $wali = Dosen::factory()->create(['prodi_id' => $prodi->id]);

        $mahasiswa = Mahasiswa::factory()->create([
            'prodi_id' => $prodi->id,
            'dosen_wali_id' => $wali->id,
        ]);

        $krs = Krs::factory()->diajukan()->create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $term->id,
        ]);

        app(KrsService::class)->putuskan(
            $krs,
            $wali,
            KeputusanWaliData::setujui(),
        );

        Queue::assertPushed(PublishBridgeEventJob::class);

        expect(BridgeWebhookDelivery::where('event', 'krs.approved')->exists())->toBeTrue();
    });
});
