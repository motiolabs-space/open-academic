<?php

declare(strict_types=1);

use App\Enums\FeederSyncStatus;
use App\Enums\Gender;
use App\Enums\HasilEvaluasi;
use App\Enums\KeputusanEvaluasi;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Exceptions\FeederException;
use App\Models\Akademik\KelasDosen;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\PertemuanKelas;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederMapping;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Feeder\FeederValidationIssue;
use App\Models\Kemahasiswaan\EvaluasiStudi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederSyncService;
use App\Services\Feeder\FeederValidator;
use Database\Seeders\Demo\IntegrasiSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * Two Feeder entities that had local tables from the first release and were
 * never sent: the lecturer teaching a class, and the record that closes a
 * student's registration. Both are what PDDIKTI reads to decide things the
 * campus cares about — the lecturer ratio, and whether a graduate may be
 * given a diploma number — so the tests pin the payload, not just the status.
 */
beforeEach(function () {
    config(['feeder.enabled' => true, 'feeder.driver' => 'fake']);

    $this->fake = new FakeFeederClient;
    $this->app->instance(FeederClientInterface::class, $this->fake);

    $this->seed(IntegrasiSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode_pddikti' => 'a1b2c3d4']);

    $this->sync = app(FeederSyncService::class);
    $this->validator = app(FeederValidator::class);
});

/** A class already known to Feeder, so its lecturers can be reported. */
function kelasTersinkron(array $atribut = []): KelasKuliah
{
    return KelasKuliah::factory()->create(array_merge([
        'tahun_akademik_id' => test()->term->id,
        'mata_kuliah_id' => MataKuliah::factory()->create(['prodi_id' => test()->prodi->id])->id,
        'prodi_id' => test()->prodi->id,
        'sks' => 3,
        'feeder_id' => 'kelas-feeder-1',
    ], $atribut));
}

/** A lecturer PDDIKTI already knows, i.e. one whose id_dosen has been pulled. */
function dosenTerpetakan(array $atribut = []): Dosen
{
    return Dosen::factory()->create(array_merge([
        'prodi_id' => test()->prodi->id,
        'feeder_id' => 'dosen-feeder-1',
    ], $atribut));
}

/** A student whose registration has reached Feeder. */
function mahasiswaTerdaftarDiFeeder(array $atribut = []): Mahasiswa
{
    return Mahasiswa::factory()->create(array_merge([
        'prodi_id' => test()->prodi->id,
        'nik' => '3201234567890123',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2003-05-12',
        'jenis_kelamin' => Gender::LakiLaki,
        'term_masuk' => '20221',
        'feeder_id' => 'mhs-feeder-1',
        'feeder_registrasi_id' => 'reg-feeder-1',
    ], $atribut));
}

function yudisiumDitetapkan(Mahasiswa $mahasiswa, array $atribut = []): Yudisium
{
    $mahasiswa->update(['status' => StudentStatus::Lulus]);

    return Yudisium::create(array_merge([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'nomor_sk' => 'SK/REKTOR/123/2026',
        'tanggal_yudisium' => '2026-09-01',
        'tanggal_lulus' => '2026-09-05',
        'total_sks' => 146,
        'ipk' => 3.47,
        'predikat' => 'Sangat Memuaskan',
        'judul_tugas_akhir' => 'Sistem Informasi Akademik Terbuka',
        'status' => 'ditetapkan',
    ], $atribut));
}

/** The riwayat_pendidikan dependency, satisfied by a ledger row for this term. */
function registrasiSudahSinkron(): void
{
    FeederSyncLog::create([
        'entity' => 'riwayat_pendidikan',
        'action' => 'InsertRiwayatPendidikanMahasiswa',
        'tahun_akademik_id' => test()->term->id,
        'status' => FeederSyncStatus::Success,
        'payload_hash' => str_repeat('0', 64),
    ]);
}

function kelasSudahSinkron(): void
{
    FeederSyncLog::create([
        'entity' => 'kelas_kuliah',
        'action' => 'InsertKelasKuliah',
        'tahun_akademik_id' => test()->term->id,
        'status' => FeederSyncStatus::Success,
        'payload_hash' => str_repeat('0', 64),
    ]);
}

describe('dosen pengajar kelas', function () {
    it('mengirim satu baris per dosen per kelas dengan SKS, pertemuan, dan id_dosen PDDIKTI', function () {
        kelasSudahSinkron();
        $kelas = kelasTersinkron();
        $dosen = dosenTerpetakan(['nidn' => '0012345678']);
        $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu', 'porsi_sks' => 2.00]);

        foreach ([1, 2, 3] as $ke) {
            PertemuanKelas::create([
                'kelas_kuliah_id' => $kelas->id,
                'pertemuan_ke' => $ke,
                'tanggal' => now()->subWeeks(4 - $ke)->toDateString(),
                'is_terlaksana' => $ke < 3,
            ]);
        }

        $hasil = $this->sync->sinkronkan('dosen_pengajar', $this->term);

        $payload = FeederSyncLog::entity('dosen_pengajar')->firstOrFail()->payload;

        expect($hasil['terkirim'])->toBe(1)
            ->and($this->fake->jumlahPanggilan('InsertDosenPengajarKelasKuliah'))->toBe(1)
            ->and($payload['id_kelas_kuliah'])->toBe('kelas-feeder-1')
            ->and($payload['id_dosen'])->toBe('dosen-feeder-1')
            ->and($payload['nidn'])->toBe('0012345678')
            ->and($payload['sks_substansi_total'])->toBe('2.00')
            ->and($payload['rencana_minggu_pertemuan'])->toBe(16)
            ->and($payload['realisasi_minggu_pertemuan'])->toBe(2);
    });

    it('melaporkan seluruh SKS kelas bila porsi tidak diisi, dan memperingatkannya pada team teaching', function () {
        kelasSudahSinkron();
        $kelas = kelasTersinkron(['sks' => 3]);
        $kelas->dosen()->attach(dosenTerpetakan()->id, ['peran' => 'pengampu']);
        $kelas->dosen()->attach(dosenTerpetakan(['feeder_id' => 'dosen-feeder-2'])->id, ['peran' => 'pendamping']);

        $hasil = $this->sync->sinkronkan('dosen_pengajar', $this->term);

        $payloads = FeederSyncLog::entity('dosen_pengajar')->pluck('payload');

        expect($hasil['terkirim'])->toBe(2)
            ->and($payloads->pluck('sks_substansi_total')->all())->toBe(['3.00', '3.00'])
            ->and(FeederValidationIssue::where('rule', 'porsi_sks_kosong')->where('severity', 'warning')->count())->toBe(2);
    });

    it('menolak sebelum dikirim bila dosennya belum punya id_dosen PDDIKTI', function () {
        kelasSudahSinkron();
        $kelas = kelasTersinkron();
        $praktisi = Dosen::factory()->praktisi()->create(['prodi_id' => $this->prodi->id]);
        $kelas->dosen()->attach($praktisi->id, ['peran' => 'praktisi']);

        expect(fn () => $this->sync->sinkronkan('dosen_pengajar', $this->term))
            ->toThrow(FeederException::class, 'tidak lolos validasi');

        expect($this->fake->dipanggil('InsertDosenPengajarKelasKuliah'))->toBeFalse()
            ->and(FeederValidationIssue::where('rule', 'dosen_belum_dipetakan')->exists())->toBeTrue();
    });

    it('menolak bila kelasnya sendiri belum sampai di Feeder', function () {
        kelasSudahSinkron();
        $kelas = kelasTersinkron(['feeder_id' => null]);
        $kelas->dosen()->attach(dosenTerpetakan()->id, ['peran' => 'pengampu']);

        $hasil = $this->validator->periksa('dosen_pengajar', $this->term->kode, app(FeederSyncService::MAPPERS['dosen_pengajar']));

        expect($hasil['error'])->toBe(1)
            ->and(FeederValidationIssue::where('rule', 'kelas_belum_sinkron')->exists())->toBeTrue();
    });

    it('tidak mengirim ulang baris yang tidak berubah, dan memperbarui yang berubah', function () {
        kelasSudahSinkron();
        $kelas = kelasTersinkron();
        $dosen = dosenTerpetakan();
        $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu', 'porsi_sks' => 3.00]);

        $this->sync->sinkronkan('dosen_pengajar', $this->term);
        $kedua = $this->sync->sinkronkan('dosen_pengajar', $this->term);

        expect($kedua['dilewati'])->toBe(1)->and($kedua['terkirim'])->toBe(0)
            ->and(KelasDosen::firstOrFail()->feeder_id)->not->toBeNull();

        KelasDosen::query()->update(['porsi_sks' => 1.50]);

        $ketiga = $this->sync->sinkronkan('dosen_pengajar', $this->term);

        expect($ketiga['terkirim'])->toBe(1)
            ->and($this->fake->dipanggil('UpdateDosenPengajarKelasKuliah'))->toBeTrue()
            ->and(collect($this->fake->riwayat)->last()['payload']['key'])->toBe(['id_aktivitas_mengajar' => KelasDosen::firstOrFail()->feeder_id]);
    });

    it('menolak berjalan sebelum kelas kuliah disinkronkan pada semester yang sama', function () {
        expect(fn () => $this->sync->sinkronkan('dosen_pengajar', $this->term))
            ->toThrow(FeederException::class, 'kelas_kuliah');
    });
});

describe('pemetaan id_dosen dari referensi', function () {
    it('mengisi feeder_id dosen dari GetListDosen berdasarkan NIDN, dan membiarkan yang tidak cocok', function () {
        $cocok = Dosen::factory()->create(['prodi_id' => $this->prodi->id, 'nidn' => '0011223344']);
        $lain = Dosen::factory()->create(['prodi_id' => $this->prodi->id, 'nidn' => '0099887766']);

        $this->fake->balas('GetListDosen', [
            ['id_dosen' => 'uuid-dosen-a', 'nama_dosen' => 'A', 'nidn' => '0011223344'],
            ['id_dosen' => 'uuid-dosen-b', 'nama_dosen' => 'B', 'nidn' => '0000000000'],
        ]);

        $hasil = $this->sync->tarikReferensi();

        expect($hasil['dosen'])->toBe(2)
            ->and($cocok->fresh()->feeder_id)->toBe('uuid-dosen-a')
            ->and($lain->fresh()->feeder_id)->toBeNull();
    });

    it('tidak menimpa id_dosen yang sudah ada', function () {
        $dosen = Dosen::factory()->create(['prodi_id' => $this->prodi->id, 'nidn' => '0011223344', 'feeder_id' => 'sudah-ada']);

        $this->fake->balas('GetListDosen', [
            ['id_dosen' => 'uuid-baru', 'nidn' => '0011223344'],
        ]);

        $this->sync->tarikReferensi();

        expect($dosen->fresh()->feeder_id)->toBe('sudah-ada');
    });
});

describe('mahasiswa lulus / DO', function () {
    it('mengirim lulusan dengan jenis keluar terpetakan, SK yudisium, IPK, dan periode keluar', function () {
        registrasiSudahSinkron();
        $mahasiswa = mahasiswaTerdaftarDiFeeder();
        yudisiumDitetapkan($mahasiswa);

        $hasil = $this->sync->sinkronkan('lulus_do', $this->term);

        $payload = FeederSyncLog::entity('lulus_do')->firstOrFail()->payload;

        expect($hasil['terkirim'])->toBe(1)
            ->and($payload)->toBe([
                'id_registrasi_mahasiswa' => 'reg-feeder-1',
                'id_jenis_keluar' => '1',
                'tanggal_keluar' => '2026-09-05',
                'id_periode_keluar' => $this->term->kode,
                'nomor_sk_yudisium' => 'SK/REKTOR/123/2026',
                'tanggal_sk_yudisium' => '2026-09-01',
                'ipk' => '3.47',
                'judul_skripsi' => 'Sistem Informasi Akademik Terbuka',
            ]);
    });

    it('mengirim mahasiswa yang diputus DO oleh evaluasi studi, tanpa alasan tertulisnya', function () {
        registrasiSudahSinkron();
        $mahasiswa = mahasiswaTerdaftarDiFeeder(['status' => StudentStatus::DropOut]);

        EvaluasiStudi::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'tahap' => 'semester_4',
            'semester_ke' => 4,
            'sks_kumulatif' => 20,
            'ipk' => 1.80,
            'ips' => 1.50,
            'temuan' => HasilEvaluasi::TidakMemenuhi,
            'keputusan' => KeputusanEvaluasi::DropOut,
            'diputuskan_at' => '2026-08-20 10:00:00',
            'catatan' => 'Alasan pribadi yang tidak perlu sampai ke kementerian.',
        ]);

        $this->sync->sinkronkan('lulus_do', $this->term);

        $payload = FeederSyncLog::entity('lulus_do')->firstOrFail()->payload;

        expect($payload['id_jenis_keluar'])->toBe('3')
            ->and($payload['tanggal_keluar'])->toBe('2026-08-20')
            ->and($payload['keterangan'])->toBe(KeputusanEvaluasi::DropOut->label())
            ->and($payload)->not->toHaveKey('nomor_sk_yudisium')
            ->and(json_encode($payload))->not->toContain('Alasan pribadi');
    });

    it('menandai registrasi sebagai tertutup dengan kunci registrasinya, lalu melewatinya', function () {
        registrasiSudahSinkron();
        $mahasiswa = mahasiswaTerdaftarDiFeeder();
        yudisiumDitetapkan($mahasiswa);

        // Feeder answers this act with no identifier worth storing.
        $this->fake->balas('InsertMahasiswaLulusDO', []);

        $this->sync->sinkronkan('lulus_do', $this->term);
        $kedua = $this->sync->sinkronkan('lulus_do', $this->term);

        expect($mahasiswa->fresh()->feeder_keluar_id)->toBe('reg-feeder-1')
            ->and($kedua['dilewati'])->toBe(1)
            ->and($this->fake->jumlahPanggilan('InsertMahasiswaLulusDO'))->toBe(1);
    });

    it('memperbarui dengan kunci id_registrasi_mahasiswa begitu nomor SK diisi belakangan', function () {
        registrasiSudahSinkron();
        $mahasiswa = mahasiswaTerdaftarDiFeeder();
        $yudisium = yudisiumDitetapkan($mahasiswa, ['nomor_sk' => null]);

        $this->sync->sinkronkan('lulus_do', $this->term);
        $yudisium->update(['nomor_sk' => 'SK/REKTOR/999/2026']);
        $hasil = $this->sync->sinkronkan('lulus_do', $this->term);

        expect($hasil['terkirim'])->toBe(1)
            ->and($this->fake->dipanggil('UpdateMahasiswaLulusDO'))->toBeTrue()
            ->and(collect($this->fake->riwayat)->last()['payload']['key'])->toBe(['id_registrasi_mahasiswa' => 'reg-feeder-1']);
    });

    it('hanya mengambil yang keluar pada semester itu, bukan seluruh alumni', function () {
        registrasiSudahSinkron();
        $termLalu = TahunAkademik::factory()->term(2025, SemesterType::Genap)->create();

        yudisiumDitetapkan(mahasiswaTerdaftarDiFeeder(['nim' => '2101']), ['tahun_akademik_id' => $termLalu->id]);
        yudisiumDitetapkan(mahasiswaTerdaftarDiFeeder(['nim' => '2102', 'feeder_registrasi_id' => 'reg-feeder-2']));

        $rows = app(FeederSyncService::MAPPERS['lulus_do'])->rows($this->term->kode);

        expect($rows->pluck('nim')->all())->toBe(['2102']);
    });

    it('menolak lulusan yang yudisiumnya belum ditetapkan, alih-alih diam-diam melewatkannya', function () {
        registrasiSudahSinkron();
        $mahasiswa = mahasiswaTerdaftarDiFeeder();
        yudisiumDitetapkan($mahasiswa, ['status' => 'diajukan']);

        // The student row says Lulus; the yudisium says otherwise. It must be
        // surfaced as an error — not fall out of the list and stay Aktif at
        // PDDIKTI for another term.
        expect(fn () => $this->sync->sinkronkan('lulus_do', $this->term))
            ->toThrow(FeederException::class, 'tidak lolos validasi');

        expect(FeederValidationIssue::where('rule', 'yudisium_belum_ditetapkan')->exists())->toBeTrue()
            ->and($this->fake->dipanggil('InsertMahasiswaLulusDO'))->toBeFalse();
    });

    it('menolak bila jenis keluarnya belum dipetakan ke kode PDDIKTI', function () {
        registrasiSudahSinkron();
        FeederMapping::query()->where('group', 'jenis_keluar')->delete();

        $mahasiswa = mahasiswaTerdaftarDiFeeder();
        yudisiumDitetapkan($mahasiswa);

        expect(fn () => $this->sync->sinkronkan('lulus_do', $this->term))
            ->toThrow(FeederException::class, 'tidak lolos validasi');

        expect(FeederValidationIssue::where('rule', 'jenis_keluar_belum_dipetakan')->exists())->toBeTrue()
            ->and($this->fake->dipanggil('InsertMahasiswaLulusDO'))->toBeFalse();
    });

    it('memperingatkan SK yudisium yang kosong tanpa menghalangi pengiriman', function () {
        registrasiSudahSinkron();
        yudisiumDitetapkan(mahasiswaTerdaftarDiFeeder(), ['nomor_sk' => null]);

        $hasil = $this->sync->sinkronkan('lulus_do', $this->term);

        expect($hasil['terkirim'])->toBe(1)
            ->and(FeederValidationIssue::where('rule', 'nomor_sk_kosong')->where('severity', 'warning')->exists())->toBeTrue();
    });

    it('menolak berjalan sebelum riwayat pendidikan disinkronkan', function () {
        expect(fn () => $this->sync->sinkronkan('lulus_do', $this->term))
            ->toThrow(FeederException::class, 'riwayat_pendidikan');
    });
});

describe('layar monitor', function () {
    it('menampilkan kedua entitas baru pada konsol Feeder', function () {
        $this->seed(RolePermissionSeeder::class);
        TahunAkademik::query()->update(['tanggal_mulai' => now()->subWeeks(8), 'tanggal_selesai' => now()->addWeeks(8)]);

        $staf = Staff::factory()->create();
        $staf->assignRole('super-admin');

        $this->actingAs($staf, 'staff')
            ->get('/admin/feeder')
            ->assertOk()
            ->assertSee('Dosen Pengajar Kelas')
            ->assertSee('Mahasiswa Lulus / DO')
            ->assertSee('InsertDosenPengajarKelasKuliah')
            ->assertSee('InsertMahasiswaLulusDO');
    });
});
