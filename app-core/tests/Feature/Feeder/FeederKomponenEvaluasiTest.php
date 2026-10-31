<?php

declare(strict_types=1);

use App\Enums\FeederSyncStatus;
use App\Enums\JenisEvaluasi;
use App\Enums\SemesterType;
use App\Exceptions\FeederException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KomponenNilai;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\NilaiKomponen;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Feeder\FeederValidationIssue;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Akademik\PenilaianService;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederSyncService;
use App\Services\Feeder\FeederValidator;
use Database\Seeders\Demo\IntegrasiSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * The class evaluation plan and the per-component scores, reported to
 * PDDIKTI. Mandatory since the 2024 odd term — and the place where IKU 7 is
 * actually decided, which is why the threshold is tested as a number and not
 * as "a warning exists".
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

function kelasDiFeeder(array $atribut = []): KelasKuliah
{
    return KelasKuliah::factory()->create(array_merge([
        'tahun_akademik_id' => test()->term->id,
        'mata_kuliah_id' => MataKuliah::factory()->create(['prodi_id' => test()->prodi->id])->id,
        'prodi_id' => test()->prodi->id,
        'sks' => 3,
        'feeder_id' => 'kelas-feeder-1',
    ], $atribut));
}

/** @param array<int, array{string, int, string|null}> $rencana [nama, bobot, jenis] */
function rencanaEvaluasi(KelasKuliah $kelas, array $rencana): void
{
    foreach ($rencana as $urutan => [$nama, $bobot, $jenis]) {
        KomponenNilai::create([
            'kelas_kuliah_id' => $kelas->id,
            'nama' => $nama,
            'bobot' => $bobot,
            'jenis_evaluasi' => $jenis,
            'urutan' => $urutan,
        ]);
    }
}

function ledgerSukses(string $entity): void
{
    FeederSyncLog::create([
        'entity' => $entity,
        'action' => config("feeder.entities.{$entity}.action"),
        'tahun_akademik_id' => test()->term->id,
        'status' => FeederSyncStatus::Success,
        'payload_hash' => str_repeat('0', 64),
    ]);
}

/** A student with a registration at Feeder, enrolled in the class, with a finalised grade. */
function pesertaDenganNilaiFinal(KelasKuliah $kelas, string $registrasi = 'reg-feeder-1'): KrsDetail
{
    $mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => $kelas->prodi_id,
        'feeder_id' => 'mhs-'.$registrasi,
        'feeder_registrasi_id' => $registrasi,
    ]);

    $krs = Krs::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => $kelas->tahun_akademik_id,
        'semester_ke' => 3,
        'batas_sks' => 24,
        'status' => 'disetujui',
    ]);

    $detail = KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => $kelas->sks]);

    Nilai::create([
        'krs_detail_id' => $detail->id,
        'kelas_kuliah_id' => $kelas->id,
        'mahasiswa_id' => $mahasiswa->id,
        'nilai_angka' => 85,
        'nilai_huruf' => 'A',
        'bobot' => 4.00,
        'is_final' => true,
        'finalized_at' => now(),
    ]);

    return $detail;
}

describe('komponen evaluasi kelas', function () {
    it('mengirim satu baris per komponen dengan jenis terpetakan, urutan, dan bobot pecahan', function () {
        ledgerSukses('kelas_kuliah');
        $kelas = kelasDiFeeder();
        rencanaEvaluasi($kelas, [['Tugas', 30, 'tugas'], ['UTS', 30, 'uts'], ['UAS', 40, 'uas']]);

        $hasil = $this->sync->sinkronkan('komponen_evaluasi', $this->term);

        $payloads = FeederSyncLog::entity('komponen_evaluasi')->orderBy('id')->pluck('payload');

        expect($hasil['terkirim'])->toBe(3)
            ->and($payloads[0])->toBeJsonColumn([
                'id_kelas_kuliah' => 'kelas-feeder-1',
                'id_jenis_evaluasi' => '3',
                'nama' => 'Tugas',
                'nama_inggris' => 'Tugas',
                'nomor_urut' => 1,
                'bobot_evaluasi' => '0.30',
            ])
            ->and($payloads[2]['id_jenis_evaluasi'])->toBe('6')
            ->and($payloads[2]['bobot_evaluasi'])->toBe('0.40')
            ->and(KomponenNilai::whereNull('feeder_id')->count())->toBe(0);
    });

    it('melewati komponen yang tidak berubah dan memperbarui yang berubah dengan kunci id_komponen_evaluasi', function () {
        ledgerSukses('kelas_kuliah');
        $kelas = kelasDiFeeder();
        rencanaEvaluasi($kelas, [['UAS', 100, 'uas']]);

        $this->sync->sinkronkan('komponen_evaluasi', $this->term);
        $kedua = $this->sync->sinkronkan('komponen_evaluasi', $this->term);
        expect($kedua['dilewati'])->toBe(1);

        $komponen = KomponenNilai::firstOrFail();
        $komponen->update(['nama' => 'Ujian Akhir']);

        $ketiga = $this->sync->sinkronkan('komponen_evaluasi', $this->term);

        expect($ketiga['terkirim'])->toBe(1)
            ->and($this->fake->dipanggil('UpdateKomponenEvaluasiKelas'))->toBeTrue()
            ->and(collect($this->fake->riwayat)->last()['payload']['key'])->toBe(['id_komponen_evaluasi' => $komponen->feeder_id]);
    });

    it('menolak komponen tanpa jenis evaluasi sebelum dikirim', function () {
        ledgerSukses('kelas_kuliah');
        $kelas = kelasDiFeeder();
        rencanaEvaluasi($kelas, [['Tugas', 30, null], ['UTS', 30, 'uts'], ['UAS', 40, 'uas']]);

        expect(fn () => $this->sync->sinkronkan('komponen_evaluasi', $this->term))
            ->toThrow(FeederException::class, 'tidak lolos validasi');

        expect(FeederValidationIssue::where('rule', 'jenis_evaluasi_kosong')->count())->toBe(1)
            ->and($this->fake->dipanggil('InsertKomponenEvaluasiKelas'))->toBeFalse();
    });

    it('melaporkan bobot yang tidak berjumlah 100 sekali per kelas, bukan per komponen', function () {
        ledgerSukses('kelas_kuliah');
        $kelas = kelasDiFeeder();
        rencanaEvaluasi($kelas, [['Tugas', 30, 'tugas'], ['UTS', 30, 'uts'], ['UAS', 30, 'uas']]);

        $hasil = $this->validator->periksa('komponen_evaluasi', $this->term->kode, app(FeederSyncService::MAPPERS['komponen_evaluasi']));

        expect($hasil['error'])->toBe(1)
            ->and(FeederValidationIssue::where('rule', 'bobot_tidak_seratus')->count())->toBe(1);
    });

    it('menolak berjalan sebelum kelas kuliah disinkronkan', function () {
        expect(fn () => $this->sync->sinkronkan('komponen_evaluasi', $this->term))
            ->toThrow(FeederException::class, 'kelas_kuliah');
    });
});

describe('ambang kelas kolaboratif', function () {
    it('memperingatkan kelas kolaboratif yang bobot partisipatif + proyeknya di bawah 50%, tanpa menahan pengiriman', function () {
        ledgerSukses('kelas_kuliah');
        $kelas = kelasDiFeeder(['is_case_method' => true]);
        // 30% participatory: flagged for IKU 7, but PDDIKTI will not count it.
        rencanaEvaluasi($kelas, [['Diskusi kasus', 30, 'partisipatif'], ['UTS', 30, 'uts'], ['UAS', 40, 'uas']]);

        $hasil = $this->sync->sinkronkan('komponen_evaluasi', $this->term);

        $isu = FeederValidationIssue::where('rule', 'iku7_bobot_kurang')->get();

        expect($hasil['terkirim'])->toBe(3)
            ->and($isu)->toHaveCount(1)
            ->and($isu->first()->severity)->toBe('warning')
            ->and($isu->first()->message)->toContain('30%')->toContain('50%');
    });

    it('diam ketika partisipatif + proyek mencapai 50%', function () {
        ledgerSukses('kelas_kuliah');
        $kelas = kelasDiFeeder(['is_team_based_project' => true]);
        rencanaEvaluasi($kelas, [['Diskusi', 20, 'partisipatif'], ['Proyek', 30, 'proyek'], ['UAS', 50, 'uas']]);

        $this->validator->periksa('komponen_evaluasi', $this->term->kode, app(FeederSyncService::MAPPERS['komponen_evaluasi']));

        expect(FeederValidationIssue::where('rule', 'iku7_bobot_kurang')->exists())->toBeFalse();
    });

    it('tidak menilai kelas yang memang tidak ditandai kolaboratif', function () {
        ledgerSukses('kelas_kuliah');
        $kelas = kelasDiFeeder();
        rencanaEvaluasi($kelas, [['UTS', 50, 'uts'], ['UAS', 50, 'uas']]);

        $this->validator->periksa('komponen_evaluasi', $this->term->kode, app(FeederSyncService::MAPPERS['komponen_evaluasi']));

        expect(FeederValidationIssue::where('rule', 'iku7_bobot_kurang')->exists())->toBeFalse();
    });

    it('memberi jenis pada komponen bawaan, sehingga kelas baru langsung dapat dilaporkan', function () {
        $kelas = kelasDiFeeder();

        $komponen = app(PenilaianService::class)->komponen($kelas);

        expect($komponen->pluck('jenis_evaluasi')->all())
            ->toBe([JenisEvaluasi::Tugas, JenisEvaluasi::Uts, JenisEvaluasi::Uas]);
    });
});

describe('nilai per komponen', function () {
    it('mengirim skor di balik nilai final dengan kunci alami, lalu melewatinya', function () {
        ledgerSukses('komponen_evaluasi');
        ledgerSukses('krs');
        $kelas = kelasDiFeeder();
        rencanaEvaluasi($kelas, [['UAS', 100, 'uas']]);
        $komponen = KomponenNilai::firstOrFail();
        $komponen->forceFill(['feeder_id' => 'komponen-feeder-1'])->save();

        $detail = pesertaDenganNilaiFinal($kelas);
        NilaiKomponen::create(['komponen_nilai_id' => $komponen->id, 'krs_detail_id' => $detail->id, 'nilai' => 85]);

        $hasil = $this->sync->sinkronkan('nilai_komponen', $this->term);
        $panggilan = collect($this->fake->riwayat)->last();

        expect($hasil['terkirim'])->toBe(1)
            ->and($panggilan['act'])->toBe('UpdateNilaiPerkuliahanKelasKomponenEvaluasi')
            ->and($panggilan['payload']['key'])->toBe([
                'id_registrasi_mahasiswa' => 'reg-feeder-1',
                'id_kelas_kuliah' => 'kelas-feeder-1',
                'id_komponen_evaluasi' => 'komponen-feeder-1',
            ])
            ->and($panggilan['payload']['record'])->toBe(['nilai_komponen_evaluasi' => '85.00'])
            ->and(NilaiKomponen::firstOrFail()->feeder_id)->toBe('komponen-feeder-1')
            ->and($this->sync->sinkronkan('nilai_komponen', $this->term)['dilewati'])->toBe(1);
    });

    it('mengirim ulang dengan aksi yang sama ketika skornya dikoreksi', function () {
        ledgerSukses('komponen_evaluasi');
        ledgerSukses('krs');
        $kelas = kelasDiFeeder();
        rencanaEvaluasi($kelas, [['UAS', 100, 'uas']]);
        KomponenNilai::query()->update(['feeder_id' => 'komponen-feeder-1']);
        $detail = pesertaDenganNilaiFinal($kelas);
        $skor = NilaiKomponen::create(['komponen_nilai_id' => KomponenNilai::firstOrFail()->id, 'krs_detail_id' => $detail->id, 'nilai' => 85]);

        $this->sync->sinkronkan('nilai_komponen', $this->term);
        $skor->update(['nilai' => 90]);
        $hasil = $this->sync->sinkronkan('nilai_komponen', $this->term);

        expect($hasil['terkirim'])->toBe(1)
            ->and($this->fake->jumlahPanggilan('UpdateNilaiPerkuliahanKelasKomponenEvaluasi'))->toBe(2)
            ->and($this->fake->dipanggil('InsertNilaiPerkuliahanKelasKomponenEvaluasi'))->toBeFalse();
    });

    it('tidak mengambil skor yang nilai akhirnya belum final', function () {
        $kelas = kelasDiFeeder();
        rencanaEvaluasi($kelas, [['UAS', 100, 'uas']]);
        $detail = pesertaDenganNilaiFinal($kelas);
        Nilai::query()->update(['is_final' => false]);
        NilaiKomponen::create(['komponen_nilai_id' => KomponenNilai::firstOrFail()->id, 'krs_detail_id' => $detail->id, 'nilai' => 85]);

        expect(app(FeederSyncService::MAPPERS['nilai_komponen'])->rows($this->term->kode))->toHaveCount(0);
    });

    it('menolak skor yang komponennya belum sampai di Feeder', function () {
        ledgerSukses('komponen_evaluasi');
        ledgerSukses('krs');
        $kelas = kelasDiFeeder();
        rencanaEvaluasi($kelas, [['UAS', 100, 'uas']]);
        $detail = pesertaDenganNilaiFinal($kelas);
        NilaiKomponen::create(['komponen_nilai_id' => KomponenNilai::firstOrFail()->id, 'krs_detail_id' => $detail->id, 'nilai' => 85]);

        expect(fn () => $this->sync->sinkronkan('nilai_komponen', $this->term))
            ->toThrow(FeederException::class, 'tidak lolos validasi');

        expect(FeederValidationIssue::where('rule', 'komponen_belum_sinkron')->exists())->toBeTrue();
    });

    it('menolak berjalan sebelum komponen evaluasi dan KRS disinkronkan', function () {
        expect(fn () => $this->sync->sinkronkan('nilai_komponen', $this->term))
            ->toThrow(FeederException::class, 'komponen_evaluasi');
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
            ->assertSee('Komponen Evaluasi Kelas')
            ->assertSee('Nilai per Komponen Evaluasi');
    });
});
