<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\SemesterType;
use App\Exceptions\PenilaianException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\PertemuanKelas;
use App\Models\Akademik\Presensi;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Akademik\PenilaianService;

beforeEach(function () {
    // Jendela penilaian dibuka lebar supaya aturan periode diuji terpisah.
    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->create([
        'is_active' => true,
        'nilai_mulai' => now()->subMonth(),
        'nilai_selesai' => now()->addMonth(),
    ]);

    $this->prodi = Prodi::factory()->create();
    $this->dosen = Dosen::factory()->create(['prodi_id' => $this->prodi->id]);

    $mk = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id, 'sks' => 3]);

    $this->kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => $this->term->id,
        'mata_kuliah_id' => $mk->id,
        'prodi_id' => $this->prodi->id,
        'sks' => 3,
    ]);
    $this->kelas->dosen()->attach($this->dosen->id, ['peran' => 'pengampu']);

    $this->penilaian = app(PenilaianService::class);
});

/** Mendaftarkan mahasiswa ke kelas lewat KRS yang disetujui. */
function daftarkan(KelasKuliah $kelas, ?Mahasiswa $mahasiswa = null): KrsDetail
{
    $mahasiswa ??= Mahasiswa::factory()->create(['prodi_id' => $kelas->prodi_id]);

    $krs = Krs::firstOrCreate(
        ['mahasiswa_id' => $mahasiswa->id, 'tahun_akademik_id' => $kelas->tahun_akademik_id],
        ['semester_ke' => 1, 'batas_sks' => 24, 'status' => 'disetujui'],
    );

    StatusMahasiswa::firstOrCreate(
        ['mahasiswa_id' => $mahasiswa->id, 'tahun_akademik_id' => $kelas->tahun_akademik_id],
        ['status' => 'A', 'semester_ke' => 1],
    );

    return KrsDetail::create([
        'krs_id' => $krs->id,
        'kelas_kuliah_id' => $kelas->id,
        'sks' => $kelas->sks,
    ]);
}

/** Mengisi seluruh komponen dengan satu nilai yang sama. */
function isiSeragam(KelasKuliah $kelas, KrsDetail $detail, float $nilai): array
{
    return [
        $detail->id => app(PenilaianService::class)
            ->komponen($kelas)
            ->mapWithKeys(fn ($k): array => [$k->id => $nilai])
            ->all(),
    ];
}

describe('komponen penilaian', function () {
    it('membuat komponen bawaan pada pembukaan pertama', function () {
        $komponen = $this->penilaian->komponen($this->kelas);

        expect($komponen)->toHaveCount(3)
            ->and($komponen->sum('bobot'))->toBe(100);
    });

    it('menolak susunan komponen yang bobotnya bukan 100', function () {
        expect(fn () => $this->penilaian->simpanKomponen($this->kelas, [
            ['nama' => 'Tugas', 'bobot' => 40],
            ['nama' => 'UAS', 'bobot' => 40],
        ]))->toThrow(PenilaianException::class, 'harus 100%');
    });

    it('menerima susunan komponen yang sah', function () {
        $komponen = $this->penilaian->simpanKomponen($this->kelas, [
            ['nama' => 'Praktikum', 'bobot' => 50],
            ['nama' => 'Proyek Akhir', 'bobot' => 50],
        ]);

        expect($komponen->pluck('nama')->all())->toBe(['Praktikum', 'Proyek Akhir']);
    });
});

describe('pengisian nilai', function () {
    it('menghitung nilai akhir terbobot dan hurufnya', function () {
        $detail = daftarkan($this->kelas);
        $komponen = $this->penilaian->komponen($this->kelas);

        // Tugas 30% = 90, UTS 30% = 80, UAS 40% = 70 → 27 + 24 + 28 = 79
        $this->penilaian->simpanNilai($this->kelas, [
            $detail->id => [
                $komponen[0]->id => 90,
                $komponen[1]->id => 80,
                $komponen[2]->id => 70,
            ],
        ]);

        $nilai = Nilai::where('krs_detail_id', $detail->id)->firstOrFail();

        expect((float) $nilai->nilai_angka)->toBe(79.0)
            ->and($nilai->nilai_huruf->value)->toBe('AB')
            ->and($nilai->is_final)->toBeFalse();
    });

    it('menandai kelas sebagai sebagian begitu ada isian, dan tetap sebagian saat penuh', function () {
        $detail = daftarkan($this->kelas);
        $komponen = $this->penilaian->komponen($this->kelas);

        expect($this->kelas->fresh()->status_nilai)->toBe('belum');

        $this->penilaian->simpanNilai($this->kelas, [$detail->id => [$komponen[0]->id => 80]]);
        expect($this->kelas->fresh()->status_nilai)->toBe('sebagian');

        // Terisi penuh bukan berarti final — final adalah keadaan tersendiri.
        $this->penilaian->simpanNilai($this->kelas->fresh(), isiSeragam($this->kelas, $detail, 80));
        expect($this->kelas->fresh()->status_nilai)->toBe('sebagian');
    });

    it('menolak nilai di luar rentang 0-100', function () {
        $detail = daftarkan($this->kelas);

        expect(fn () => $this->penilaian->simpanNilai($this->kelas, isiSeragam($this->kelas, $detail, 130)))
            ->toThrow(PenilaianException::class, 'rentang 0 sampai 100');
    });

    it('mengabaikan isian untuk mahasiswa yang tidak mengambil kelas ini', function () {
        $detail = daftarkan($this->kelas);
        $kelasLain = KelasKuliah::factory()->create(['tahun_akademik_id' => $this->term->id]);
        $detailAsing = daftarkan($kelasLain);

        $komponen = $this->penilaian->komponen($this->kelas);

        $this->penilaian->simpanNilai($this->kelas, [
            $detail->id => [$komponen[0]->id => 80],
            $detailAsing->id => [$komponen[0]->id => 95],
        ]);

        expect(Nilai::where('krs_detail_id', $detailAsing->id)->exists())->toBeFalse();
    });

    it('menolak pengisian di luar periode penilaian', function () {
        $this->term->update(['nilai_mulai' => now()->addMonth(), 'nilai_selesai' => now()->addMonths(2)]);
        $detail = daftarkan($this->kelas);

        expect(fn () => $this->penilaian->simpanNilai($this->kelas, isiSeragam($this->kelas, $detail, 80)))
            ->toThrow(PenilaianException::class, 'belum dibuka atau sudah ditutup');
    });
});

describe('finalisasi', function () {
    it('menolak finalisasi ketika masih ada isian kosong', function () {
        $detail = daftarkan($this->kelas);
        $komponen = $this->penilaian->komponen($this->kelas);

        $this->penilaian->simpanNilai($this->kelas, [$detail->id => [$komponen[0]->id => 80]]);

        expect(fn () => $this->penilaian->finalisasi($this->kelas, $this->dosen))
            ->toThrow(PenilaianException::class, 'isian nilai yang kosong');
    });

    it('menolak finalisasi kelas tanpa peserta', function () {
        expect(fn () => $this->penilaian->finalisasi($this->kelas, $this->dosen))
            ->toThrow(PenilaianException::class, 'Belum ada mahasiswa');
    });

    it('mengunci nilai dan menghitung ulang IPS mahasiswa', function () {
        $detail = daftarkan($this->kelas);
        $this->penilaian->simpanNilai($this->kelas, isiSeragam($this->kelas, $detail, 85));

        $this->penilaian->finalisasi($this->kelas, $this->dosen);

        $nilai = Nilai::where('krs_detail_id', $detail->id)->firstOrFail();
        $status = StatusMahasiswa::where('mahasiswa_id', $nilai->mahasiswa_id)->firstOrFail();

        expect($nilai->is_final)->toBeTrue()
            ->and($nilai->finalized_by_dosen_id)->toBe($this->dosen->id)
            ->and($this->kelas->fresh()->status_nilai)->toBe('final')
            ->and((float) $status->ips)->toBe(4.0)
            ->and($status->sks_semester)->toBe(3);
    });

    it('menolak perubahan nilai setelah kelas difinalisasi', function () {
        $detail = daftarkan($this->kelas);
        $this->penilaian->simpanNilai($this->kelas, isiSeragam($this->kelas, $detail, 85));
        $this->penilaian->finalisasi($this->kelas, $this->dosen);

        expect(fn () => $this->penilaian->simpanNilai($this->kelas->fresh(), isiSeragam($this->kelas, $detail, 40)))
            ->toThrow(PenilaianException::class, 'sudah difinalisasi');
    });
});

describe('koreksi ter-audit', function () {
    it('mengubah nilai final dan mencatat alasannya di jejak audit', function () {
        $detail = daftarkan($this->kelas);
        $this->penilaian->simpanNilai($this->kelas, isiSeragam($this->kelas, $detail, 40));
        $this->penilaian->finalisasi($this->kelas, $this->dosen);

        $nilai = Nilai::where('krs_detail_id', $detail->id)->firstOrFail();
        expect($nilai->nilai_huruf->value)->toBe('E');

        $staff = Staff::factory()->create(['nama' => 'Sri Wahyuni']);
        $dikoreksi = $this->penilaian->koreksi($nilai, 82, 'Salah input nilai UAS, berkas terlampir.', $staff);

        expect($dikoreksi->nilai_huruf->value)->toBe('A')
            ->and($dikoreksi->catatan_koreksi)->toContain('Salah input');

        $jejak = $dikoreksi->logAktivitas()->where('event', 'corrected')->first();

        expect($jejak)->not->toBeNull()
            ->and($jejak->description)->toContain('E → A')
            ->and($jejak->description)->toContain('Sri Wahyuni');
    });

    it('mewajibkan alasan pada koreksi', function () {
        $detail = daftarkan($this->kelas);
        $this->penilaian->simpanNilai($this->kelas, isiSeragam($this->kelas, $detail, 70));
        $this->penilaian->finalisasi($this->kelas, $this->dosen);

        $nilai = Nilai::where('krs_detail_id', $detail->id)->firstOrFail();

        expect(fn () => $this->penilaian->koreksi($nilai, 80, '', Staff::factory()->create()))
            ->toThrow(PenilaianException::class, 'wajib disertai alasan');
    });
});

describe('lembar nilai', function () {
    it('menandai mahasiswa yang kehadirannya di bawah ambang UAS', function () {
        $detail = daftarkan($this->kelas);
        $mahasiswaId = $detail->krs->mahasiswa_id;

        // Empat pertemuan terlaksana, hadir hanya sekali → 25%.
        foreach (range(1, 4) as $ke) {
            $pertemuan = PertemuanKelas::create([
                'kelas_kuliah_id' => $this->kelas->id,
                'pertemuan_ke' => $ke,
                'tanggal' => now()->subWeeks(5 - $ke),
                'is_terlaksana' => true,
            ]);

            Presensi::create([
                'pertemuan_kelas_id' => $pertemuan->id,
                'mahasiswa_id' => $mahasiswaId,
                'status' => $ke === 1 ? AttendanceStatus::Hadir : AttendanceStatus::Alpa,
            ]);
        }

        $baris = $this->penilaian->lembarNilai($this->kelas)->first();

        expect($baris->persenKehadiran)->toBe(25.0)
            ->and($baris->layakUas)->toBeFalse()
            ->and($baris->bermasalah())->toBeTrue();
    });

    it('tidak mendiskualifikasi siapa pun ketika belum ada pertemuan terlaksana', function () {
        daftarkan($this->kelas);

        $baris = $this->penilaian->lembarNilai($this->kelas)->first();

        expect($baris->persenKehadiran)->toBeNull()
            ->and($baris->layakUas)->toBeTrue();
    });
});

describe('IPK dengan mata kuliah mengulang', function () {
    it('menghitung mata kuliah yang diulang satu kali dengan nilai terbaik', function () {
        $mahasiswa = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);

        // Percobaan pertama: nilai E.
        $detail = daftarkan($this->kelas, $mahasiswa);
        $this->penilaian->simpanNilai($this->kelas, isiSeragam($this->kelas, $detail, 40));
        $this->penilaian->finalisasi($this->kelas, $this->dosen);

        // Mengulang mata kuliah yang sama di semester berikutnya: nilai A.
        $termBaru = TahunAkademik::factory()->term(2026, SemesterType::Genap)->create([
            'nilai_mulai' => now()->subMonth(),
            'nilai_selesai' => now()->addMonth(),
        ]);

        $ulang = KelasKuliah::factory()->create([
            'tahun_akademik_id' => $termBaru->id,
            'mata_kuliah_id' => $this->kelas->mata_kuliah_id,
            'prodi_id' => $this->prodi->id,
            'sks' => 3,
        ]);
        $ulang->dosen()->attach($this->dosen->id, ['peran' => 'pengampu']);

        $detailUlang = daftarkan($ulang, $mahasiswa);
        $this->penilaian->simpanNilai($ulang, isiSeragam($ulang, $detailUlang, 85));
        $this->penilaian->finalisasi($ulang, $this->dosen);

        $status = StatusMahasiswa::where('mahasiswa_id', $mahasiswa->id)
            ->where('tahun_akademik_id', $termBaru->id)
            ->firstOrFail();

        // IPK memakai nilai terbaik sekali, bukan rata-rata kedua percobaan.
        expect((float) $status->ipk)->toBe(4.0)
            ->and($status->sks_kumulatif)->toBe(3);
    });
});
