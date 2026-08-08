<?php

declare(strict_types=1);

use App\DTOs\Akademik\KeputusanWaliData;
use App\Enums\KrsStatus;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\JadwalKuliah;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Dosen;
use App\Services\Akademik\KrsService;

beforeEach(function () {
    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create();
    $this->kurikulum = Kurikulum::factory()->create(['prodi_id' => $this->prodi->id]);
    $this->wali = Dosen::factory()->create(['prodi_id' => $this->prodi->id]);

    $this->mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => $this->prodi->id,
        'kurikulum_id' => $this->kurikulum->id,
        'dosen_wali_id' => $this->wali->id,
        'status' => StudentStatus::Aktif,
    ]);

    $this->krs = app(KrsService::class);
});

/** Course + offering, wired into the student's curriculum. */
function tawarkanKelas(array $atribut = [], int $semester = 1, array $kelasAtribut = []): KelasKuliah
{
    $test = test();

    $mk = MataKuliah::factory()->create(array_merge([
        'prodi_id' => $test->prodi->id,
        'sks' => 3,
    ], $atribut));

    $test->kurikulum->mataKuliah()->attach($mk->id, ['semester' => $semester, 'jenis' => 'wajib']);

    $kelas = KelasKuliah::factory()->create(array_merge([
        'tahun_akademik_id' => $test->term->id,
        'mata_kuliah_id' => $mk->id,
        'prodi_id' => $test->prodi->id,
        'sks' => $mk->sks,
        'kuota' => 40,
    ], $kelasAtribut));

    JadwalKuliah::create([
        'kelas_kuliah_id' => $kelas->id,
        'hari' => 1,
        'jam_mulai' => '07:30:00',
        'jam_selesai' => '10:00:00',
    ]);

    return $kelas;
}

describe('membuka rencana studi', function () {
    it('memberi batas SKS bawaan kepada mahasiswa tanpa riwayat', function () {
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);

        expect($krs->batas_sks)->toBe(config('academic.krs.default_credits'))
            ->and($krs->ips_acuan)->toBeNull()
            ->and($krs->semester_ke)->toBe(1)
            ->and($krs->status)->toBe(KrsStatus::Draft);
    });

    it('menurunkan batas SKS dari IPS semester terakhir yang final', function () {
        $lalu = TahunAkademik::factory()->term(2025, SemesterType::Genap)->create();

        StatusMahasiswa::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $lalu->id,
            'status' => StudentStatus::Aktif,
            'semester_ke' => 2,
            'ips' => 3.40,
            'ipk' => 3.40,
            'is_final' => true,
        ]);

        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);

        expect($krs->batas_sks)->toBe(24)
            ->and((float) $krs->ips_acuan)->toBe(3.40)
            ->and($krs->semester_ke)->toBe(3);
    });

    it('mengabaikan semester yang nilainya belum final saat menghitung batas', function () {
        $lalu = TahunAkademik::factory()->term(2025, SemesterType::Genap)->create();

        // IPS 0 karena nilainya belum masuk — tidak boleh menghukum mahasiswa.
        StatusMahasiswa::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $lalu->id,
            'status' => StudentStatus::Aktif,
            'semester_ke' => 2,
            'ips' => 0,
            'ipk' => 0,
            'is_final' => false,
        ]);

        expect($this->krs->bukaAtauAmbil($this->mahasiswa, $this->term)->batas_sks)
            ->toBe(config('academic.krs.default_credits'));
    });

    it('menolak mahasiswa yang sedang cuti', function () {
        $this->mahasiswa->update(['status' => StudentStatus::Cuti]);

        expect(fn () => $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term))
            ->toThrow(AturanAkademikException::class, 'Cuti');
    });

    it('tidak membuat rencana studi ganda', function () {
        $pertama = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);
        $kedua = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);

        expect($kedua->id)->toBe($pertama->id)
            ->and(Krs::count())->toBe(1);
    });
});

describe('menambah kelas', function () {
    it('menambah kelas dan memutakhirkan SKS serta kuota terisi', function () {
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);
        $kelas = tawarkanKelas();

        $this->krs->tambahKelas($krs, $kelas);

        expect($krs->fresh()->total_sks)->toBe(3)
            ->and($kelas->fresh()->terisi)->toBe(1)
            ->and($krs->detail()->count())->toBe(1);
    });

    it('menolak mata kuliah di luar kurikulum yang diikuti', function () {
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);

        $asing = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id]);
        $kelas = KelasKuliah::factory()->create([
            'tahun_akademik_id' => $this->term->id,
            'mata_kuliah_id' => $asing->id,
            'prodi_id' => $this->prodi->id,
        ]);

        expect(fn () => $this->krs->tambahKelas($krs, $kelas))
            ->toThrow(AturanAkademikException::class, 'tidak terdaftar pada kurikulum');
    });

    it('menolak mata kuliah yang sudah ada di rencana studi', function () {
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);
        $kelas = tawarkanKelas();

        $this->krs->tambahKelas($krs, $kelas);

        expect(fn () => $this->krs->tambahKelas($krs->fresh(), $kelas))
            ->toThrow(AturanAkademikException::class, 'sudah ada di rencana studi');
    });

    it('menolak melebihi batas SKS', function () {
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);
        $krs->update(['batas_sks' => 4]);

        $this->krs->tambahKelas($krs->fresh(), tawarkanKelas());

        expect(fn () => $this->krs->tambahKelas($krs->fresh(), tawarkanKelas()))
            ->toThrow(AturanAkademikException::class, 'melebihi batas');
    });

    it('menolak ketika kuota kelas sudah penuh', function () {
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);
        $kelas = tawarkanKelas(kelasAtribut: ['kuota' => 1, 'terisi' => 1]);

        expect(fn () => $this->krs->tambahKelas($krs, $kelas))
            ->toThrow(AturanAkademikException::class, 'Kuota');
    });

    it('menolak kelas dari semester lain', function () {
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);

        $lain = TahunAkademik::factory()->term(2025, SemesterType::Ganjil)->create();
        $kelas = tawarkanKelas(kelasAtribut: ['tahun_akademik_id' => $lain->id]);

        expect(fn () => $this->krs->tambahKelas($krs, $kelas))
            ->toThrow(AturanAkademikException::class, 'tidak ditawarkan pada semester aktif');
    });

    it('menolak jadwal yang bentrok dengan kelas yang sudah diambil', function () {
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);

        $this->krs->tambahKelas($krs, tawarkanKelas());

        // Kelas kedua memakai slot Senin 07.30 yang sama.
        expect(fn () => $this->krs->tambahKelas($krs->fresh(), tawarkanKelas()))
            ->toThrow(AturanAkademikException::class, 'bentrok');
    });

    it('menolak mata kuliah yang prasyaratnya belum lulus', function () {
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);

        $dasar = tawarkanKelas(['nama' => 'Algoritma dan Pemrograman']);
        $lanjut = tawarkanKelas(['nama' => 'Struktur Data'], kelasAtribut: ['kode' => 'B']);

        $lanjut->mataKuliah->prasyarat()->attach($dasar->mata_kuliah_id, ['jenis' => 'prasyarat']);

        expect(fn () => $this->krs->tambahKelas($krs, $lanjut))
            ->toThrow(AturanAkademikException::class, 'Algoritma dan Pemrograman');
    });

    it('meloloskan mata kuliah yang prasyaratnya sudah lulus', function () {
        $dasar = tawarkanKelas(['nama' => 'Algoritma dan Pemrograman']);
        $lanjut = tawarkanKelas(['nama' => 'Struktur Data'], kelasAtribut: ['kode' => 'B']);
        $lanjut->mataKuliah->prasyarat()->attach($dasar->mata_kuliah_id, ['jenis' => 'prasyarat']);

        luluskan($this->mahasiswa, $dasar, 'A');

        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);

        expect($this->krs->tambahKelas($krs, $lanjut))->toBeInstanceOf(KrsDetail::class);
    });

    it('menolak mata kuliah yang sudah dilulusi', function () {
        $kelas = tawarkanKelas(['nama' => 'Kalkulus I']);
        luluskan($this->mahasiswa, $kelas, 'B');

        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);

        expect(fn () => $this->krs->tambahKelas($krs, $kelas))
            ->toThrow(AturanAkademikException::class, 'sudah Anda lulusi');
    });
});

describe('menghapus kelas', function () {
    it('mengembalikan kuota dan mengurangi total SKS', function () {
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);
        $kelas = tawarkanKelas();

        $detail = $this->krs->tambahKelas($krs, $kelas);
        $this->krs->hapusKelas($krs->fresh(), $detail);

        expect($krs->fresh()->total_sks)->toBe(0)
            ->and($kelas->fresh()->terisi)->toBe(0);
    });
});

describe('pengajuan', function () {
    it('menolak pengajuan rencana studi kosong', function () {
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);

        expect(fn () => $this->krs->ajukan($krs))
            ->toThrow(AturanAkademikException::class, 'masih kosong');
    });

    it('mengajukan rencana studi yang sudah berisi', function () {
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);
        $this->krs->tambahKelas($krs, tawarkanKelas());

        $diajukan = $this->krs->ajukan($krs->fresh());

        expect($diajukan->status)->toBe(KrsStatus::Diajukan)
            ->and($diajukan->diajukan_at)->not->toBeNull();
    });

    it('mengunci pengajuan ketika pembayaran belum memenuhi ambang', function () {
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);
        $this->krs->tambahKelas($krs, tawarkanKelas());

        Tagihan::create([
            'nomor' => 'INV/UJI/1',
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'keterangan' => 'UKT',
            'total' => 1_000_000,
            'terbayar' => 100_000,
            'jatuh_tempo' => now()->addWeek(),
        ]);

        expect(fn () => $this->krs->ajukan($krs->fresh()))
            ->toThrow(AturanAkademikException::class, 'terkunci hingga pembayaran');
    });

    it('meloloskan pengajuan ketika tidak ada tagihan sama sekali', function () {
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);
        $this->krs->tambahKelas($krs, tawarkanKelas());

        expect($this->krs->ajukan($krs->fresh())->status)->toBe(KrsStatus::Diajukan);
    });

    it('menolak pengajuan ulang rencana studi yang sudah disetujui', function () {
        $krs = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);
        $this->krs->tambahKelas($krs, tawarkanKelas());
        $this->krs->ajukan($krs->fresh());
        $this->krs->putuskan($krs->fresh(), $this->wali, KeputusanWaliData::setujui());

        expect(fn () => $this->krs->ajukan($krs->fresh()))
            ->toThrow(AturanAkademikException::class, 'tidak dapat diubah menjadi');
    });
});

describe('keputusan dosen wali', function () {
    beforeEach(function () {
        $this->rencana = $this->krs->bukaAtauAmbil($this->mahasiswa, $this->term);
        $this->krs->tambahKelas($this->rencana, tawarkanKelas());
        $this->krs->ajukan($this->rencana->fresh());
        $this->rencana = $this->rencana->fresh();
    });

    it('menyetujui dan mencatat status semester mahasiswa', function () {
        $hasil = $this->krs->putuskan($this->rencana, $this->wali, KeputusanWaliData::setujui('Lanjutkan.'));

        expect($hasil->status)->toBe(KrsStatus::Disetujui)
            ->and($hasil->disetujui_by_dosen_id)->toBe($this->wali->id)
            ->and(StatusMahasiswa::where('mahasiswa_id', $this->mahasiswa->id)->exists())->toBeTrue();
    });

    it('menolak dosen yang bukan wali mahasiswa tersebut', function () {
        $lain = Dosen::factory()->create();

        expect(fn () => $this->krs->putuskan($this->rencana, $lain, KeputusanWaliData::setujui()))
            ->toThrow(AturanAkademikException::class, 'Hanya Dosen Wali');
    });

    it('mewajibkan catatan pada penolakan', function () {
        expect(fn () => $this->krs->putuskan($this->rencana, $this->wali, new KeputusanWaliData(disetujui: false)))
            ->toThrow(AturanAkademikException::class, 'wajib disertai catatan');
    });

    it('mengembalikan rencana studi ke keadaan dapat disunting saat ditolak', function () {
        $hasil = $this->krs->putuskan($this->rencana, $this->wali, KeputusanWaliData::tolak('Kurangi beban SKS.'));

        expect($hasil->status)->toBe(KrsStatus::Ditolak)
            ->and($hasil->status->isEditable())->toBeTrue()
            ->and($hasil->catatan_wali)->toBe('Kurangi beban SKS.');
    });

    it('membersihkan catatan penolakan saat mahasiswa mengajukan ulang', function () {
        $this->krs->putuskan($this->rencana, $this->wali, KeputusanWaliData::tolak('Kurangi beban SKS.'));

        expect($this->krs->ajukan($this->rencana->fresh())->catatan_wali)->toBeNull();
    });
});

/**
 * Meluluskan mahasiswa pada sebuah mata kuliah di semester lampau.
 *
 * Riwayat kelulusan harus berada di semester sebelumnya — menaruhnya di
 * semester berjalan akan bertabrakan dengan rencana studi yang sedang diisi,
 * dan itu bukan keadaan yang mungkin terjadi di dunia nyata.
 */
function luluskan(Mahasiswa $mahasiswa, KelasKuliah $kelas, string $huruf): void
{
    $lampau = TahunAkademik::firstWhere('kode', '20252')
        ?? TahunAkademik::factory()->term(2025, SemesterType::Genap)->terkunci()->create();

    $kelasLampau = KelasKuliah::factory()->create([
        'tahun_akademik_id' => $lampau->id,
        'mata_kuliah_id' => $kelas->mata_kuliah_id,
        'prodi_id' => $kelas->prodi_id,
        'sks' => $kelas->sks,
        'status_nilai' => 'final',
    ]);

    $krs = Krs::firstOrCreate(
        ['mahasiswa_id' => $mahasiswa->id, 'tahun_akademik_id' => $lampau->id],
        ['semester_ke' => 1, 'batas_sks' => 24, 'status' => KrsStatus::Disetujui],
    );

    $detail = KrsDetail::create([
        'krs_id' => $krs->id,
        'kelas_kuliah_id' => $kelasLampau->id,
        'sks' => $kelasLampau->sks,
    ]);

    Nilai::create([
        'krs_detail_id' => $detail->id,
        'kelas_kuliah_id' => $kelasLampau->id,
        'mahasiswa_id' => $mahasiswa->id,
        'nilai_angka' => 85,
        'nilai_huruf' => $huruf,
        'bobot' => 4,
        'is_final' => true,
    ]);
}
