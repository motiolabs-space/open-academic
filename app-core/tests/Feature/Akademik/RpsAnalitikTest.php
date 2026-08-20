<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\GradeLetter;
use App\Enums\SemesterType;
use App\Enums\StatusRps;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KomponenNilai;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\NilaiKomponen;
use App\Models\Akademik\PertemuanKelas;
use App\Models\Akademik\Presensi;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\ProdiCpl;
use App\Models\Akademik\Rps;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Services\Akademik\AnalitikService;
use App\Services\Akademik\JurnalService;
use App\Services\Akademik\PresensiService;
use App\Services\Akademik\RpsService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create();
    $this->dosen = Dosen::factory()->create(['is_active' => true]);

    $this->mk = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id, 'sks' => 3]);

    $this->kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => $this->term->id,
        'prodi_id' => $this->prodi->id,
        'mata_kuliah_id' => $this->mk->id,
        'sks' => 3,
    ]);

    $this->kelas->dosen()->attach($this->dosen->id, ['peran' => 'pengampu', 'porsi_sks' => 3]);

    $this->cplA = ProdiCpl::create([
        'prodi_id' => $this->prodi->id,
        'kode' => 'CPL-01',
        'kategori' => 'pengetahuan',
        'deskripsi' => 'Menguasai konsep dasar.',
    ]);

    $this->cplB = ProdiCpl::create([
        'prodi_id' => $this->prodi->id,
        'kode' => 'CPL-02',
        'kategori' => 'keterampilan_khusus',
        'deskripsi' => 'Mampu merancang solusi.',
    ]);

    $this->rpsService = app(RpsService::class);
    $this->jurnal = app(JurnalService::class);
    $this->analitik = app(AnalitikService::class);
});

/** Sixteen weeks whose assessment weights total 100. */
function pertemuanLengkap(int $jumlah = 16): array
{
    return collect(range(1, $jumlah))
        ->map(fn (int $i): array => [
            'pertemuan_ke' => $i,
            'kemampuan_akhir' => 'Kemampuan akhir pekan '.$i,
            'bahan_kajian' => 'Bahan pekan '.$i,
            'metode' => 'ceramah',
            // 4 × 10 + 12 × 5 = 100
            'bobot' => $i <= 4 ? 10 : 5,
        ])
        ->all();
}

function rpsSiapTerbit(): Rps
{
    $rps = test()->rpsService->mulai(test()->mk, test()->term, test()->dosen);

    test()->rpsService->simpanPertemuan($rps, pertemuanLengkap());
    test()->rpsService->simpanCpl($rps, [
        ['prodi_cpl_id' => test()->cplA->id, 'rumusan' => 'CPMK 1'],
        ['prodi_cpl_id' => test()->cplB->id],
    ]);

    return $rps->fresh(['pertemuan', 'cpl']);
}

/** Enrols a student and returns their krs_detail. */
function pesertaKelasRps(?string $nama = null): KrsDetail
{
    $mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => test()->prodi->id,
        'nama' => $nama ?? 'Peserta '.uniqid(),
    ]);

    $krs = Krs::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'semester_ke' => 1,
        'status' => 'disetujui',
        'total_sks' => 3,
        'batas_sks' => 24,
    ]);

    return KrsDetail::create([
        'krs_id' => $krs->id,
        'kelas_kuliah_id' => test()->kelas->id,
        'sks' => 3,
    ]);
}

describe('RPS: rencana yang mengunci artinya', function () {
    it('menolak terbit sebelum bobotnya berjumlah 100', function () {
        $rps = $this->rpsService->mulai($this->mk, $this->term, $this->dosen);

        $this->rpsService->simpanPertemuan($rps, [
            ['pertemuan_ke' => 1, 'kemampuan_akhir' => 'A', 'bobot' => 40],
        ]);
        $this->rpsService->simpanCpl($rps, [['prodi_cpl_id' => $this->cplA->id]]);

        expect(fn () => $this->rpsService->terbitkan($rps->fresh(['pertemuan', 'cpl'])))
            ->toThrow(AturanAkademikException::class, 'berjumlah 40%');
    });

    it('menolak terbit tanpa satu pun CPL yang dibebankan', function () {
        // Mata kuliah yang tidak menjawab CPL apa pun tidak dapat dilaporkan
        // ketercapaiannya — dan itu justru pertanyaan yang dibawa asesor.
        $rps = $this->rpsService->mulai($this->mk, $this->term, $this->dosen);
        $this->rpsService->simpanPertemuan($rps, pertemuanLengkap());

        expect(fn () => $this->rpsService->terbitkan($rps->fresh(['pertemuan', 'cpl'])))
            ->toThrow(AturanAkademikException::class, 'Belum ada CPL');
    });

    it('menyebut pertemuan mana yang kosong, bukan sekadar "belum lengkap"', function () {
        $rps = $this->rpsService->mulai($this->mk, $this->term, $this->dosen);
        $this->rpsService->simpanPertemuan($rps, [
            ['pertemuan_ke' => 1, 'kemampuan_akhir' => 'Ada', 'bobot' => 100],
        ]);

        expect(implode(' ', $this->rpsService->kekurangan($rps->fresh('pertemuan'))))
            ->toContain('Baru 1 dari 16 pertemuan');
    });

    it('menerbitkan dan menandainya berlaku', function () {
        $rps = $this->rpsService->terbitkan(rpsSiapTerbit(), $this->dosen);

        expect($rps->status)->toBe(StatusRps::Berlaku)
            ->and($rps->kunci_aktif)->toBe($this->mk->id.':'.$this->term->id)
            ->and(Rps::untuk($this->mk->id, $this->term->id)?->id)->toBe($rps->id);
    });

    it('hanya menyisakan satu RPS berlaku per mata kuliah per semester', function () {
        $pertama = $this->rpsService->terbitkan(rpsSiapTerbit(), $this->dosen);

        $revisi = $this->rpsService->mulai($this->mk, $this->term, $this->dosen);
        $kedua = $this->rpsService->terbitkan($revisi->fresh(['pertemuan', 'cpl']), $this->dosen);

        expect($pertama->fresh()->status)->toBe(StatusRps::Diarsipkan)
            ->and($kedua->versi)->toBe(2)
            ->and(Rps::aktif()->where('mata_kuliah_id', $this->mk->id)->count())->toBe(1);
    });

    it('menyalin isi versi berlaku ke revisi, bukan memulai dari kosong', function () {
        // Mengetik ulang enam belas pekan untuk memperbaiki satu di antaranya
        // adalah cara sebuah kampus berhenti merevisi apa pun.
        $this->rpsService->terbitkan(rpsSiapTerbit(), $this->dosen);

        $revisi = $this->rpsService->mulai($this->mk, $this->term, $this->dosen);

        expect($revisi->pertemuan)->toHaveCount(16)
            ->and($revisi->cpl)->toHaveCount(2);
    });

    it('menolak penyuntingan RPS yang sudah berlaku', function () {
        /*
         * Aturan yang membuat angka penguasaan bermakna. Nilai yang dicatat pada
         * pekan keempat terhadap CPL-01 harus tetap milik CPL-01 pada pekan
         * kedua belas.
         */
        $rps = $this->rpsService->terbitkan(rpsSiapTerbit(), $this->dosen);

        expect(fn () => $this->rpsService->simpanPertemuan($rps, pertemuanLengkap()))
            ->toThrow(AturanAkademikException::class, 'tidak dapat disunting');
    });

    it('menolak CPL milik program studi lain', function () {
        $lain = ProdiCpl::create([
            'prodi_id' => Prodi::factory()->create()->id,
            'kode' => 'CPL-01',
            'kategori' => 'pengetahuan',
            'deskripsi' => 'Milik prodi lain.',
        ]);

        $rps = $this->rpsService->mulai($this->mk, $this->term, $this->dosen);

        expect(fn () => $this->rpsService->simpanCpl($rps, [['prodi_cpl_id' => $lain->id]]))
            ->toThrow(AturanAkademikException::class, 'bukan milik program studi');
    });

    it('menyebutkan CPL yang dibebankan tetapi tidak diukur apa pun', function () {
        // Celah yang persis dicari saat visitasi: CPL yang seolah diajarkan
        // tetapi tidak pernah dapat dilaporkan.
        $rps = $this->rpsService->terbitkan(rpsSiapTerbit(), $this->dosen);

        $komponen = KomponenNilai::create([
            'kelas_kuliah_id' => $this->kelas->id,
            'nama' => 'UTS',
            'bobot' => 100,
        ]);
        $komponen->cpl()->attach($this->cplA->id, ['porsi' => 100]);

        $tanpa = $this->rpsService->cplTanpaPengukur($rps, $this->kelas);

        expect($tanpa->pluck('kode')->all())->toBe(['CPL-02']);
    });
});

describe('jurnal perkuliahan', function () {
    beforeEach(function () {
        $this->pertemuan = PertemuanKelas::create([
            'kelas_kuliah_id' => $this->kelas->id,
            'pertemuan_ke' => 1,
            'tanggal' => now(),
        ]);
    });

    it('membekukan cacah kehadiran saat jurnal diisi', function () {
        /*
         * Jurnal adalah pernyataan tentang satu sore. Menghitungnya ulang
         * berbulan-bulan kemudian — setelah koreksi presensi, atau setelah
         * seorang mahasiswa mengundurkan diri — akan mengubah catatan yang sudah
         * ditandatangani.
         */
        $detailA = pesertaKelasRps();
        $detailB = pesertaKelasRps();

        foreach ([[$detailA, AttendanceStatus::Hadir], [$detailB, AttendanceStatus::Alpa]] as [$d, $status]) {
            Presensi::create([
                'pertemuan_kelas_id' => $this->pertemuan->id,
                'mahasiswa_id' => $d->krs->mahasiswa_id,
                'status' => $status,
            ]);
        }

        $this->jurnal->isi($this->pertemuan, $this->dosen, 'Pengantar dan kontrak kuliah.');

        $this->pertemuan->refresh();

        expect($this->pertemuan->jumlah_hadir)->toBe(1)
            ->and($this->pertemuan->jumlah_peserta)->toBe(2);

        // Presensi dikoreksi setelahnya; cuplikannya tidak ikut berubah.
        Presensi::where('mahasiswa_id', $detailB->krs->mahasiswa_id)
            ->update(['status' => AttendanceStatus::Hadir->value]);

        expect($this->pertemuan->fresh()->jumlah_hadir)->toBe(1);
    });

    it('menandai pertemuan terlaksana saat jurnal diisi', function () {
        expect($this->pertemuan->fresh()->is_terlaksana)->toBeFalse();

        $this->jurnal->isi($this->pertemuan, $this->dosen, 'Materi pekan pertama.');

        expect($this->pertemuan->fresh()->is_terlaksana)->toBeTrue();
    });

    it('menolak dosen yang tidak mengampu kelas itu', function () {
        expect(fn () => $this->jurnal->isi($this->pertemuan, Dosen::factory()->create(), 'Materi.'))
            ->toThrow(AturanAkademikException::class, 'tidak mengampu');
    });

    it('mewajibkan materi diisi', function () {
        expect(fn () => $this->jurnal->isi($this->pertemuan, $this->dosen, ''))
            ->toThrow(AturanAkademikException::class, 'wajib diisi');
    });

    it('membedakan pertemuan terlaksana dari pertemuan berjurnal', function () {
        /*
         * Kelas dengan empat belas terlaksana dan empat berjurnal bukan mengajar
         * lebih sedikit — ia mendokumentasikan lebih sedikit. Melaporkan satu
         * angka saja menyembunyikan mana dari dua masalah itu yang dipunyai
         * kampusnya.
         */
        PertemuanKelas::create([
            'kelas_kuliah_id' => $this->kelas->id,
            'pertemuan_ke' => 2,
            'tanggal' => now(),
            'is_terlaksana' => true,
        ]);

        $this->jurnal->isi($this->pertemuan, $this->dosen, 'Materi pekan pertama.');

        $hasil = $this->jurnal->keterlaksanaan($this->kelas->fresh());

        expect($hasil['terlaksana'])->toBe(2)
            ->and($hasil['berjurnal'])->toBe(1);
    });

    it('tidak menuduh kelas tertinggal ketika RPS-nya memang belum ada', function () {
        $hasil = $this->jurnal->keterlaksanaan($this->kelas);

        expect($hasil['ada_rps'])->toBeFalse()
            ->and($hasil['pertemuan_belum_tersampaikan'])->toHaveCount(0);
    });
});

describe('analitik penguasaan CPL', function () {
    it('tidak melaporkan nol ketika komponennya belum dipetakan', function () {
        /*
         * Nol di sini akan terbaca "mahasiswa tidak menguasai apa pun", padahal
         * artinya "belum ada yang menyatakan ujian ini mengukur apa".
         */
        $detail = pesertaKelasRps();

        $komponen = KomponenNilai::create([
            'kelas_kuliah_id' => $this->kelas->id, 'nama' => 'UTS', 'bobot' => 100,
        ]);

        NilaiKomponen::create([
            'komponen_nilai_id' => $komponen->id,
            'krs_detail_id' => $detail->id,
            'nilai' => 80,
        ]);

        expect($this->analitik->penguasaanKelas($this->kelas)['terpetakan'])->toBeFalse();
    });

    it('menimbang bobot komponen dikali porsi CPL-nya', function () {
        /*
         * UTS berbobot 30 yang 60%-nya mengukur CPL-01 menyumbang 18 satuan
         * bobot — bukan 30, dan bukan 1.
         *
         *   (18 × 80 + 40 × 60) / (18 + 40) = 3.840 / 58 = 66,21
         */
        $detail = pesertaKelasRps();

        $uts = KomponenNilai::create([
            'kelas_kuliah_id' => $this->kelas->id, 'nama' => 'UTS', 'bobot' => 30,
        ]);
        $uas = KomponenNilai::create([
            'kelas_kuliah_id' => $this->kelas->id, 'nama' => 'UAS', 'bobot' => 40,
        ]);

        $uts->cpl()->attach($this->cplA->id, ['porsi' => 60]);
        $uas->cpl()->attach($this->cplA->id, ['porsi' => 100]);

        NilaiKomponen::create(['komponen_nilai_id' => $uts->id, 'krs_detail_id' => $detail->id, 'nilai' => 80]);
        NilaiKomponen::create(['komponen_nilai_id' => $uas->id, 'krs_detail_id' => $detail->id, 'nilai' => 60]);

        $hasil = $this->analitik->penguasaanKelas($this->kelas);

        expect($hasil['cpl'][0]['nilai'])->toBe(66.21);
    });

    it('membagi satu komponen ke beberapa CPL tanpa menggandakan bobotnya', function () {
        // Satu UTS lazim mengukur dua CPL. Memaksanya memilih satu akan
        // membuat CPL yang dibuang tampak tidak pernah diukur.
        $detail = pesertaKelasRps();

        $uts = KomponenNilai::create([
            'kelas_kuliah_id' => $this->kelas->id, 'nama' => 'UTS', 'bobot' => 100,
        ]);

        $uts->cpl()->attach($this->cplA->id, ['porsi' => 70]);
        $uts->cpl()->attach($this->cplB->id, ['porsi' => 30]);

        NilaiKomponen::create(['komponen_nilai_id' => $uts->id, 'krs_detail_id' => $detail->id, 'nilai' => 75]);

        $hasil = $this->analitik->penguasaanKelas($this->kelas);

        expect($hasil['cpl'])->toHaveCount(2)
            ->and($hasil['cpl']->pluck('nilai')->all())->toBe([75.0, 75.0]);
    });

    it('menyertakan ambang bersama angkanya', function () {
        // Supaya pembaca dapat berselisih dengan ambangnya, bukan dengan
        // mahasiswanya.
        config(['academic.cpl.ambang_penguasaan' => 70]);

        $detail = pesertaKelasRps();
        $k = KomponenNilai::create(['kelas_kuliah_id' => $this->kelas->id, 'nama' => 'UTS', 'bobot' => 100]);
        $k->cpl()->attach($this->cplA->id, ['porsi' => 100]);
        NilaiKomponen::create(['komponen_nilai_id' => $k->id, 'krs_detail_id' => $detail->id, 'nilai' => 68]);

        $hasil = $this->analitik->penguasaanKelas($this->kelas);

        expect($hasil['ambang'])->toBe(70.0)
            ->and($hasil['cpl'][0]['tercapai'])->toBeFalse();
    });

    it('mengurutkan CPL terlemah lebih dulu pada tampilan mahasiswa', function () {
        // "Lemah pada CPL-02 di mana pun ia diukur" adalah yang dapat dibawa
        // dosen wali ke pertemuan; angka 68 tidak.
        $detail = pesertaKelasRps();
        $mahasiswa = $detail->krs->mahasiswa;

        foreach ([[$this->cplA, 85], [$this->cplB, 55]] as $i => [$cpl, $nilai]) {
            $k = KomponenNilai::create([
                'kelas_kuliah_id' => $this->kelas->id, 'nama' => 'Komponen '.$i, 'bobot' => 50,
            ]);
            $k->cpl()->attach($cpl->id, ['porsi' => 100]);
            NilaiKomponen::create([
                'komponen_nilai_id' => $k->id, 'krs_detail_id' => $detail->id, 'nilai' => $nilai,
            ]);
        }

        $hasil = $this->analitik->penguasaanMahasiswa($mahasiswa);

        expect($hasil['cpl'][0]['cpl']->kode)->toBe('CPL-02')
            ->and($hasil['cpl'][0]['nilai'])->toBe(55.0);
    });
});

describe('analitik kehadiran & penilaian', function () {
    it('memakai perhitungan yang sama dengan PresensiService', function () {
        // Dua implementasi "persentase kehadiran" adalah cara sebuah dasbor dan
        // sebuah lembar nilai berselisih tentang mahasiswa yang sama.
        $detail = pesertaKelasRps();

        foreach ([AttendanceStatus::Hadir, AttendanceStatus::Alpa] as $i => $status) {
            $p = PertemuanKelas::create([
                'kelas_kuliah_id' => $this->kelas->id,
                'pertemuan_ke' => $i + 1,
                'tanggal' => now(),
                'is_terlaksana' => true,
            ]);

            Presensi::create([
                'pertemuan_kelas_id' => $p->id,
                'mahasiswa_id' => $detail->krs->mahasiswa_id,
                'status' => $status,
            ]);
        }

        $hasil = $this->analitik->kehadiran($this->kelas);
        $langsung = app(PresensiService::class)
            ->persenKehadiran($detail->krs->mahasiswa, $this->kelas);

        expect($hasil['rerata'])->toBe(round((float) $langsung, 1));
    });

    it('menamai komponen terlemah, bukan sekadar rerata kelas', function () {
        // "Kelasnya jelek" tidak dapat ditindaklanjuti; "praktikumnya jelek"
        // dapat.
        $detail = pesertaKelasRps();

        foreach ([['UTS', 80], ['Praktikum', 45]] as [$nama, $nilai]) {
            $k = KomponenNilai::create([
                'kelas_kuliah_id' => $this->kelas->id, 'nama' => $nama, 'bobot' => 50,
            ]);
            NilaiKomponen::create([
                'komponen_nilai_id' => $k->id, 'krs_detail_id' => $detail->id, 'nilai' => $nilai,
            ]);
        }

        expect($this->analitik->penilaian($this->kelas)['komponen_terlemah']['nama'])->toBe('Praktikum');
    });

    it('memberi alasan tertulis, bukan skor risiko', function () {
        /*
         * Indeks risiko berperingkat mengundang pembacanya memperlakukan
         * kombinasi aritmetik dua angka tak sejenis sebagai ramalan. Daftar
         * beralasan mengundangnya memeriksa.
         */
        $detail = pesertaKelasRps('Mahasiswa Rawan');

        foreach (range(1, 4) as $i) {
            $p = PertemuanKelas::create([
                'kelas_kuliah_id' => $this->kelas->id,
                'pertemuan_ke' => $i,
                'tanggal' => now(),
                'is_terlaksana' => true,
            ]);

            Presensi::create([
                'pertemuan_kelas_id' => $p->id,
                'mahasiswa_id' => $detail->krs->mahasiswa_id,
                'status' => $i === 1 ? AttendanceStatus::Hadir : AttendanceStatus::Alpa,
            ]);
        }

        $k = KomponenNilai::create(['kelas_kuliah_id' => $this->kelas->id, 'nama' => 'UTS', 'bobot' => 100]);
        NilaiKomponen::create(['komponen_nilai_id' => $k->id, 'krs_detail_id' => $detail->id, 'nilai' => 40]);

        $perhatian = $this->analitik->perluPerhatian($this->kelas);

        expect($perhatian)->toHaveCount(1)
            ->and($perhatian[0]['mahasiswa']->nama)->toBe('Mahasiswa Rawan')
            ->and($perhatian[0]['alasan'])->toHaveCount(2)
            ->and(implode(' ', $perhatian[0]['alasan']))
            ->toContain('di bawah ambang')
            ->toContain('komponen yang sudah dinilai');
    });

    it('tidak menyebut siapa pun rawan ketika tidak ada aturan yang terlanggar', function () {
        $detail = pesertaKelasRps();

        $p = PertemuanKelas::create([
            'kelas_kuliah_id' => $this->kelas->id,
            'pertemuan_ke' => 1,
            'tanggal' => now(),
            'is_terlaksana' => true,
        ]);

        Presensi::create([
            'pertemuan_kelas_id' => $p->id,
            'mahasiswa_id' => $detail->krs->mahasiswa_id,
            'status' => AttendanceStatus::Hadir,
        ]);

        $k = KomponenNilai::create(['kelas_kuliah_id' => $this->kelas->id, 'nama' => 'UTS', 'bobot' => 100]);
        NilaiKomponen::create(['komponen_nilai_id' => $k->id, 'krs_detail_id' => $detail->id, 'nilai' => 85]);

        expect($this->analitik->perluPerhatian($this->kelas))->toHaveCount(0);
    });
});

describe('analitik nilai final', function () {
    /*
     * Dua cacat sekaligus, dan yang kedua lebih berbahaya.
     *
     * `AnalitikService::penilaian()` dulu menyaring `whereNotNull('nilai_akhir')`
     * — kolom milik `tugas_akhir`, bukan `nilai`. Di MySQL layar Analitik Kelas
     * per-kelas membalas 500. Di SQLite ia TIDAK galat sama sekali: identifier
     * berkutip yang tidak dikenal diperlakukan sebagai string literal, dan
     * string tidak pernah NULL — jadi penyaringnya selalu benar dan SELURUH
     * nilai terhitung final.
     *
     * Karena itu tes ini memaku maknanya, bukan status HTTP-nya: satu-satunya
     * hal yang gagal di SQLite pada kode lama adalah angkanya.
     */
    it('hanya menghitung nilai yang sudah final', function () {
        $a = pesertaKelasRps('Final Satu');
        $b = pesertaKelasRps('Final Dua');
        $c = pesertaKelasRps('Belum Final');

        foreach ([[$a, 80, true], [$b, 90, true], [$c, 40, false]] as [$detail, $angka, $final]) {
            Nilai::create([
                'krs_detail_id' => $detail->id,
                'kelas_kuliah_id' => $this->kelas->id,
                'mahasiswa_id' => $detail->krs->mahasiswa_id,
                'nilai_angka' => $angka,
                'nilai_huruf' => GradeLetter::B,
                'bobot' => 3,
                'is_final' => $final,
            ]);
        }

        $hasil = $this->analitik->penilaian($this->kelas);

        // 3 berarti yang belum final ikut terhitung — persis gejala kode lama.
        expect($hasil['sudah_final'])->toBe(2)
            // Rerata 70 berarti nilai 40 yang belum final ikut menyeret.
            ->and($hasil['rerata_akhir'])->toBe(85.0);
    });
});
