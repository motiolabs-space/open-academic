<?php

declare(strict_types=1);

use App\Enums\HasilUjian;
use App\Enums\JenisUjian;
use App\Enums\PeranPembimbing;
use App\Enums\PeranPenguji;
use App\Enums\StudentStatus;
use App\Enums\TugasAkhirStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Gedung;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\Ruang;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\TugasAkhir\TugasAkhir;
use App\Services\TugasAkhir\BimbinganService;
use App\Services\TugasAkhir\TugasAkhirService;
use App\Services\TugasAkhir\UjianService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->berjalan()->create(['is_active' => true]);
    $this->prodi = Prodi::factory()->create(['sks_lulus' => 144]);

    $gedung = Gedung::create(['kode' => 'A', 'nama' => 'Gedung A']);
    $this->ruang = Ruang::create([
        'gedung_id' => $gedung->id, 'kode' => 'A-101', 'nama' => 'Ruang 101',
        'kapasitas' => 40, 'jenis' => 'kelas', 'is_active' => true,
    ]);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('baak');

    $this->mahasiswa = mahasiswaSiapTa();
    $this->dosen = Dosen::factory()->create(['is_active' => true]);

    $this->ta = app(TugasAkhirService::class);
    $this->bimbingan = app(BimbinganService::class);
    $this->ujian = app(UjianService::class);
});

/** Mahasiswa aktif dengan SKS yang cukup untuk mengajukan tugas akhir. */
function mahasiswaSiapTa(int $sks = 120): Mahasiswa
{
    $mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => test()->prodi->id,
        'status' => StudentStatus::Aktif,
    ]);

    StatusMahasiswa::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'semester_ke' => 7,
        'status' => StudentStatus::Aktif,
        'sks_semester' => 20,
        'sks_kumulatif' => $sks,
        'ips' => 3.5,
        'ipk' => 3.5,
    ]);

    return $mahasiswa->fresh();
}

/** Tugas akhir yang sudah disetujui dan punya pembimbing. */
function taDibimbing(?Mahasiswa $mahasiswa = null, ?Dosen $dosen = null): TugasAkhir
{
    $mahasiswa ??= test()->mahasiswa;
    $dosen ??= test()->dosen;

    $ta = test()->ta->ajukan($mahasiswa, test()->term, 'Judul Uji');
    test()->ta->setujuiJudul($ta, test()->staf);
    test()->ta->tetapkanPembimbing($ta->fresh(), $dosen, PeranPembimbing::Utama);

    return $ta->fresh();
}

/** Mengisi log bimbingan yang sudah disetujui sebanyak $jumlah. */
function isiBimbingan(TugasAkhir $ta, Dosen $dosen, int $jumlah): void
{
    for ($i = 0; $i < $jumlah; $i++) {
        $log = test()->bimbingan->catat(
            $ta->fresh(),
            $dosen,
            now()->subDays($jumlah - $i)->toDateString(),
            "Bimbingan ke-{$i}",
        );

        test()->bimbingan->setujui($log, $dosen);
    }
}

describe('satu tugas akhir aktif per mahasiswa', function () {
    it('menolak judul kedua selagi yang pertama berjalan', function () {
        $this->ta->ajukan($this->mahasiswa, $this->term, 'Judul Pertama');

        expect(fn () => $this->ta->ajukan($this->mahasiswa, $this->term, 'Judul Kedua'))
            ->toThrow(AturanAkademikException::class, 'sudah memiliki tugas akhir yang berjalan');
    });

    it('membebaskan slot begitu judul ditolak', function () {
        $pertama = $this->ta->ajukan($this->mahasiswa, $this->term, 'Judul Pertama');
        $this->ta->tolakJudul($pertama, $this->staf, 'Topik sudah pernah diteliti tahun lalu.');

        $kedua = $this->ta->ajukan($this->mahasiswa->fresh(), $this->term, 'Judul Kedua');

        expect($kedua->status)->toBe(TugasAkhirStatus::Diajukan)
            ->and($pertama->fresh()->mahasiswa_aktif_id)->toBeNull()
            ->and(TugasAkhir::where('mahasiswa_id', $this->mahasiswa->id)->count())->toBe(2);
    });

    it('dijaga basis data, bukan hanya oleh service', function () {
        // Penjaga di service kalah oleh dua permintaan bersamaan. Yang benar-benar
        // menahan adalah indeks unik pada mahasiswa_aktif_id — ditulis langsung di
        // sini, melewati seluruh lapisan aplikasi.
        $this->ta->ajukan($this->mahasiswa, $this->term, 'Judul Pertama');

        expect(fn () => TugasAkhir::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'mahasiswa_aktif_id' => $this->mahasiswa->id,
            'judul' => 'Judul Tembus',
            'status' => TugasAkhirStatus::Diajukan,
            'tanggal_pengajuan' => now()->toDateString(),
        ]))->toThrow(QueryException::class);
    });

    it('menerjemahkan pelanggaran indeks menjadi kalimat yang terbaca', function () {
        /*
         * Menutup celah pada tes di atas: yang itu menulis langsung ke tabel,
         * sehingga membuktikan indeksnya bekerja tetapi tidak membuktikan
         * service menerjemahkan galatnya.
         *
         * Versi pertama penerjemah itu mencocokkan kode SQLSTATE dengan tangan
         * dan tidak pernah cocok — permintaan yang kalah menerima galat driver
         * mentah, dan tidak ada tes yang menyadarinya.
         *
         * Baris pertama ditanam tanpa melewati service, sehingga kunci di
         * dalam ajukan() tidak melihatnya dan alur penerjemahan itulah yang
         * benar-benar dijalani.
         */
        TugasAkhir::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'mahasiswa_aktif_id' => $this->mahasiswa->id,
            'judul' => 'Judul Yang Sudah Ada',
            'status' => TugasAkhirStatus::Diajukan,
            'tanggal_pengajuan' => now()->toDateString(),
        ]);

        expect(fn () => $this->ta->ajukan($this->mahasiswa->fresh(), $this->term, 'Judul Baru'))
            ->toThrow(AturanAkademikException::class, 'sudah memiliki tugas akhir yang berjalan');
    });

    it('mengizinkan riwayat menumpuk selama tidak ada yang berjalan', function () {
        $a = $this->ta->ajukan($this->mahasiswa, $this->term, 'Judul A');
        $this->ta->tolakJudul($a, $this->staf, 'Terlalu luas.');

        $b = $this->ta->ajukan($this->mahasiswa->fresh(), $this->term, 'Judul B');
        $this->ta->tolakJudul($b, $this->staf, 'Masih terlalu luas.');

        expect(fn () => $this->ta->ajukan($this->mahasiswa->fresh(), $this->term, 'Judul C'))
            ->not->toThrow(AturanAkademikException::class);
    });
});

describe('syarat pengajuan', function () {
    it('menolak mahasiswa yang SKS-nya belum cukup', function () {
        $baru = mahasiswaSiapTa(sks: 40);

        expect(fn () => $this->ta->ajukan($baru, $this->term, 'Judul'))
            ->toThrow(AturanAkademikException::class, 'memerlukan minimal 110 SKS');
    });

    it('menolak mahasiswa yang sedang cuti', function () {
        $this->mahasiswa->update(['status' => StudentStatus::Cuti]);

        expect(fn () => $this->ta->ajukan($this->mahasiswa->fresh(), $this->term, 'Judul'))
            ->toThrow(AturanAkademikException::class, 'berstatus aktif');
    });

    it('mewajibkan alasan pada penolakan judul', function () {
        $ta = $this->ta->ajukan($this->mahasiswa, $this->term, 'Judul');

        expect(fn () => $this->ta->tolakJudul($ta, $this->staf, ''))
            ->toThrow(AturanAkademikException::class, 'wajib disertai alasan');
    });
});

describe('kuota pembimbing', function () {
    it('menolak dosen yang sudah mencapai batas', function () {
        config(['academic.tugas_akhir.kuota_pembimbing' => 2]);

        foreach (range(1, 2) as $i) {
            taDibimbing(mahasiswaSiapTa(), $this->dosen);
        }

        $ta = $this->ta->ajukan(mahasiswaSiapTa(), $this->term, 'Judul Ketiga');
        $this->ta->setujuiJudul($ta, $this->staf);

        expect(fn () => $this->ta->tetapkanPembimbing($ta->fresh(), $this->dosen))
            ->toThrow(AturanAkademikException::class, 'sama dengan batas 2');
    });

    it('tidak menghitung tugas akhir yang sudah tidak berjalan', function () {
        // Kuota karier akan menolak setiap dosen dalam beberapa tahun. Yang
        // dihitung hanya bimbingan yang sedang berjalan.
        config(['academic.tugas_akhir.kuota_pembimbing' => 1]);

        $lama = taDibimbing(mahasiswaSiapTa(), $this->dosen);
        $this->ta->batalkan($lama, 'Mahasiswa mengundurkan diri.');

        $ta = $this->ta->ajukan(mahasiswaSiapTa(), $this->term, 'Judul Baru');
        $this->ta->setujuiJudul($ta, $this->staf);

        expect(fn () => $this->ta->tetapkanPembimbing($ta->fresh(), $this->dosen))
            ->not->toThrow(AturanAkademikException::class);
    });

    it('menolak dosen nonaktif', function () {
        $ta = $this->ta->ajukan($this->mahasiswa, $this->term, 'Judul');
        $this->ta->setujuiJudul($ta, $this->staf);
        $this->dosen->update(['is_active' => false]);

        expect(fn () => $this->ta->tetapkanPembimbing($ta->fresh(), $this->dosen->fresh()))
            ->toThrow(AturanAkademikException::class, 'nonaktif');
    });

    it('kembali ke menunggu saat pembimbing terakhir dilepas', function () {
        // Bukan tetap "dalam bimbingan" tanpa ada yang membimbing — justru
        // keadaan itulah yang modul ini ada untuk membuatnya terlihat.
        $ta = taDibimbing();

        expect($ta->status)->toBe(TugasAkhirStatus::Dibimbing);

        $this->ta->lepasPembimbing($ta->pembimbing()->first());

        expect($ta->fresh()->status)->toBe(TugasAkhirStatus::Disetujui);
    });
});

describe('susunan penguji', function () {
    it('menolak sidang yang pengujinya seluruhnya pembimbing', function () {
        $ta = taDibimbing();
        isiBimbingan($ta, $this->dosen, 8);

        expect(fn () => $this->ujian->jadwalkan(
            $ta->fresh(),
            JenisUjian::Sidang,
            now()->addWeek()->toDateString(),
            '09:00',
            '11:00',
            [['dosen_id' => $this->dosen->id, 'peran' => PeranPenguji::Ketua]],
        ))->toThrow(AturanAkademikException::class, 'seluruhnya terdiri atas pembimbing');
    });

    it('mengizinkan pembimbing duduk di panel selama ada penguji luar', function () {
        // Praktik lazim di sini, dan bukan konflik yang perlu diblokir.
        $ta = taDibimbing();
        isiBimbingan($ta, $this->dosen, 8);
        $luar = Dosen::factory()->create(['is_active' => true]);

        $hasil = $this->ujian->jadwalkan(
            $ta->fresh(),
            JenisUjian::Sidang,
            now()->addWeek()->toDateString(),
            '09:00',
            '11:00',
            [
                ['dosen_id' => $luar->id, 'peran' => PeranPenguji::Ketua],
                ['dosen_id' => $this->dosen->id, 'peran' => PeranPenguji::Sekretaris],
            ],
        );

        expect($hasil['ujian']->penguji)->toHaveCount(2);
    });

    it('mengizinkan seminar proposal hanya oleh pembimbing', function () {
        // Sebagian kampus menjalankan seminar proposal dengan tim pembimbing
        // saja. Menolaknya hanya akan memindahkan seminar itu ke luar sistem.
        $ta = taDibimbing();

        expect(fn () => $this->ujian->jadwalkan(
            $ta->fresh(),
            JenisUjian::Proposal,
            now()->addWeek()->toDateString(),
            '09:00',
            '10:00',
            [['dosen_id' => $this->dosen->id, 'peran' => PeranPenguji::Ketua]],
        ))->not->toThrow(AturanAkademikException::class);
    });

    it('mewajibkan tepat satu ketua penguji', function () {
        $ta = taDibimbing();
        $a = Dosen::factory()->create(['is_active' => true]);
        $b = Dosen::factory()->create(['is_active' => true]);

        expect(fn () => $this->ujian->jadwalkan(
            $ta->fresh(),
            JenisUjian::Proposal,
            now()->addWeek()->toDateString(),
            '09:00',
            '10:00',
            [
                ['dosen_id' => $a->id, 'peran' => PeranPenguji::Ketua],
                ['dosen_id' => $b->id, 'peran' => PeranPenguji::Ketua],
            ],
        ))->toThrow(AturanAkademikException::class, 'tepat satu ketua');
    });
});

describe('syarat sidang', function () {
    it('menolak sidang sebelum bimbingan mencukupi', function () {
        $ta = taDibimbing();
        isiBimbingan($ta, $this->dosen, 3);
        $luar = Dosen::factory()->create(['is_active' => true]);

        expect(fn () => $this->ujian->jadwalkan(
            $ta->fresh(),
            JenisUjian::Sidang,
            now()->addWeek()->toDateString(),
            '09:00',
            '11:00',
            [['dosen_id' => $luar->id, 'peran' => PeranPenguji::Ketua]],
        ))->toThrow(AturanAkademikException::class, 'baru 3 yang tercatat');
    });

    it('tidak menghitung log bimbingan yang belum disetujui', function () {
        // Inti dari tanda tangan pembimbing. Tanpa aturan ini, syarat minimum
        // disertifikasi sendiri oleh orang yang dibatasi olehnya.
        $ta = taDibimbing();

        foreach (range(1, 10) as $i) {
            $this->bimbingan->catat($ta->fresh(), $this->dosen, now()->subDays($i)->toDateString(), "Topik {$i}");
        }

        $luar = Dosen::factory()->create(['is_active' => true]);

        expect($ta->fresh()->bimbingan()->count())->toBe(10)
            ->and(fn () => $this->ujian->jadwalkan(
                $ta->fresh(),
                JenisUjian::Sidang,
                now()->addWeek()->toDateString(),
                '09:00',
                '11:00',
                [['dosen_id' => $luar->id, 'peran' => PeranPenguji::Ketua]],
            ))->toThrow(AturanAkademikException::class, 'baru 0 yang tercatat');
    });
});

describe('bentrok jadwal ujian', function () {
    it('menolak ruang yang sedang dipakai kuliah pada hari dan jam itu', function () {
        // Bentrok yang tidak akan ditemukan bila hanya sidang lain yang diperiksa:
        // panel datang ke ruang yang sudah ada kuliahnya.
        $ta = taDibimbing();
        isiBimbingan($ta, $this->dosen, 8);
        $luar = Dosen::factory()->create(['is_active' => true]);

        $tanggal = now()->addWeek()->next(Carbon\Carbon::MONDAY);

        $kelas = KelasKuliah::factory()->create([
            'tahun_akademik_id' => $this->term->id,
            'prodi_id' => $this->prodi->id,
        ]);

        $kelas->jadwal()->create([
            'ruang_id' => $this->ruang->id,
            'hari' => 1, // Senin
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
        ]);

        expect(fn () => $this->ujian->jadwalkan(
            $ta->fresh(),
            JenisUjian::Sidang,
            $tanggal->toDateString(),
            '09:00',
            '11:00',
            [['dosen_id' => $luar->id, 'peran' => PeranPenguji::Ketua]],
            $this->ruang->id,
        ))->toThrow(AturanAkademikException::class, 'terpakai kuliah');
    });

    it('mengizinkan ruang yang sama pada jam yang tidak beririsan', function () {
        $ta = taDibimbing();
        isiBimbingan($ta, $this->dosen, 8);
        $luar = Dosen::factory()->create(['is_active' => true]);

        $tanggal = now()->addWeek()->next(Carbon\Carbon::MONDAY);

        $kelas = KelasKuliah::factory()->create([
            'tahun_akademik_id' => $this->term->id,
            'prodi_id' => $this->prodi->id,
        ]);

        // Kuliah selesai tepat saat sidang dimulai — berurutan, bukan bersamaan.
        $kelas->jadwal()->create([
            'ruang_id' => $this->ruang->id,
            'hari' => 1,
            'jam_mulai' => '07:00',
            'jam_selesai' => '09:00',
        ]);

        expect(fn () => $this->ujian->jadwalkan(
            $ta->fresh(),
            JenisUjian::Sidang,
            $tanggal->toDateString(),
            '09:00',
            '11:00',
            [['dosen_id' => $luar->id, 'peran' => PeranPenguji::Ketua]],
            $this->ruang->id,
        ))->not->toThrow(AturanAkademikException::class);
    });

    it('menolak penguji yang sudah menguji sidang lain pada jam yang sama', function () {
        $luar = Dosen::factory()->create(['is_active' => true]);
        $tanggal = now()->addWeek()->toDateString();

        $pertama = taDibimbing(mahasiswaSiapTa(), $this->dosen);
        isiBimbingan($pertama, $this->dosen, 8);
        $this->ujian->jadwalkan(
            $pertama->fresh(),
            JenisUjian::Sidang,
            $tanggal,
            '09:00',
            '11:00',
            [['dosen_id' => $luar->id, 'peran' => PeranPenguji::Ketua]],
        );

        $kedua = taDibimbing(mahasiswaSiapTa(), $this->dosen);
        isiBimbingan($kedua, $this->dosen, 8);

        expect(fn () => $this->ujian->jadwalkan(
            $kedua->fresh(),
            JenisUjian::Sidang,
            $tanggal,
            '10:00',
            '12:00',
            [['dosen_id' => $luar->id, 'peran' => PeranPenguji::Ketua]],
        ))->toThrow(AturanAkademikException::class, 'sudah menguji');
    });
});

describe('hasil ujian dan penyelesaian', function () {
    /** Menjadwalkan sidang lengkap dan mengembalikan ujiannya. */
    function sidangkan(TugasAkhir $ta, Dosen $luar)
    {
        isiBimbingan($ta, test()->dosen, 8);

        return test()->ujian->jadwalkan(
            $ta->fresh(),
            JenisUjian::Sidang,
            now()->addWeek()->toDateString(),
            '09:00',
            '11:00',
            [['dosen_id' => $luar->id, 'peran' => PeranPenguji::Ketua]],
        )['ujian'];
    }

    it('memakai rerata penguji bila nilai tidak diketik ulang', function () {
        $luar = Dosen::factory()->create(['is_active' => true]);
        $ujian = sidangkan(taDibimbing(), $luar);

        $this->ujian->nilaiPenguji($ujian->penguji()->first(), 82.0);
        $hasil = $this->ujian->catatHasil($ujian->fresh(), HasilUjian::Lulus);

        expect((float) $hasil->nilai)->toBe(82.0);
    });

    it('mewajibkan batas revisi saat lulus dengan revisi', function () {
        $luar = Dosen::factory()->create(['is_active' => true]);
        $ujian = sidangkan(taDibimbing(), $luar);
        $this->ujian->nilaiPenguji($ujian->penguji()->first(), 75.0);

        expect(fn () => $this->ujian->catatHasil($ujian->fresh(), HasilUjian::LulusRevisi))
            ->toThrow(AturanAkademikException::class, 'batas waktu revisi');
    });

    it('menolak menyelesaikan tugas akhir tanpa sidang yang lulus', function () {
        $ta = taDibimbing();

        expect(fn () => $this->ta->selesaikan($ta->fresh()))
            ->toThrow(AturanAkademikException::class, 'Belum ada sidang akhir yang dinyatakan lulus');
    });

    it('menolak menyelesaikan bila sidang tidak lulus', function () {
        $luar = Dosen::factory()->create(['is_active' => true]);
        $ta = taDibimbing();
        $ujian = sidangkan($ta, $luar);

        $this->ujian->nilaiPenguji($ujian->penguji()->first(), 40.0);
        $this->ujian->catatHasil($ujian->fresh(), HasilUjian::TidakLulus);

        expect(fn () => $this->ta->selesaikan($ta->fresh()))
            ->toThrow(AturanAkademikException::class, 'Belum ada sidang akhir yang dinyatakan lulus');
    });

    it('menutup tugas akhir dan membebaskan slot setelah sidang lulus', function () {
        $luar = Dosen::factory()->create(['is_active' => true]);
        $ta = taDibimbing();
        $ujian = sidangkan($ta, $luar);

        $this->ujian->nilaiPenguji($ujian->penguji()->first(), 88.0);
        $this->ujian->catatHasil($ujian->fresh(), HasilUjian::Lulus);

        $selesai = $this->ta->selesaikan($ta->fresh());

        expect($selesai->status)->toBe(TugasAkhirStatus::Selesai)
            ->and($selesai->mahasiswa_aktif_id)->toBeNull()
            ->and((float) $selesai->nilai_akhir)->toBe(88.0)
            ->and($selesai->nilai_huruf)->toBe('A');
    });
});

describe('log bimbingan', function () {
    it('hanya dapat disetujui oleh dosen yang tercatat pada baris itu', function () {
        // Pembimbing kedua tidak boleh mengesahkan pertemuan yang tidak
        // dihadirinya.
        $ta = taDibimbing();
        $kedua = Dosen::factory()->create(['is_active' => true]);
        $this->ta->tetapkanPembimbing($ta->fresh(), $kedua, PeranPembimbing::Pendamping);

        $log = $this->bimbingan->catat($ta->fresh(), $this->dosen, now()->toDateString(), 'Bab 1');

        expect(fn () => $this->bimbingan->setujui($log, $kedua))
            ->toThrow(AturanAkademikException::class, 'Hanya dosen yang tercatat');
    });

    it('menolak dicatat atas nama dosen yang bukan pembimbing', function () {
        $ta = taDibimbing();
        $asing = Dosen::factory()->create(['is_active' => true]);

        expect(fn () => $this->bimbingan->catat($ta->fresh(), $asing, now()->toDateString(), 'Bab 1'))
            ->toThrow(AturanAkademikException::class, 'bukan pembimbing');
    });

    it('menolak menghapus log yang sudah disetujui', function () {
        $ta = taDibimbing();
        $log = $this->bimbingan->catat($ta->fresh(), $this->dosen, now()->toDateString(), 'Bab 1');
        $this->bimbingan->setujui($log, $this->dosen);

        expect(fn () => $this->bimbingan->hapus($log->fresh()))
            ->toThrow(AturanAkademikException::class, 'sudah disetujui');
    });
});
