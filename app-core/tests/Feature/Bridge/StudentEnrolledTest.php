<?php

declare(strict_types=1);

use App\DTOs\Kemahasiswaan\AsalPendidikanData;
use App\DTOs\Kemahasiswaan\IdentitasMahasiswaData;
use App\Enums\ApplicantStatus;
use App\Enums\JenisDaftar;
use App\Enums\SemesterType;
use App\Jobs\PublishBridgeEventJob;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Bridge\BridgeWebhookDelivery;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbPendaftar;
use App\Services\Bridge\PenerbitPendaftaran;
use App\Services\Kemahasiswaan\PindahanService;
use App\Services\Pmb\PmbService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * student.enrolled was listed in config/bridge.php and bridge.yaml but never
 * published, so Open Campus learned about a new student only by polling.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    config(['bridge.enabled' => true]);
    Queue::fake();

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->krsDibuka()->create(['tahun_mulai' => 2026]);
    $this->prodi = Prodi::factory()->create(['kode' => '55201', 'kode_pddikti' => fake()->uuid()]);

    $this->consumer = BridgeConsumer::create([
        'nama' => 'Open Campus',
        'slug' => 'open-campus',
        'base_url' => 'https://campus.test',
        'scopes' => ['students.read'],
        'webhook_url' => 'https://campus.test/webhooks/open-academic',
        'webhook_secret' => Str::random(48),
        'webhook_events' => ['student.enrolled'],
        'is_active' => true,
    ]);

    $this->gelombang = PmbGelombang::create([
        'tahun_akademik_id' => $this->term->id,
        'kode' => 'PMB-BR-1',
        'nama' => 'Gelombang Uji Bridge',
        'jalur' => 'reguler',
        'tanggal_mulai' => now()->subMonth(),
        'tanggal_selesai' => now()->addMonth(),
        'biaya_pendaftaran' => 0,
        'is_active' => true,
    ]);
});

function pendaftarLulusBridge(): PmbPendaftar
{
    $pendaftar = PmbPendaftar::create([
        'pmb_gelombang_id' => test()->gelombang->id,
        'nomor_pendaftaran' => 'REG-'.fake()->unique()->numerify('######'),
        'nama' => 'Calon Bridge',
        'email' => fake()->unique()->safeEmail(),
        'nik' => '9900000000000777',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2008-05-14',
        'jenis_kelamin' => 'L',
        'prodi_pilihan_1_id' => test()->prodi->id,
        'status' => ApplicantStatus::Seleksi,
    ]);

    app(PmbService::class)->luluskan($pendaftar, test()->prodi);

    return $pendaftar->fresh();
}

/** @return array<int, array<string, mixed>> */
function kirimanEnrolled(): array
{
    return BridgeWebhookDelivery::query()->where('event', 'student.enrolled')->orderBy('id')->get()
        ->map(fn (BridgeWebhookDelivery $d): array => $d->payload)->all();
}

it('menerbitkan student.enrolled saat pendaftar PMB didaftarkan ulang', function () {
    $mahasiswa = app(PmbService::class)->daftarUlang(pendaftarLulusBridge(), $this->term)['mahasiswa'];

    $kiriman = kirimanEnrolled();
    expect($kiriman)->toHaveCount(1);

    expect($kiriman[0]['event'])->toBe('student.enrolled')
        ->and($kiriman[0]['data'])->toBe([
            'mahasiswa_uuid' => $mahasiswa->uuid,
            'nim' => $mahasiswa->nim,
            'nama' => 'Calon Bridge',
            'email' => $mahasiswa->email,
            'prodi' => '55201',
            'angkatan' => 2026,
            'semester_masuk' => $this->term->kode,
            'jenis_daftar' => $mahasiswa->fresh()->jenis_daftar?->value,
            'status' => 'A',
            // Only a re-NIM or readmission names an earlier registration (ADR-042 §3).
            'mahasiswa_sebelumnya_uuid' => null,
        ]);

    // Queued and signed like every other event.
    Queue::assertPushed(PublishBridgeEventJob::class);
    expect($mahasiswa->fresh()->bridge_enrolled_at)->not->toBeNull();
});

it('menerbitkan student.enrolled untuk intake pindahan langsung', function () {
    $mahasiswa = app(PindahanService::class)->daftarkanLangsung(
        IdentitasMahasiswaData::fromArray([
            'nama' => 'Pindahan Bridge',
            'email' => 'pindahan.bridge@contoh.test',
            'nik' => '9900000000000778',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '2004-07-15',
            'jenis_kelamin' => 'P',
        ]),
        $this->prodi,
        $this->term,
        AsalPendidikanData::fromArray([
            'jenis_daftar' => JenisDaftar::Pindahan->value,
            'pt_asal' => 'Universitas Merdeka Nusantara',
            'prodi_asal' => 'Teknik Informatika',
            'term_masuk_asal' => '20241',
        ]),
    )['mahasiswa'];

    $kiriman = kirimanEnrolled();
    expect($kiriman)->toHaveCount(1)
        ->and($kiriman[0]['data']['mahasiswa_uuid'])->toBe($mahasiswa->uuid)
        ->and($kiriman[0]['data']['jenis_daftar'])->toBe('pindahan');
});

it('menerbitkan muatan akhir untuk pindahan lewat PMB, bukan keadaan di tengah transaksi', function () {
    // daftarkanDariPmb() runs daftar ulang inside its own transaction, then
    // corrects jenis_daftar. The event must say "pindahan".
    $mahasiswa = app(PindahanService::class)->daftarkanDariPmb(
        pendaftarLulusBridge(),
        $this->term,
        AsalPendidikanData::fromArray([
            'jenis_daftar' => JenisDaftar::Pindahan->value,
            'pt_asal' => 'Universitas Merdeka Nusantara',
            'prodi_asal' => 'Teknik Informatika',
            'term_masuk_asal' => '20241',
        ]),
    )['mahasiswa'];

    $kiriman = kirimanEnrolled();
    expect($kiriman)->toHaveCount(1)
        ->and($kiriman[0]['data']['mahasiswa_uuid'])->toBe($mahasiswa->uuid)
        ->and($kiriman[0]['data']['jenis_daftar'])->toBe('pindahan');
});

it('tidak menerbitkan apa pun bila pendaftaran dibatalkan transaksinya', function () {
    $pendaftar = pendaftarLulusBridge();

    try {
        DB::transaction(function () use ($pendaftar): void {
            app(PmbService::class)->daftarUlang($pendaftar, $this->term);

            throw new RuntimeException('gagal sesudah daftar ulang');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(kirimanEnrolled())->toBe([])
        ->and(Mahasiswa::count())->toBe(0);
});

it('menerbitkan satu kali saja untuk satu mahasiswa', function () {
    $mahasiswa = app(PmbService::class)->daftarUlang(pendaftarLulusBridge(), $this->term)['mahasiswa'];
    $penerbit = app(PenerbitPendaftaran::class);

    $penerbit->umumkan($mahasiswa);
    expect($penerbit->terbitkan($mahasiswa->id))->toBeFalse();

    expect(kirimanEnrolled())->toHaveCount(1);
});

it('tidak menerbitkan untuk mahasiswa yang sudah ada sebelum jalur ini', function () {
    // Adoption and past-student claims link existing students to PDDIKTI;
    // they enrol nobody. Nothing but the two enrolment paths calls this.
    Mahasiswa::factory()->count(3)->create(['prodi_id' => $this->prodi->id]);

    expect(kirimanEnrolled())->toBe([])
        ->and(Mahasiswa::whereNotNull('bridge_enrolled_at')->count())->toBe(0);
});

it('tetap menandai mahasiswa saat Bridge dimatikan, tanpa kiriman', function () {
    config(['bridge.enabled' => false]);

    $mahasiswa = app(PmbService::class)->daftarUlang(pendaftarLulusBridge(), $this->term)['mahasiswa'];

    expect(BridgeWebhookDelivery::count())->toBe(0)
        ->and($mahasiswa->fresh()->bridge_enrolled_at)->not->toBeNull();
});

it('menerbitkan tepat properti yang didokumentasikan di bridge.yaml', function () {
    $spec = Yaml::parseFile(base_path('../docs/openapi/bridge.yaml'));
    $skema = $spec['components']['x-webhooks']['payloads']['student.enrolled'];

    $mahasiswa = app(PmbService::class)->daftarUlang(pendaftarLulusBridge(), $this->term)['mahasiswa'];
    $data = kirimanEnrolled()[0]['data'];

    expect(array_keys($data))->toEqualCanonicalizing(array_keys($skema['properties']))
        ->and(array_diff($skema['required'], array_keys(array_filter($data, fn ($v): bool => $v !== null))))->toBe([])
        ->and($mahasiswa->uuid)->toBe($data['mahasiswa_uuid']);
});
