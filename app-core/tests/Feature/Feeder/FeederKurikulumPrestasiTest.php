<?php

declare(strict_types=1);

use App\Enums\FeederSyncStatus;
use App\Enums\Gender;
use App\Enums\JenisDaftar;
use App\Enums\JenisKonversi;
use App\Enums\JenisPoin;
use App\Enums\SemesterType;
use App\Enums\StatusKonversi;
use App\Exceptions\FeederException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KonversiKredit;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\KurikulumMataKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederMapping;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Feeder\FeederValidationIssue;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\PoinKategori;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederSyncService;
use App\Services\Feeder\FeederValidator;
use Database\Seeders\Demo\IntegrasiSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * Roadmap #23–#25: the reference data every other payload points at, the
 * three records that recognise credit earned elsewhere, and how a student
 * came in.
 */
beforeEach(function () {
    config(['feeder.enabled' => true, 'feeder.driver' => 'fake']);

    $this->fake = new FakeFeederClient;
    $this->app->instance(FeederClientInterface::class, $this->fake);

    $this->seed(IntegrasiSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode' => 'IF', 'kode_pddikti' => 'a1b2c3d4']);

    $this->sync = app(FeederSyncService::class);
    $this->validator = app(FeederValidator::class);
});

function sukses(string $entity): void
{
    FeederSyncLog::create([
        'entity' => $entity,
        'action' => config("feeder.entities.{$entity}.action"),
        'tahun_akademik_id' => test()->term->id,
        'status' => FeederSyncStatus::Success,
        'payload_hash' => str_repeat('0', 64),
    ]);
}

function mkDiFeeder(string $kode = 'IF101', array $atribut = []): MataKuliah
{
    return MataKuliah::factory()->create(array_merge([
        'prodi_id' => test()->prodi->id, 'kode' => $kode, 'sks' => 3, 'sks_teori' => 2, 'sks_praktik' => 1, 'feeder_id' => 'mk-'.$kode,
    ], $atribut));
}

function mhsDiFeeder(array $atribut = []): Mahasiswa
{
    return Mahasiswa::factory()->create(array_merge([
        'prodi_id' => test()->prodi->id,
        'nik' => '3201234567890123',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2004-05-12',
        'jenis_kelamin' => Gender::LakiLaki,
        'term_masuk' => test()->term->kode,
        'feeder_id' => 'mhs-1',
        'feeder_registrasi_id' => 'reg-1',
    ], $atribut));
}

describe('kurikulum dan mata kuliah (#24)', function () {
    it('mengirim kurikulum dengan semester mulai berlaku dan jumlah SKS-nya', function () {
        Kurikulum::factory()->create(['prodi_id' => $this->prodi->id, 'nama' => 'Kurikulum OBE 2024', 'tahun_mulai' => 2024, 'sks_lulus' => 146, 'sks_wajib' => 120, 'sks_pilihan' => 26]);

        $hasil = $this->sync->sinkronkan('kurikulum', $this->term);
        $payload = FeederSyncLog::entity('kurikulum')->firstOrFail()->payload;

        expect($hasil['terkirim'])->toBe(1)
            ->and($payload)->toBe([
                'nama_kurikulum' => 'Kurikulum OBE 2024',
                'id_prodi' => 'a1b2c3d4',
                'id_semester' => '20241',
                'jumlah_sks_lulus' => 146,
                'jumlah_sks_wajib' => 120,
                'jumlah_sks_pilihan' => 26,
            ])
            ->and(Kurikulum::firstOrFail()->feeder_id)->not->toBeNull();
    });

    it('mengirim mata kuliah dengan pembagian SKS dan jenis dari penempatan kurikulumnya', function () {
        $kurikulum = Kurikulum::factory()->create(['prodi_id' => $this->prodi->id]);
        $mk = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id, 'kode' => 'IF201', 'nama' => 'Basis Data', 'sks' => 3, 'sks_teori' => 2, 'sks_praktik' => 1, 'sks_praktik_lapangan' => 0]);
        $kurikulum->mataKuliah()->attach($mk->id, ['semester' => 3, 'jenis' => 'pilihan']);

        $this->sync->sinkronkan('mata_kuliah', $this->term);
        $payload = FeederSyncLog::entity('mata_kuliah')->firstOrFail()->payload;

        expect($payload['kode_mata_kuliah'])->toBe('IF201')
            ->and($payload['id_jenis_mata_kuliah'])->toBe('B')
            ->and($payload['sks_mata_kuliah'])->toBe(3)
            ->and($payload['sks_tatap_muka'])->toBe(2)
            ->and($payload['sks_praktek'])->toBe(1)
            ->and($mk->fresh()->feeder_id)->not->toBeNull();
    });

    it('mengirim penempatan mata kuliah dalam kurikulum, dikunci pada pasangan id-nya', function () {
        sukses('kurikulum');
        sukses('mata_kuliah');
        $kurikulum = Kurikulum::factory()->create(['prodi_id' => $this->prodi->id, 'feeder_id' => 'kur-1']);
        $mk = mkDiFeeder('IF101');
        $kurikulum->mataKuliah()->attach($mk->id, ['semester' => 1, 'jenis' => 'wajib']);

        $hasil = $this->sync->sinkronkan('matkul_kurikulum', $this->term);
        $baris = KurikulumMataKuliah::firstOrFail();

        expect($hasil['terkirim'])->toBe(1)
            ->and(FeederSyncLog::entity('matkul_kurikulum')->firstOrFail()->payload)->toBe([
                'id_kurikulum' => 'kur-1', 'id_matkul' => 'mk-IF101', 'semester' => 1, 'sks_mata_kuliah' => 3, 'apakah_wajib' => 1,
            ])
            ->and($baris->feeder_id)->toBe('kur-1:mk-IF101');

        // A changed placement is an update keyed on the pair, not a fresh insert.
        $baris->update(['semester' => 2]);
        $this->sync->sinkronkan('matkul_kurikulum', $this->term);

        expect(collect($this->fake->riwayat)->last())->toMatchArray([
            'act' => 'UpdateMatkulKurikulum',
            'payload' => ['key' => ['id_kurikulum' => 'kur-1', 'id_matkul' => 'mk-IF101'], 'record' => ['id_kurikulum' => 'kur-1', 'id_matkul' => 'mk-IF101', 'semester' => 2, 'sks_mata_kuliah' => 3, 'apakah_wajib' => 1]],
        ]);
    });

    it('menahan penempatan sampai kurikulum dan mata kuliahnya sendiri sampai di Feeder', function () {
        $kurikulum = Kurikulum::factory()->create(['prodi_id' => $this->prodi->id]);
        $kurikulum->mataKuliah()->attach(MataKuliah::factory()->create(['prodi_id' => $this->prodi->id])->id, ['semester' => 1, 'jenis' => 'wajib']);

        expect(app(FeederSyncService::MAPPERS['matkul_kurikulum'])->rows($this->term->kode))->toHaveCount(0);
    });

    it('memakai id_matkul Feeder pada payload kelas dan KRS begitu katalog terkirim', function () {
        $mk = mkDiFeeder('IF101');
        $kelas = KelasKuliah::factory()->create(['tahun_akademik_id' => $this->term->id, 'mata_kuliah_id' => $mk->id, 'prodi_id' => $this->prodi->id, 'sks' => 3]);
        $dosen = Dosen::factory()->create(['prodi_id' => $this->prodi->id, 'feeder_id' => 'dosen-1']);
        $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu']);

        $this->sync->sinkronkan('kelas_kuliah', $this->term);

        expect(FeederSyncLog::entity('kelas_kuliah')->firstOrFail()->payload['id_matkul'])->toBe('mk-IF101');
    });

    it('memperingatkan kelas yang mata kuliahnya belum terkirim, karena kode lokal yang dikirim akan ditolak', function () {
        $mk = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id, 'kode' => 'IF999']);
        $kelas = KelasKuliah::factory()->create(['tahun_akademik_id' => $this->term->id, 'mata_kuliah_id' => $mk->id, 'prodi_id' => $this->prodi->id, 'sks' => 3]);
        $kelas->dosen()->attach(Dosen::factory()->create(['prodi_id' => $this->prodi->id])->id, ['peran' => 'pengampu']);

        $this->validator->periksa('kelas_kuliah', $this->term->kode, app(FeederSyncService::MAPPERS['kelas_kuliah']));

        expect(FeederValidationIssue::where('rule', 'matkul_belum_sinkron')->where('severity', 'warning')->exists())->toBeTrue();
    });
});

describe('prestasi, transfer kredit, konversi MBKM (#23)', function () {
    it('mengirim prestasi terverifikasi dengan jenis dan tingkat terpetakan', function () {
        sukses('mahasiswa');
        FeederMapping::create(['group' => 'jenis_prestasi', 'local_value' => 'LOMBA', 'feeder_code' => '1']);
        FeederMapping::create(['group' => 'tingkat_prestasi', 'local_value' => 'nasional', 'feeder_code' => '3']);
        $kategori = PoinKategori::create(['kode' => 'LOMBA', 'nama' => 'Juara lomba', 'jenis' => JenisPoin::Prestasi, 'tingkat' => 'nasional', 'poin' => 30]);
        $m = mhsDiFeeder();

        PoinMahasiswa::create(['mahasiswa_id' => $m->id, 'poin_kategori_id' => $kategori->id, 'tahun_akademik_id' => $this->term->id, 'tanggal' => '2026-09-10', 'judul' => 'Juara 1 Gemastik', 'keterangan' => 'Kemdiktisaintek', 'poin' => 30, 'jenis' => JenisPoin::Prestasi, 'is_verified' => true]);
        PoinMahasiswa::create(['mahasiswa_id' => $m->id, 'poin_kategori_id' => $kategori->id, 'tahun_akademik_id' => $this->term->id, 'tanggal' => '2026-09-11', 'judul' => 'Belum diverifikasi', 'poin' => 30, 'jenis' => JenisPoin::Prestasi, 'is_verified' => false]);

        $hasil = $this->sync->sinkronkan('prestasi', $this->term);

        expect($hasil['terkirim'])->toBe(1)
            ->and(FeederSyncLog::entity('prestasi')->firstOrFail()->payload)->toBe([
                'id_mahasiswa' => 'mhs-1', 'id_jenis_prestasi' => '1', 'id_tingkat_prestasi' => '3',
                'nama' => 'Juara 1 Gemastik', 'tahun_prestasi' => 2026, 'penyelenggara' => 'Kemdiktisaintek',
            ]);
    });

    it('menolak prestasi yang jenis atau tingkatnya belum dipetakan, dan menyebut referensinya', function () {
        sukses('mahasiswa');
        $kategori = PoinKategori::create(['kode' => 'LOMBA', 'nama' => 'Juara lomba', 'jenis' => JenisPoin::Prestasi, 'tingkat' => 'nasional', 'poin' => 30]);
        PoinMahasiswa::create(['mahasiswa_id' => mhsDiFeeder()->id, 'poin_kategori_id' => $kategori->id, 'tahun_akademik_id' => $this->term->id, 'tanggal' => '2026-09-10', 'judul' => 'Juara', 'poin' => 30, 'jenis' => JenisPoin::Prestasi, 'is_verified' => true]);

        expect(fn () => $this->sync->sinkronkan('prestasi', $this->term))->toThrow(FeederException::class, 'tidak lolos validasi');

        expect(FeederValidationIssue::where('rule', 'jenis_prestasi_belum_dipetakan')->firstOrFail()->message)->toContain('GetJenisPrestasi');
    });

    it('mengirim transfer kredit yang disetujui dengan asal sebagai teks dan tujuan sebagai id Feeder', function () {
        sukses('riwayat_pendidikan');
        sukses('mata_kuliah');
        $m = mhsDiFeeder();
        $mk = mkDiFeeder('IF101');

        KonversiKredit::create([
            'mahasiswa_id' => $m->id, 'mata_kuliah_id' => $mk->id, 'jenis' => JenisKonversi::Transfer, 'status' => StatusKonversi::Disetujui,
            'asal_institusi' => 'Politeknik Negeri Bandung', 'asal_nama' => 'Pemrograman Dasar', 'asal_sks' => 3, 'asal_nilai' => 'A',
            'sks_diakui' => 3, 'nilai_huruf' => 'A', 'bobot' => 4.00, 'kunci_aktif' => KonversiKredit::kunci($m->id, $mk->id),
        ]);

        $hasil = $this->sync->sinkronkan('transfer_kredit', $this->term);

        expect($hasil['terkirim'])->toBe(1)
            ->and(FeederSyncLog::entity('transfer_kredit')->firstOrFail()->payload)->toMatchArray([
                'id_registrasi_mahasiswa' => 'reg-1', 'id_matkul' => 'mk-IF101', 'nama_mata_kuliah_asal' => 'Pemrograman Dasar',
                'sks_mata_kuliah_asal' => 3, 'nilai_huruf_asal' => 'A', 'nilai_huruf_diakui' => 'A', 'nilai_indeks_diakui' => '4.00',
                'sks_mata_kuliah_diakui' => 3, 'nama_perguruan_tinggi_asal' => 'Politeknik Negeri Bandung',
            ])
            ->and(KonversiKredit::firstOrFail()->feeder_id)->not->toBeNull();
    });

    it('mengirim konversi MBKM dari baris KRS beraktivitas yang nilainya final, dikunci pada anggota aktivitas', function () {
        sukses('anggota_mbkm');
        sukses('nilai');
        $m = mhsDiFeeder();
        $mk = mkDiFeeder('IF301');
        $kelas = KelasKuliah::factory()->create(['tahun_akademik_id' => $this->term->id, 'mata_kuliah_id' => $mk->id, 'prodi_id' => $this->prodi->id, 'sks' => 3, 'feeder_id' => 'kelas-1']);
        $aktivitas = AktivitasMahasiswa::create(['mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'jenis' => 'magang', 'judul' => 'Magang', 'tanggal_mulai' => '2026-09-01', 'sks_konversi' => 3, 'is_verified' => true, 'feeder_id' => 'akt-1', 'feeder_anggota_id' => 'anggota-1']);
        $krs = Krs::create(['mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'semester_ke' => 5, 'batas_sks' => 24, 'status' => 'disetujui']);
        $detail = KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => 3, 'aktivitas_mahasiswa_id' => $aktivitas->id, 'feeder_id' => 'krs-1']);
        Nilai::create(['krs_detail_id' => $detail->id, 'kelas_kuliah_id' => $kelas->id, 'mahasiswa_id' => $m->id, 'nilai_angka' => 85, 'nilai_huruf' => 'A', 'bobot' => 4.00, 'is_final' => true]);

        $hasil = $this->sync->sinkronkan('konversi_mbkm', $this->term);

        expect($hasil['terkirim'])->toBe(1)
            ->and(FeederSyncLog::entity('konversi_mbkm')->firstOrFail()->payload)->toBe([
                'id_matkul' => 'mk-IF301', 'id_anggota' => 'anggota-1', 'id_semester' => $this->term->kode,
                'sks_mata_kuliah' => 3, 'nilai_angka' => '85.00', 'nilai_huruf' => 'A', 'nilai_indeks' => '4.00',
            ])
            ->and($detail->fresh()->feeder_konversi_id)->not->toBeNull()
            ->and($detail->fresh()->feeder_id)->toBe('krs-1');
    });

    it('menolak konversi MBKM yang keanggotaannya belum terkirim', function () {
        sukses('anggota_mbkm');
        sukses('nilai');
        $m = mhsDiFeeder();
        $mk = mkDiFeeder('IF301');
        $kelas = KelasKuliah::factory()->create(['tahun_akademik_id' => $this->term->id, 'mata_kuliah_id' => $mk->id, 'prodi_id' => $this->prodi->id, 'sks' => 3, 'feeder_id' => 'kelas-1']);
        $aktivitas = AktivitasMahasiswa::create(['mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'jenis' => 'magang', 'judul' => 'Magang', 'tanggal_mulai' => '2026-09-01', 'sks_konversi' => 3, 'is_verified' => true]);
        $krs = Krs::create(['mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'semester_ke' => 5, 'batas_sks' => 24, 'status' => 'disetujui']);
        $detail = KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => 3, 'aktivitas_mahasiswa_id' => $aktivitas->id]);
        Nilai::create(['krs_detail_id' => $detail->id, 'kelas_kuliah_id' => $kelas->id, 'mahasiswa_id' => $m->id, 'nilai_angka' => 85, 'nilai_huruf' => 'A', 'bobot' => 4.00, 'is_final' => true]);

        expect(fn () => $this->sync->sinkronkan('konversi_mbkm', $this->term))->toThrow(FeederException::class, 'tidak lolos validasi');

        expect(FeederValidationIssue::where('rule', 'anggota_belum_sinkron')->exists())->toBeTrue();
    });
});

describe('jenis pendaftaran (#25)', function () {
    it('mengirim peserta didik baru seperti sebelumnya, tanpa kolom asal', function () {
        sukses('mahasiswa');
        mhsDiFeeder(['feeder_registrasi_id' => null, 'jenis_daftar' => null]);

        $this->sync->sinkronkan('riwayat_pendidikan', $this->term);
        $payload = FeederSyncLog::entity('riwayat_pendidikan')->firstOrFail()->payload;

        expect($payload['id_jenis_daftar'])->toBe('1')
            ->and($payload)->not->toHaveKey('sks_diakui')
            ->and($payload)->not->toHaveKey('nama_perguruan_tinggi_asal');
    });

    it('mengirim mahasiswa pindahan dengan SKS diakui dan asalnya, begitu jenisnya dipetakan', function () {
        sukses('mahasiswa');
        FeederMapping::create(['group' => 'jenis_daftar', 'local_value' => 'pindahan', 'feeder_code' => '2']);
        mhsDiFeeder(['feeder_registrasi_id' => null, 'jenis_daftar' => JenisDaftar::Pindahan, 'sks_diakui' => 48, 'pt_asal' => 'Universitas Padjadjaran', 'prodi_asal' => 'Teknik Informatika']);

        $this->sync->sinkronkan('riwayat_pendidikan', $this->term);
        $payload = FeederSyncLog::entity('riwayat_pendidikan')->firstOrFail()->payload;

        expect($payload)->toMatchArray([
            'id_jenis_daftar' => '2', 'sks_diakui' => 48,
            'nama_perguruan_tinggi_asal' => 'Universitas Padjadjaran', 'nama_program_studi_asal' => 'Teknik Informatika',
        ]);
    });

    it('menolak pindahan tanpa SKS diakui atau asal — Feeder menolaknya, dan portal PIN menolaknya lagi', function () {
        sukses('mahasiswa');
        FeederMapping::create(['group' => 'jenis_daftar', 'local_value' => 'rpl_transfer', 'feeder_code' => '3']);
        mhsDiFeeder(['feeder_registrasi_id' => null, 'jenis_daftar' => JenisDaftar::RplTransfer]);

        expect(fn () => $this->sync->sinkronkan('riwayat_pendidikan', $this->term))->toThrow(FeederException::class, 'tidak lolos validasi');

        expect(FeederValidationIssue::whereIn('rule', ['sks_diakui_kosong', 'asal_kosong'])->count())->toBe(2);
    });

    it('menolak jenis pendaftaran yang belum dipetakan, dan menyebut referensinya', function () {
        sukses('mahasiswa');
        mhsDiFeeder(['feeder_registrasi_id' => null, 'jenis_daftar' => JenisDaftar::FastTrack]);

        $hasil = $this->validator->periksa('riwayat_pendidikan', $this->term->kode, app(FeederSyncService::MAPPERS['riwayat_pendidikan']));

        expect($hasil['error'])->toBe(1)
            ->and(FeederValidationIssue::where('rule', 'jenis_daftar_belum_dipetakan')->firstOrFail()->message)->toContain('GetJenisPendaftaran');
    });
});

describe('layar monitor', function () {
    it('menampilkan keenam entitas baru dengan referensi di urutan pertama', function () {
        $this->seed(RolePermissionSeeder::class);
        TahunAkademik::query()->update(['tanggal_mulai' => now()->subWeeks(8), 'tanggal_selesai' => now()->addWeeks(8)]);
        $staf = Staff::factory()->create();
        $staf->assignRole('super-admin');

        $html = $this->actingAs($staf, 'staff')->get('/admin/feeder')->assertOk()->getContent();

        foreach (['Kurikulum', 'Mata Kuliah dalam Kurikulum', 'Prestasi Mahasiswa', 'Transfer Kredit', 'Konversi Kredit MBKM'] as $label) {
            expect($html)->toContain($label);
        }

        expect(strpos($html, 'InsertKurikulum'))->toBeLessThan(strpos($html, 'InsertBiodataMahasiswa'));
    });
});
