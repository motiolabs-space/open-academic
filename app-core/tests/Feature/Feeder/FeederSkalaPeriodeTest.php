<?php

declare(strict_types=1);

use App\Enums\ApplicantStatus;
use App\Enums\SemesterType;
use App\Exceptions\FeederException;
use App\Models\Akademik\PeriodePerkuliahan;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\SkalaNilaiProdi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Feeder\FeederValidationIssue;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbPendaftar;
use App\Models\Sdm\Staff;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederSyncService;
use App\Services\Feeder\FeederValidator;
use App\Services\Feeder\SkalaPeriodeProdiService;
use Database\Seeders\DemoCampusSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * Roadmap #24b: the two per-programme reference records PDDIKTI wants before
 * it will take a grade or a class — derived from the one campus scale and
 * the one calendar, so a programme that must differ edits rows, not code.
 */
beforeEach(function () {
    config(['feeder.enabled' => true, 'feeder.driver' => 'fake']);

    $this->fake = new FakeFeederClient;
    $this->app->instance(FeederClientInterface::class, $this->fake);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode' => 'IF', 'kode_pddikti' => 'a1b2c3d4']);

    $this->sync = app(FeederSyncService::class);
    $this->validator = app(FeederValidator::class);
});

describe('skala nilai prodi', function () {
    it('menurunkan skala kampus menjadi satu baris per prodi per huruf, lalu mengirimnya', function () {
        $hasil = $this->sync->sinkronkan('skala_nilai_prodi', $this->term);

        $jumlahHuruf = count(config('academic.grading.scale'));

        expect($hasil['terkirim'])->toBe($jumlahHuruf)
            ->and(SkalaNilaiProdi::where('prodi_id', $this->prodi->id)->count())->toBe($jumlahHuruf)
            ->and($this->fake->jumlahPanggilan('InsertSkalaNilaiProdi'))->toBe($jumlahHuruf);

        $a = SkalaNilaiProdi::where('nilai_huruf', 'A')->firstOrFail();
        $e = SkalaNilaiProdi::where('nilai_huruf', 'E')->firstOrFail();

        // The top letter reaches 100; every other letter ends where the one
        // above it starts; the scale takes effect from the first term.
        expect(FeederSyncLog::entity('skala_nilai_prodi')->where('local_id', $a->id)->firstOrFail()->payload)->toBe([
            'id_prodi' => 'a1b2c3d4',
            'nilai_huruf' => 'A',
            'nilai_indeks' => '4.00',
            'bobot_minimum' => '80.00',
            'bobot_maksimum' => '100.00',
            'tanggal_mulai_efektif' => '2026-09-01',
        ])->and(FeederSyncLog::entity('skala_nilai_prodi')->where('local_id', $e->id)->firstOrFail()->payload)->toMatchArray([
            'nilai_huruf' => 'E',
            'nilai_indeks' => '0.00',
            'bobot_minimum' => '0.00',
            'bobot_maksimum' => '45.00',
        ])->and($a->fresh()->feeder_id)->not->toBeNull();
    });

    it('melewati seluruh baris pada sinkronisasi berikutnya, dan tidak menurunkan ulang', function () {
        $this->sync->sinkronkan('skala_nilai_prodi', $this->term);
        $kedua = $this->sync->sinkronkan('skala_nilai_prodi', $this->term);

        expect($kedua['terkirim'])->toBe(0)
            ->and($kedua['dilewati'])->toBe(count(config('academic.grading.scale')))
            ->and(SkalaNilaiProdi::count())->toBe(count(config('academic.grading.scale')));
    });

    it('membiarkan skala yang sudah disunting prodi, dan mengirim suntingannya sebagai pembaruan berkunci id_bobot_nilai', function () {
        $this->sync->sinkronkan('skala_nilai_prodi', $this->term);

        $ab = SkalaNilaiProdi::where('nilai_huruf', 'AB')->firstOrFail();
        $ab->update(['bobot_minimum' => 76]);

        $hasil = $this->sync->sinkronkan('skala_nilai_prodi', $this->term);

        $panggilan = collect($this->fake->riwayat)->firstWhere('act', 'UpdateSkalaNilaiProdi');

        expect($hasil['terkirim'])->toBe(1)
            ->and($panggilan['payload']['key'])->toBe(['id_bobot_nilai' => $ab->feeder_id])
            ->and($panggilan['payload']['record']['bobot_minimum'])->toBe('76.00')
            ->and($ab->fresh()->bobot_minimum)->toBe('76.00');
    });

    it('menolak prodi tanpa kode PDDIKTI dan rentang yang terbalik sebelum mengirim', function () {
        Prodi::factory()->create(['kode' => 'SI', 'kode_pddikti' => null]);

        expect(fn () => $this->sync->sinkronkan('skala_nilai_prodi', $this->term))
            ->toThrow(FeederException::class, 'tidak lolos validasi');

        SkalaNilaiProdi::where('prodi_id', $this->prodi->id)->where('nilai_huruf', 'B')->update(['bobot_maksimum' => 60]);

        $hasil = $this->validator->periksa('skala_nilai_prodi', $this->term->kode, app(FeederSyncService::MAPPERS['skala_nilai_prodi']));

        expect(FeederValidationIssue::batch($hasil['batch'])->where('rule', 'prodi_tanpa_id_sms')->count())
            ->toBe(count(config('academic.grading.scale')))
            ->and(FeederValidationIssue::where('rule', 'rentang_terbalik')->firstOrFail()->message)->toContain('huruf B')
            ->and($hasil['error'])->toBeGreaterThan(0);
    });
});

describe('periode perkuliahan', function () {
    it('menurunkan satu periode per prodi dari kalender semester dan mengirimnya berkunci (prodi, semester)', function () {
        $hasil = $this->sync->sinkronkan('periode_perkuliahan', $this->term);
        $periode = PeriodePerkuliahan::firstOrFail();

        expect($hasil['terkirim'])->toBe(1)
            ->and(FeederSyncLog::entity('periode_perkuliahan')->firstOrFail()->payload)->toBe([
                'id_prodi' => 'a1b2c3d4',
                'id_semester' => '20261',
                'tanggal_awal_perkuliahan' => '2026-09-01',
                'tanggal_akhir_perkuliahan' => '2027-02-28',
                'jumlah_minggu_pertemuan' => 16,
                'metode_kuliah_tatap_muka' => 1,
                'metode_kuliah_daring' => 0,
                'metode_kuliah_campuran' => 0,
            ])
            ->and($periode->feeder_id)->toBe('a1b2c3d4:20261');
    });

    it('menghitung angka PMB semester itu per prodi, dan membiarkannya kosong bila tidak ada gelombang', function () {
        $gelombang = PmbGelombang::create([
            'tahun_akademik_id' => $this->term->id, 'nama' => 'Gelombang 1', 'kode' => 'G1',
            'tanggal_mulai' => '2026-03-01', 'tanggal_selesai' => '2026-06-30', 'biaya_pendaftaran' => 250000, 'is_active' => true,
        ]);

        $pendaftar = fn (ApplicantStatus $status, bool $diterima = true) => PmbPendaftar::create([
            'pmb_gelombang_id' => $gelombang->id, 'nomor_pendaftaran' => fake()->unique()->numerify('PMB######'),
            'nama' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'prodi_pilihan_1_id' => $this->prodi->id,
            'prodi_diterima_id' => $diterima ? $this->prodi->id : null, 'status' => $status,
        ]);

        $pendaftar(ApplicantStatus::Mendaftar, false);
        $pendaftar(ApplicantStatus::Seleksi, false);
        $pendaftar(ApplicantStatus::TidakLulus, false);
        $pendaftar(ApplicantStatus::Lulus);
        $pendaftar(ApplicantStatus::DaftarUlang);
        $pendaftar(ApplicantStatus::Mahasiswa);
        $pendaftar(ApplicantStatus::Batal);

        $this->sync->sinkronkan('periode_perkuliahan', $this->term);

        expect(FeederSyncLog::entity('periode_perkuliahan')->firstOrFail()->payload)->toMatchArray([
            // Registered-only and cancelled applicants never sat the selection.
            'jumlah_pendaftar_ikut_seleksi' => 5,
            'jumlah_pendaftar_lulus_seleksi' => 3,
            'jumlah_daftar_ulang' => 2,
            'jumlah_mengundurkan_diri' => 1,
        ]);

        // A term without an admission wave: not counted, not zero.
        $genap = TahunAkademik::factory()->term(2026, SemesterType::Genap)->create();
        $this->sync->sinkronkan('periode_perkuliahan', $genap);

        expect(FeederSyncLog::entity('periode_perkuliahan')->where('tahun_akademik_id', $genap->id)->firstOrFail()->payload)
            ->not->toHaveKey('jumlah_pendaftar_ikut_seleksi');
    });

    it('mengirim pembaruan dengan kunci id_prodi dan id_semester begitu kalendernya disunting', function () {
        $this->sync->sinkronkan('periode_perkuliahan', $this->term);

        PeriodePerkuliahan::firstOrFail()->update(['jumlah_minggu_pertemuan' => 14, 'metode_campuran' => true]);

        $hasil = $this->sync->sinkronkan('periode_perkuliahan', $this->term);
        $panggilan = collect($this->fake->riwayat)->firstWhere('act', 'UpdatePeriodePerkuliahan');

        expect($hasil['terkirim'])->toBe(1)
            ->and($panggilan['payload']['key'])->toBe(['id_prodi' => 'a1b2c3d4', 'id_semester' => '20261'])
            ->and($panggilan['payload']['record']['jumlah_minggu_pertemuan'])->toBe(14)
            ->and($panggilan['payload']['record']['metode_kuliah_campuran'])->toBe(1);
    });

    it('menolak periode yang berakhir sebelum dimulai', function () {
        $this->sync->sinkronkan('periode_perkuliahan', $this->term);
        PeriodePerkuliahan::firstOrFail()->update(['tanggal_akhir' => '2026-08-01']);

        expect(fn () => $this->sync->sinkronkan('periode_perkuliahan', $this->term))
            ->toThrow(FeederException::class, 'tidak lolos validasi');

        expect(FeederValidationIssue::where('rule', 'tanggal_periode_terbalik')->exists())->toBeTrue();
    });
});

describe('kampus demo dan layar monitor', function () {
    it('mengirim skala nilai dan periode seluruh prodi kampus demo lewat driver palsu', function () {
        // The demo campus brings its own calendar and programmes.
        $this->prodi->forceDelete();
        $this->term->forceDelete();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoCampusSeeder::class);
        $term = TahunAkademik::aktif();

        $skala = $this->sync->sinkronkan('skala_nilai_prodi', $term);
        $periode = $this->sync->sinkronkan('periode_perkuliahan', $term);

        $jumlahProdi = Prodi::count();

        expect($jumlahProdi)->toBeGreaterThan(1)
            ->and($skala['gagal'])->toBe(0)
            ->and($skala['terkirim'])->toBe($jumlahProdi * count(config('academic.grading.scale')))
            ->and($periode['gagal'])->toBe(0)
            ->and($periode['terkirim'])->toBe($jumlahProdi)
            ->and(SkalaNilaiProdi::whereNull('feeder_id')->exists())->toBeFalse()
            ->and(PeriodePerkuliahan::where('tahun_akademik_id', $term->id)->whereNull('feeder_id')->exists())->toBeFalse();
    });

    it('menampilkan kedua entitas sebelum kurikulum, dengan antrean yang terhitung tanpa menjalankan apa pun', function () {
        $this->seed(RolePermissionSeeder::class);
        TahunAkademik::query()->update(['tanggal_mulai' => now()->subWeeks(8), 'tanggal_selesai' => now()->addWeeks(8)]);
        $staf = Staff::factory()->create();
        $staf->assignRole('super-admin');

        app(SkalaPeriodeProdiService::class)->turunkanSkala();

        $html = $this->actingAs($staf, 'staff')->get('/admin/feeder')->assertOk()->getContent();

        expect($html)->toContain('Skala Nilai Prodi')->toContain('Periode Perkuliahan')
            ->and(strpos($html, 'InsertSkalaNilaiProdi'))->toBeLessThan(strpos($html, 'InsertKurikulum'))
            ->and($this->sync->jumlahAntre($this->term->kode)['skala_nilai_prodi'])->toBe(count(config('academic.grading.scale')));
    });
});
