<?php

declare(strict_types=1);

use App\DTOs\Kemahasiswaan\AsalPendidikanData;
use App\Enums\Gender;
use App\Enums\JenisDaftar;
use App\Enums\JenisPerubahanFeeder;
use App\Enums\JenisRegistrasiUlang;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Bridge\BridgeWebhookDelivery;
use App\Models\Feeder\FeederChangeRequest;
use App\Models\Feeder\FeederMapping;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\RiwayatRegistrasiMahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederSyncService;
use App\Services\Feeder\FeederValidator;
use App\Services\Feeder\Mappers\LulusDoMapper;
use App\Services\Feeder\Mappers\MahasiswaMapper;
use App\Services\Feeder\Mappers\RiwayatPendidikanMapper;
use App\Services\Kemahasiswaan\PindahanService;
use Database\Seeders\Demo\IntegrasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

/*
 * Re-NIM, readmission and reactivation (ROADMAP #55, ADR-042 §3): what is
 * recorded, what each registration sends to Feeder, what Campus Bridge
 * announces, and that a re-run sends nothing twice.
 */

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(IntegrasiSeeder::class);
    config(['feeder.enabled' => true, 'feeder.driver' => 'fake', 'bridge.enabled' => true]);
    Queue::fake();

    $this->feeder = new FakeFeederClient;
    $this->app->instance(FeederClientInterface::class, $this->feeder);

    TahunAkademik::factory()->term(2024, SemesterType::Ganjil)->create();
    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create(['tahun_mulai' => 2026]);

    $this->if = Prodi::factory()->create(['kode' => 'IF', 'nama' => 'Informatika', 'kode_pddikti' => 'sms-if']);
    $this->si = Prodi::factory()->create(['kode' => 'SI', 'nama' => 'Sistem Informasi', 'kode_pddikti' => 'sms-si']);

    foreach ([JenisDaftar::PindahProdi->value => '21', JenisDaftar::MasukKembali->value => '22'] as $lokal => $kode) {
        FeederMapping::create(['group' => 'jenis_daftar', 'local_value' => $lokal, 'feeder_code' => $kode, 'feeder_label' => $lokal]);
    }

    $this->baak = Staff::factory()->create();
    $this->baak->assignRole('baak');

    $this->lama = Mahasiswa::factory()->create([
        'nim' => '2024IF0001',
        'nama' => 'Bayu Saputra',
        'email' => 'bayu@kampus.test',
        'prodi_id' => $this->if->id,
        'nik' => '9902020202020002',
        'tempat_lahir' => 'Surabaya',
        'tanggal_lahir' => '2005-02-02',
        'jenis_kelamin' => Gender::LakiLaki,
        'agama_kode' => '1',
        'telepon' => '081299990000',
        'nama_ibu' => 'Siti Aminah',
        'term_masuk' => '20241',
        'angkatan' => 2024,
        'status' => StudentStatus::Aktif,
        'feeder_id' => 'orang-1',
        'feeder_registrasi_id' => 'reg-lama',
    ]);
    StatusMahasiswa::create(['mahasiswa_id' => $this->lama->id, 'tahun_akademik_id' => $this->term->id, 'status' => 'A', 'semester_ke' => 5]);
});

/** @param array<string, mixed> $isian */
function catatRegistrasiUlang(Mahasiswa $m, array $isian = []): TestResponse
{
    return test()->actingAs(test()->baak, 'staff')->post(route('admin.mahasiswa.registrasi-ulang.store', $m), array_merge([
        'jenis' => 'pindah_prodi',
        'term' => test()->term->uuid,
        'tanggal' => '2026-08-20',
        'prodi' => test()->si->uuid,
        'nim' => '2026SI0042',
        'email' => 'bayu.si@kampus.test',
        'nomor_sk' => 'SK/REK/77/2026',
        'tanggal_sk' => '2026-08-15',
        'alasan' => 'Permohonan pindah prodi disetujui rapat fakultas.',
    ], $isian));
}

/** @return array<int, array<string, mixed>> envelopes published for an event */
function kirimanBridge(string $event): array
{
    return BridgeWebhookDelivery::query()->where('event', $event)->orderBy('id')->get()->map(fn ($d) => $d->payload)->all();
}

describe('pindah prodi internal', function () {
    it('membuka registrasi baru dan menutup yang lama tanpa menimpa NIM', function () {
        catatRegistrasiUlang($this->lama)->assertRedirect()->assertSessionHas('kata_sandi_baru');

        $baru = Mahasiswa::query()->where('nim', '2026SI0042')->sole();
        $lama = $this->lama->fresh();
        $riwayat = RiwayatRegistrasiMahasiswa::query()->sole();

        expect($lama->nim)->toBe('2024IF0001')
            ->and($lama->status)->toBe(StudentStatus::GantiProdi)
            ->and($lama->feeder_registrasi_id)->toBe('reg-lama')
            ->and($baru->only(['nama', 'nik', 'tempat_lahir', 'telepon', 'nama_ibu', 'feeder_id']))->toBe([
                'nama' => 'Bayu Saputra', 'nik' => '9902020202020002', 'tempat_lahir' => 'Surabaya',
                'telepon' => '081299990000', 'nama_ibu' => 'Siti Aminah', 'feeder_id' => 'orang-1',
            ])
            ->and($baru->prodi_id)->toBe($this->si->id)
            ->and($baru->status)->toBe(StudentStatus::Aktif)
            ->and($baru->jenis_daftar)->toBe(JenisDaftar::PindahProdi)
            ->and($baru->term_masuk)->toBe('20261')
            ->and($baru->term_masuk_asal)->toBe('20241')
            ->and($baru->prodi_asal)->toContain('Informatika')
            ->and($baru->pt_asal)->toBe(config('branding.institution.name'))
            ->and($baru->feeder_registrasi_id)->toBeNull()
            ->and($baru->hasRole('mahasiswa'))->toBeTrue()
            ->and($riwayat->only(['nim_lama', 'nim_baru', 'mahasiswa_lama_id', 'mahasiswa_baru_id', 'dicatat_by_staff_id']))->toBe([
                'nim_lama' => '2024IF0001', 'nim_baru' => '2026SI0042',
                'mahasiswa_lama_id' => $lama->id, 'mahasiswa_baru_id' => $baru->id, 'dicatat_by_staff_id' => $this->baak->id,
            ])
            ->and($riwayat->status_sebelum)->toBe(StudentStatus::Aktif)
            ->and($riwayat->status_sesudah)->toBe(StudentStatus::GantiProdi)
            ->and($riwayat->jenis_daftar)->toBe(JenisDaftar::PindahProdi);
    });

    it('membangkitkan NIM dari pola prodi tujuan bila tidak diisi', function () {
        catatRegistrasiUlang($this->lama, ['nim' => null])->assertRedirect();

        expect(RiwayatRegistrasiMahasiswa::query()->sole()->nim_baru)->not->toBeEmpty()->not->toBe('2024IF0001');
    });

    it('mengirim riwayat pendidikan baru pada orang yang sama, dan menutup registrasi lama sebagai Lulus/DO', function () {
        catatRegistrasiUlang($this->lama);
        $baru = Mahasiswa::query()->where('nim', '2026SI0042')->sole();

        expect(app(RiwayatPendidikanMapper::class)->payload($baru->load('prodi')))->toBe([
            'id_mahasiswa' => 'orang-1',
            'nim' => '2026SI0042',
            'id_prodi' => 'sms-si',
            'id_jenis_daftar' => '21',
            'id_jalur_masuk' => '1',
            'id_periode_masuk' => '20261',
            'tanggal_daftar' => $baru->created_at->format('Y-m-d'),
            'sks_diakui' => 0,
            'nama_perguruan_tinggi_asal' => config('branding.institution.name'),
            'nama_program_studi_asal' => $this->lama->prodi->namaLengkap(),
        ]);

        $tutup = app(LulusDoMapper::class)->rows('20261');

        expect($tutup->pluck('nim')->all())->toBe(['2024IF0001'])
            ->and(app(LulusDoMapper::class)->payload($tutup->first()))->toBe([
                'id_registrasi_mahasiswa' => 'reg-lama',
                'id_jenis_keluar' => '2',
                'tanggal_keluar' => '2026-08-20',
                'id_periode_keluar' => '20261',
                'keterangan' => 'Pindah program studi',
            ])
            ->and(app(FeederValidator::class)->isuUntuk('lulus_do', $tutup->first())->where('severity', 'error'))->toBeEmpty();
    });

    it('tidak mendaftarkan biodata kedua, dan aman dijalankan ulang', function () {
        catatRegistrasiUlang($this->lama);
        $sync = app(FeederSyncService::class);

        foreach ([1, 2] as $_) {
            foreach (['mahasiswa', 'riwayat_pendidikan', 'lulus_do'] as $entity) {
                $sync->sinkronkan($entity, $this->term);
            }
        }

        $panggilan = collect($this->feeder->riwayat)->pluck('act')->all();
        $baru = Mahasiswa::query()->where('nim', '2026SI0042')->sole();

        expect($panggilan)->toBe(['UpdateBiodataMahasiswa', 'InsertRiwayatPendidikanMahasiswa', 'InsertMahasiswaLulusDO'])
            ->and(collect($this->feeder->riwayat)->first()['payload']['key'])->toBe(['id_mahasiswa' => 'orang-1'])
            ->and($baru->feeder_registrasi_id)->not->toBeNull()->not->toBe('reg-lama')
            ->and($this->lama->fresh()->feeder_keluar_id)->toBe('reg-lama')
            ->and(FeederSyncLog::query()->where('status', 'skipped')->count())->toBe(3);
    });

    it('mengumumkan registrasi baru sebagai student.enrolled bertaut, dan status lama sebagai student.status_changed', function () {
        catatRegistrasiUlang($this->lama);
        $baru = Mahasiswa::query()->where('nim', '2026SI0042')->sole();

        $enrolled = kirimanBridge('student.enrolled');
        $status = kirimanBridge('student.status_changed');

        expect($enrolled)->toHaveCount(1)
            ->and($enrolled[0]['data']['mahasiswa_uuid'])->toBe($baru->uuid)
            ->and($enrolled[0]['data']['jenis_daftar'])->toBe('pindah_prodi')
            ->and($enrolled[0]['data']['mahasiswa_sebelumnya_uuid'])->toBe($this->lama->uuid)
            ->and($status)->toHaveCount(1)
            ->and($status[0]['data'])->toBeJsonColumn([
                'mahasiswa_uuid' => $this->lama->uuid, 'nim' => '2024IF0001', 'status_lama' => 'A', 'status_baru' => 'G',
            ]);
    });

    it('menolak prodi yang sama, registrasi yang sudah dialihkan, dan surel yang sudah dipakai', function () {
        catatRegistrasiUlang($this->lama, ['prodi' => $this->if->uuid])->assertSessionHas('galat');
        catatRegistrasiUlang($this->lama, ['email' => 'bayu@kampus.test'])->assertSessionHasErrors('email');

        catatRegistrasiUlang($this->lama)->assertSessionHasNoErrors()->assertSessionMissing('galat');

        // The old row is now Ganti Prodi, and a closed registration is not
        // transferred twice.
        catatRegistrasiUlang($this->lama->fresh(), ['nim' => '2026SI0043', 'email' => 'lagi@kampus.test'])->assertSessionHas('galat');

        expect(RiwayatRegistrasiMahasiswa::query()->count())->toBe(1)
            ->and(Mahasiswa::query()->count())->toBe(2);
    });

    it('menolak status Ganti Prodi yang tidak berasal dari peristiwa tercatat', function () {
        $this->lama->update(['status' => StudentStatus::GantiProdi]);

        expect(app(FeederValidator::class)->isuUntuk('lulus_do', $this->lama->fresh())->pluck('rule'))->toContain('pindah_prodi_tidak_tercatat');
    });

    it('meminta pemetaan jenis daftar sebelum riwayat pendidikan baru dikirim', function () {
        FeederMapping::query()->where('local_value', JenisDaftar::PindahProdi->value)->delete();
        catatRegistrasiUlang($this->lama);
        $baru = Mahasiswa::query()->where('nim', '2026SI0042')->sole();

        expect(app(FeederValidator::class)->isuUntuk('riwayat_pendidikan', $baru)->firstWhere('rule', 'jenis_daftar_belum_dipetakan')['severity'])->toBe('error')
            ->and(app(FeederValidator::class)->isuUntuk('riwayat_pendidikan', $baru)->firstWhere('rule', 'sks_diakui_kosong')['severity'])->toBe('warning');
    });

    it('tidak menganggap nomor HP yang sama pada dua registrasi satu orang sebagai nomor ganda', function () {
        catatRegistrasiUlang($this->lama);
        $baru = Mahasiswa::query()->where('nim', '2026SI0042')->sole();

        expect(app(FeederValidator::class)->isuUntuk('mahasiswa', $baru)->pluck('rule'))->not->toContain('telepon_ganda');

        Mahasiswa::factory()->create(['prodi_id' => $this->if->id, 'telepon' => '081299990000', 'nik' => '9903030303030003']);

        expect(app(FeederValidator::class)->isuUntuk('mahasiswa', $baru)->pluck('rule'))->toContain('telepon_ganda');
    });

    it('menautkan kedua registrasi di halaman detail', function () {
        catatRegistrasiUlang($this->lama);
        $baru = Mahasiswa::query()->where('nim', '2026SI0042')->sole();

        $this->actingAs($this->baak, 'staff')->get(route('admin.mahasiswa.show', $baru))
            ->assertOk()->assertSee('2024IF0001')->assertSee(__('registrasi_ulang.riwayat.dari'));
        $this->actingAs($this->baak, 'staff')->get(route('admin.mahasiswa.show', $this->lama))
            ->assertOk()->assertSee('2026SI0042')->assertSee(__('registrasi_ulang.riwayat.ke'));
        $this->actingAs($this->baak, 'staff')->get(route('admin.mahasiswa.registrasi-ulang', $this->lama))
            ->assertOk()->assertSee(__('registrasi_ulang.jenis.pindah_prodi'));
    });
});

describe('masuk kembali', function () {
    it('membuka registrasi baru untuk mahasiswa yang mengundurkan diri, tanpa mengubah registrasi lamanya', function () {
        $this->lama->updateQuietly(['status' => StudentStatus::Keluar, 'feeder_keluar_id' => 'reg-lama']);

        catatRegistrasiUlang($this->lama, ['jenis' => 'masuk_kembali', 'prodi' => $this->if->uuid])->assertSessionHasNoErrors()->assertSessionMissing('galat');

        $baru = Mahasiswa::query()->where('nim', '2026SI0042')->sole();

        expect($this->lama->fresh()->status)->toBe(StudentStatus::Keluar)
            ->and($baru->jenis_daftar)->toBe(JenisDaftar::MasukKembali)
            ->and($baru->prodi_id)->toBe($this->if->id)
            ->and(app(RiwayatPendidikanMapper::class)->payload($baru->load('prodi'))['id_jenis_daftar'])->toBe('22')
            ->and(app(LulusDoMapper::class)->rows('20261')->pluck('nim'))->not->toContain('2024IF0001')
            ->and(FeederChangeRequest::query()->count())->toBe(0)
            ->and(kirimanBridge('student.status_changed'))->toBe([])
            ->and(kirimanBridge('student.enrolled')[0]['data']['mahasiswa_sebelumnya_uuid'])->toBe($this->lama->uuid);
    });

    it('menolak mahasiswa DO kecuali kampus mengizinkannya', function () {
        $this->lama->update(['status' => StudentStatus::DropOut]);

        catatRegistrasiUlang($this->lama, ['jenis' => 'masuk_kembali'])->assertSessionHas('galat');
        expect(RiwayatRegistrasiMahasiswa::query()->count())->toBe(0);

        config(['academic.registrasi_ulang.izinkan_dari_do' => true]);

        catatRegistrasiUlang($this->lama, ['jenis' => 'masuk_kembali'])->assertSessionMissing('galat');
        expect(RiwayatRegistrasiMahasiswa::query()->sole()->jenis)->toBe(JenisRegistrasiUlang::MasukKembali);
    });

    it('menolak lulusan: gelar berikutnya adalah pendaftaran baru lewat PMB', function () {
        $this->lama->update(['status' => StudentStatus::Lulus]);

        catatRegistrasiUlang($this->lama, ['jenis' => 'masuk_kembali'])->assertSessionHas('galat');
        catatRegistrasiUlang($this->lama, ['jenis' => 'pindah_prodi'])->assertSessionHas('galat');
    });
});

describe('aktif kembali', function () {
    it('membuka ulang registrasi yang sama dan mengajukan PJK bila keluarnya sudah dilaporkan', function () {
        $this->lama->updateQuietly(['status' => StudentStatus::Keluar, 'feeder_keluar_id' => 'reg-lama']);

        catatRegistrasiUlang($this->lama, ['jenis' => 'aktif_kembali', 'prodi' => null, 'nim' => null, 'email' => null])
            ->assertRedirect(route('admin.mahasiswa.show', $this->lama))
            ->assertSessionMissing('kata_sandi_baru');

        $pjk = FeederChangeRequest::query()->sole();
        $riwayat = RiwayatRegistrasiMahasiswa::query()->sole();

        expect($this->lama->fresh()->status)->toBe(StudentStatus::Aktif)
            ->and($this->lama->fresh()->nim)->toBe('2024IF0001')
            ->and(Mahasiswa::query()->count())->toBe(1)
            ->and($riwayat->mahasiswa_baru_id)->toBeNull()
            ->and($riwayat->nim_baru)->toBeNull()
            ->and($pjk->jenis)->toBe(JenisPerubahanFeeder::Pjk)
            ->and($pjk->kolom)->toBe('id_jenis_keluar')
            ->and(kirimanBridge('student.enrolled'))->toBe([])
            ->and(kirimanBridge('student.status_changed')[0]['data'])->toMatchArray(['status_lama' => 'K', 'status_baru' => 'A']);
    });

    it('tidak membuat PJK bila keluarnya belum pernah dilaporkan', function () {
        $this->lama->update(['status' => StudentStatus::Keluar]);

        catatRegistrasiUlang($this->lama, ['jenis' => 'aktif_kembali'])->assertSessionMissing('galat');

        expect(FeederChangeRequest::query()->count())->toBe(0)
            ->and(Mahasiswa::query()->count())->toBe(1)
            ->and(RiwayatRegistrasiMahasiswa::query()->sole()->prodi_baru_id)->toBeNull();
    });

    it('menolak mengaktifkan kembali mahasiswa yang masih aktif', function () {
        catatRegistrasiUlang($this->lama, ['jenis' => 'aktif_kembali'])->assertSessionHas('galat');
    });
});

describe('batas akses dan masukan', function () {
    it('hanya staf dengan izin kelola mahasiswa yang boleh mencatat', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')->get(route('admin.mahasiswa.registrasi-ulang', $this->lama))->assertForbidden();
        $this->actingAs($keuangan, 'staff')->post(route('admin.mahasiswa.registrasi-ulang.store', $this->lama), [])->assertForbidden();
    });

    it('mewajibkan alasan, prodi, dan surel untuk registrasi baru', function () {
        catatRegistrasiUlang($this->lama, ['alasan' => '', 'prodi' => null, 'email' => null])
            ->assertSessionHasErrors(['alasan', 'prodi', 'email']);
    });

    it('tidak menawarkan jenis daftar internal di layar intake pindahan', function () {
        expect(JenisDaftar::optionsIntake())->not->toHaveKeys(['pindah_prodi', 'masuk_kembali'])
            ->and(PindahanService::jenisPilihan())->not->toHaveKeys(['pindah_prodi', 'masuk_kembali'])
            ->and(fn () => app(PindahanService::class)->perbaruiAsal(
                Mahasiswa::factory()->create(['prodi_id' => $this->if->id, 'jenis_daftar' => JenisDaftar::PindahProdi]),
                AsalPendidikanData::fromArray(['jenis_daftar' => 'baru']),
            ))->toThrow(AturanAkademikException::class);
    });

    it('mencatat medan HP biodata registrasi baru dengan nama yang sama seperti registrasi lama', function () {
        catatRegistrasiUlang($this->lama);
        $baru = Mahasiswa::query()->where('nim', '2026SI0042')->sole();

        expect(array_keys(app(MahasiswaMapper::class)->payload($baru)))->toBe(array_keys(app(MahasiswaMapper::class)->payload($this->lama->fresh())));
    });
});
