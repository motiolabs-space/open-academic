<?php

declare(strict_types=1);

use App\DTOs\Bridge\KonsumenBridgeData;
use App\DTOs\Bridge\WebhookData;
use App\Enums\SemesterType;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\System\LogAktivitas;
use App\Services\Bridge\KonsumenBridgeService;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The Campus Bridge consumer screens: credentials shown once, stored only in a
 * form that cannot be read back, never logged, and handed out only by holders
 * of integrasi.kredensial.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    Portal::lupakanTerm();

    $this->tik = Staff::factory()->create();
    $this->tik->assignRole('tik');

    $this->layanan = app(KonsumenBridgeService::class);
});

function dataKonsumen(array $ubah = []): KonsumenBridgeData
{
    return new KonsumenBridgeData(...[
        'nama' => 'Open Campus',
        'slug' => 'open-campus-'.Str::lower(Str::random(5)),
        'deskripsi' => 'Lapisan ekosistem',
        'baseUrl' => 'https://campus.test',
        'scopes' => ['students.read', 'terms.read'],
        ...$ubah,
    ]);
}

function isiLogBridge(): string
{
    return (string) json_encode(LogAktivitas::query()->get()->map->getAttributes());
}

describe('KonsumenBridgeService', function () {
    it('mendaftarkan konsumen dan menolak scope yang tidak dikenal atau slug ganda', function () {
        $k = $this->layanan->daftarkan(dataKonsumen(['slug' => 'open-campus']));

        expect($k->scopes)->toBe(['students.read', 'terms.read'])
            ->and(fn () => $this->layanan->daftarkan(dataKonsumen(['scopes' => ['grades.write']])))
            ->toThrow(AturanAkademikException::class, 'grades.write')
            ->and(fn () => $this->layanan->daftarkan(dataKonsumen(['slug' => 'open-campus'])))
            ->toThrow(AturanAkademikException::class, 'Slug');
    });

    it('memperbarui tanpa mengubah slug', function () {
        $k = $this->layanan->daftarkan(dataKonsumen(['slug' => 'tetap']));

        $this->layanan->perbarui($k, dataKonsumen(['slug' => 'berubah', 'nama' => 'OC', 'scopes' => ['terms.read'], 'aktif' => false]));

        expect($k->refresh()->slug)->toBe('tetap')
            ->and($k->nama)->toBe('OC')
            ->and($k->scopes)->toBe(['terms.read'])
            ->and($k->is_active)->toBeFalse();
    });

    it('menerbitkan token yang hanya tersimpan sebagai hash dan tidak tercatat di log', function () {
        $k = $this->layanan->daftarkan(dataKonsumen());

        $baru = $this->layanan->terbitkanToken($k, 'produksi');
        [, $polos] = explode('|', $baru->plainTextToken, 2);

        $baris = DB::table('personal_access_tokens')->first();

        expect($baris->token)->toBe(hash('sha256', $polos))
            ->and($baris->token)->not->toBe($polos)
            ->and(isiLogBridge())->not->toContain($polos)
            ->and(LogAktivitas::where('event', 'token_issued')->where('subject_id', $k->id)->exists())->toBeTrue();
    });

    it('menolak menerbitkan token untuk konsumen nonaktif', function () {
        $k = $this->layanan->daftarkan(dataKonsumen(['aktif' => false]));

        expect(fn () => $this->layanan->terbitkanToken($k, 'x'))->toThrow(AturanAkademikException::class, 'nonaktif');
    });

    it('mencabut satu token lewat konsumennya, bukan token konsumen lain', function () {
        $a = $this->layanan->daftarkan(dataKonsumen());
        $b = $this->layanan->daftarkan(dataKonsumen());
        $this->layanan->terbitkanToken($a, 'a');
        $milikB = $this->layanan->terbitkanToken($b, 'b')->accessToken;

        expect(fn () => $this->layanan->cabutToken($a, $milikB->id))->toThrow(ModelNotFoundException::class);

        $this->layanan->cabutToken($b, $milikB->id);

        expect(PersonalAccessToken::count())->toBe(1)
            ->and(LogAktivitas::where('event', 'token_revoked')->where('subject_id', $b->id)->exists())->toBeTrue();
    });

    it('mencabut semua token sekaligus', function () {
        $k = $this->layanan->daftarkan(dataKonsumen());
        $this->layanan->terbitkanToken($k, 'satu');
        $this->layanan->terbitkanToken($k, 'dua');

        expect($this->layanan->cabutSemuaToken($k))->toBe(2)
            ->and($k->tokens()->count())->toBe(0);
    });

    it('menolak URL webhook non-HTTPS dan event yang tidak dikenal', function () {
        $k = $this->layanan->daftarkan(dataKonsumen());

        expect(fn () => $this->layanan->aturWebhook($k, new WebhookData('http://campus.test/hook', ['krs.approved'])))
            ->toThrow(AturanAkademikException::class, 'HTTPS')
            ->and(fn () => $this->layanan->aturWebhook($k, new WebhookData('https://campus.test/hook', ['grade.deleted'])))
            ->toThrow(AturanAkademikException::class, 'grade.deleted');

        $this->layanan->aturWebhook($k, new WebhookData('https://campus.test/hook', ['krs.approved']));
        expect($k->refresh()->subscribesTo('krs.approved'))->toBeTrue();
    });

    it('merotasi rahasia webhook: tersimpan terenkripsi, tidak tercatat, yang lama tidak berlaku', function () {
        $k = $this->layanan->daftarkan(dataKonsumen());

        $lama = $this->layanan->rotasiRahasiaWebhook($k);
        $baru = $this->layanan->rotasiRahasiaWebhook($k);

        $mentah = DB::table('bridge_consumers')->where('id', $k->id)->value('webhook_secret');

        expect($baru)->not->toBe($lama)
            ->and($k->refresh()->webhook_secret)->toBe($baru)
            ->and($mentah)->not->toContain($baru)
            ->and(isiLogBridge())->not->toContain($baru)->not->toContain($lama)
            ->and(LogAktivitas::where('event', 'webhook_secret_rotated')->count())->toBe(2);
    });
});

it('mengenkripsi rahasia webhook polos yang sudah ada saat migrasi', function () {
    $id = DB::table('bridge_consumers')->insertGetId([
        'uuid' => (string) Str::uuid(), 'nama' => 'Lama', 'slug' => 'lama', 'scopes' => '[]',
        'webhook_secret' => 'rahasia-polos-lama', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    (require database_path('migrations/2026_10_29_400100_encrypt_bridge_webhook_secret.php'))->up();

    expect(DB::table('bridge_consumers')->where('id', $id)->value('webhook_secret'))->not->toBe('rahasia-polos-lama')
        ->and(BridgeConsumer::find($id)->webhook_secret)->toBe('rahasia-polos-lama');
});

describe('layar', function () {
    it('menampilkan token tepat sekali, pada halaman yang tidak boleh disimpan', function () {
        $k = $this->layanan->daftarkan(dataKonsumen());
        $this->actingAs($this->tik, 'staff');

        $this->post(route('admin.bridge.konsumen.token', $k), ['nama_token' => 'produksi'])->assertRedirect();

        $pertama = $this->get(route('admin.bridge.konsumen.show', $k))->assertOk();

        $token = PersonalAccessToken::firstOrFail();
        expect($pertama->getContent())->toContain($token->id.'|')
            ->and($pertama->headers->get('Cache-Control'))->toContain('no-store');

        $kedua = $this->get(route('admin.bridge.konsumen.show', $k))->assertOk();
        expect($kedua->getContent())->not->toContain($token->id.'|')
            ->and($kedua->getContent())->toContain('produksi');
    });

    it('token yang diterbitkan dari layar dapat dipakai, tercatat pemakaiannya, lalu ditolak setelah dicabut', function () {
        $k = $this->layanan->daftarkan(dataKonsumen());
        $polos = $this->layanan->terbitkanToken($k, 'uji')->plainTextToken;
        $tokenId = (int) explode('|', $polos)[0];

        $this->getJson('/api/bridge/v1/academic-terms', ['Authorization' => 'Bearer '.$polos])->assertOk();
        expect(PersonalAccessToken::find($tokenId)->last_used_at)->not->toBeNull();

        $this->actingAs($this->tik, 'staff')
            ->delete(route('admin.bridge.konsumen.token.cabut', [$k, $tokenId]))
            ->assertSessionHas('sukses');

        auth()->forgetGuards();
        $this->getJson('/api/bridge/v1/academic-terms', ['Authorization' => 'Bearer '.$polos])->assertUnauthorized();
    });

    it('menampilkan rahasia webhook baru sekali', function () {
        $k = $this->layanan->daftarkan(dataKonsumen());
        $this->actingAs($this->tik, 'staff');

        $this->post(route('admin.bridge.konsumen.rahasia', $k))->assertRedirect();
        $rahasia = $k->refresh()->webhook_secret;

        $this->get(route('admin.bridge.konsumen.show', $k))->assertSee($rahasia);
        $this->get(route('admin.bridge.konsumen.show', $k))->assertDontSee($rahasia);
    });

    it('mendaftarkan konsumen dan mengatur webhook lewat formulir', function () {
        $this->actingAs($this->tik, 'staff');

        $this->post(route('admin.bridge.konsumen.store'), [
            'nama' => 'Dasbor Rektorat', 'slug' => 'dasbor-rektorat', 'scopes' => ['terms.read'], 'is_active' => '1',
        ])->assertRedirect();

        $k = BridgeConsumer::where('slug', 'dasbor-rektorat')->firstOrFail();

        $this->put(route('admin.bridge.konsumen.webhook', $k), ['webhook_url' => 'http://dasbor.test/hook'])
            ->assertSessionHasErrors('webhook_url');
        $this->put(route('admin.bridge.konsumen.webhook', $k), ['webhook_url' => 'https://dasbor.test/hook', 'webhook_events' => ['student.graduated']])
            ->assertSessionHas('sukses');

        expect($k->refresh()->webhook_events)->toBe(['student.graduated']);

        $this->get(route('admin.bridge'))->assertOk()->assertSee('Dasbor Rektorat')->assertSee(__('bridge.daftar.judul'));
    });

    it('membatasi kredensial pada pemegang integrasi.kredensial', function () {
        $k = $this->layanan->daftarkan(dataKonsumen());
        $polos = $this->layanan->terbitkanToken($k, 'uji')->plainTextToken;
        $tokenId = (int) explode('|', $polos)[0];

        // Membaca konsol saja: melihat konsumen, tidak memegang kuncinya.
        $pengamat = Staff::factory()->create();
        $pengamat->givePermissionTo('bridge.view');

        $this->actingAs($pengamat, 'staff')->get(route('admin.bridge.konsumen.show', $k))->assertOk()
            ->assertDontSee(__('bridge.token.terbitkan'));
        $this->actingAs($pengamat, 'staff')->post(route('admin.bridge.konsumen.token', $k))->assertForbidden();
        $this->actingAs($pengamat, 'staff')->post(route('admin.bridge.konsumen.rahasia', $k))->assertForbidden();
        $this->actingAs($pengamat, 'staff')->delete(route('admin.bridge.konsumen.token.cabut', [$k, $tokenId]))->assertForbidden();
        $this->actingAs($pengamat, 'staff')->post(route('admin.bridge.konsumen.store'), ['nama' => 'X', 'slug' => 'x'])->assertForbidden();

        // BAAK tidak melihat konsol sama sekali.
        $baak = Staff::factory()->create();
        $baak->assignRole('baak');
        $this->actingAs($baak, 'staff')->get(route('admin.bridge.konsumen.show', $k))->assertForbidden();

        expect(PersonalAccessToken::count())->toBe(1);
    });

    it('membuka halaman konsumen dengan jumlah kueri tetap berapa pun banyaknya token', function () {
        $k = $this->layanan->daftarkan(dataKonsumen());
        $this->layanan->terbitkanToken($k, 'satu');

        $hitung = function () use ($k): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->tik, 'staff')->get(route('admin.bridge.konsumen.show', $k))->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $awal = $hitung();
        foreach (range(1, 10) as $i) {
            $this->layanan->terbitkanToken($k, 'token-'.$i);
        }

        expect($hitung())->toBeLessThanOrEqual($awal)->toBeLessThanOrEqual(16);
    });
});

it('tidak membuka rute kredensial bagi guard selain staf', function () {
    $k = app(KonsumenBridgeService::class)->daftarkan(dataKonsumen());

    $this->actingAs(Dosen::factory()->create(), 'dosen')
        ->post(route('admin.bridge.konsumen.token', $k))
        ->assertRedirect();

    expect(PersonalAccessToken::count())->toBe(0);
});
