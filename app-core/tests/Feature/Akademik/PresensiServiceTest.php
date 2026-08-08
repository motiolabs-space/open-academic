<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\SemesterType;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\PertemuanKelas;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Akademik\PresensiService;

beforeEach(function () {
    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create();

    $this->kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => $this->term->id,
        'prodi_id' => $this->prodi->id,
    ]);

    $this->presensi = app(PresensiService::class);
});

function pesertaBaru(KelasKuliah $kelas): Mahasiswa
{
    $mahasiswa = Mahasiswa::factory()->create(['prodi_id' => $kelas->prodi_id]);

    $krs = Krs::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => $kelas->tahun_akademik_id,
        'semester_ke' => 1,
        'batas_sks' => 24,
        'status' => 'disetujui',
    ]);

    KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => $kelas->sks]);

    return $mahasiswa;
}

it('menyiapkan 16 pertemuan sesuai konfigurasi', function () {
    $pertemuan = $this->presensi->siapkanPertemuan($this->kelas);

    expect($pertemuan)->toHaveCount((int) config('academic.attendance.meetings_per_term'))
        ->and($pertemuan->pluck('pertemuan_ke')->first())->toBe(1);
});

it('tidak menggandakan pertemuan pada pemanggilan berulang', function () {
    $this->presensi->siapkanPertemuan($this->kelas);
    $this->presensi->siapkanPertemuan($this->kelas);

    expect(PertemuanKelas::where('kelas_kuliah_id', $this->kelas->id)->count())
        ->toBe((int) config('academic.attendance.meetings_per_term'));
});

it('mencatat kehadiran dan menandai pertemuan terlaksana', function () {
    $mahasiswa = pesertaBaru($this->kelas);
    $pertemuan = $this->presensi->siapkanPertemuan($this->kelas)->first();

    $jumlah = $this->presensi->catat($pertemuan, [$mahasiswa->id => 'H']);

    expect($jumlah)->toBe(1)
        ->and($pertemuan->fresh()->is_terlaksana)->toBeTrue();
});

it('mengabaikan tanda untuk mahasiswa yang tidak mengambil kelas', function () {
    $peserta = pesertaBaru($this->kelas);
    $asing = Mahasiswa::factory()->create();
    $pertemuan = $this->presensi->siapkanPertemuan($this->kelas)->first();

    // Tanda liar akan merusak persentase yang dipakai aturan kelayakan UAS.
    $jumlah = $this->presensi->catat($pertemuan, [$peserta->id => 'H', $asing->id => 'H']);

    expect($jumlah)->toBe(1);
});

it('menghitung persentase terhadap pertemuan yang benar-benar terlaksana', function () {
    $mahasiswa = pesertaBaru($this->kelas);
    $pertemuan = $this->presensi->siapkanPertemuan($this->kelas);

    // Empat pertemuan dijalankan; hadir dua, izin satu, alpa satu → 75%.
    $this->presensi->catat($pertemuan[0], [$mahasiswa->id => 'H']);
    $this->presensi->catat($pertemuan[1], [$mahasiswa->id => 'H']);
    $this->presensi->catat($pertemuan[2], [$mahasiswa->id => 'I']);
    $this->presensi->catat($pertemuan[3], [$mahasiswa->id => 'A']);

    // Bukan 4/16: kelas yang baru berjalan empat pertemuan tidak boleh
    // menampilkan seluruh mahasiswanya di angka 25%.
    expect($this->presensi->persenKehadiran($mahasiswa, $this->kelas->fresh()))->toBe(75.0);
});

it('menghitung izin dan sakit sebagai kehadiran, hanya alpa yang tidak', function () {
    $mahasiswa = pesertaBaru($this->kelas);
    $pertemuan = $this->presensi->siapkanPertemuan($this->kelas);

    $this->presensi->catat($pertemuan[0], [$mahasiswa->id => AttendanceStatus::Izin->value]);
    $this->presensi->catat($pertemuan[1], [$mahasiswa->id => AttendanceStatus::Sakit->value]);

    expect($this->presensi->persenKehadiran($mahasiswa, $this->kelas->fresh()))->toBe(100.0);
});

it('menyatakan tidak layak UAS di bawah ambang minimum', function () {
    $mahasiswa = pesertaBaru($this->kelas);
    $pertemuan = $this->presensi->siapkanPertemuan($this->kelas);

    foreach (range(0, 3) as $i) {
        $this->presensi->catat($pertemuan[$i], [$mahasiswa->id => $i === 0 ? 'H' : 'A']);
    }

    expect($this->presensi->layakUas($mahasiswa, $this->kelas->fresh()))->toBeFalse();
});

it('tidak mendiskualifikasi siapa pun sebelum ada pertemuan terlaksana', function () {
    $mahasiswa = pesertaBaru($this->kelas);
    $this->presensi->siapkanPertemuan($this->kelas);

    expect($this->presensi->persenKehadiran($mahasiswa, $this->kelas))->toBeNull()
        ->and($this->presensi->layakUas($mahasiswa, $this->kelas))->toBeTrue();
});

describe('sesi QR', function () {
    it('membuka sesi dengan token dan masa berlaku', function () {
        $pertemuan = $this->presensi->siapkanPertemuan($this->kelas)->first();

        $dibuka = $this->presensi->bukaSesiQr($pertemuan);

        expect($dibuka->qr_token)->not->toBeNull()
            ->and($dibuka->qrAktif())->toBeTrue()
            ->and($dibuka->sisaDetikQr())->toBeGreaterThan(0)
            ->and($dibuka->sisaDetikQr())->toBeInt();
    });

    it('memungkinkan mahasiswa peserta menandai kehadirannya sendiri', function () {
        $mahasiswa = pesertaBaru($this->kelas);
        $pertemuan = $this->presensi->bukaSesiQr($this->presensi->siapkanPertemuan($this->kelas)->first());

        $presensi = $this->presensi->absenMandiri($pertemuan->qr_token, $mahasiswa);

        expect($presensi->status)->toBe(AttendanceStatus::Hadir)
            ->and($presensi->sumber)->toBe('qr');
    });

    it('menolak mahasiswa yang tidak terdaftar di kelas', function () {
        $asing = Mahasiswa::factory()->create();
        $pertemuan = $this->presensi->bukaSesiQr($this->presensi->siapkanPertemuan($this->kelas)->first());

        expect(fn () => $this->presensi->absenMandiri($pertemuan->qr_token, $asing))
            ->toThrow(AturanAkademikException::class, 'tidak terdaftar');
    });

    it('menolak token yang sudah kedaluwarsa', function () {
        $mahasiswa = pesertaBaru($this->kelas);
        $pertemuan = $this->presensi->bukaSesiQr($this->presensi->siapkanPertemuan($this->kelas)->first());
        $token = $pertemuan->qr_token;

        // Tangkapan layar yang diteruskan ke teman yang absen harus keburu mati.
        $pertemuan->update(['qr_expires_at' => now()->subMinute()]);

        expect(fn () => $this->presensi->absenMandiri($token, $mahasiswa))
            ->toThrow(AturanAkademikException::class, 'sudah berakhir');
    });

    it('mematikan token saat sesi ditutup', function () {
        $mahasiswa = pesertaBaru($this->kelas);
        $pertemuan = $this->presensi->bukaSesiQr($this->presensi->siapkanPertemuan($this->kelas)->first());
        $token = $pertemuan->qr_token;

        $this->presensi->tutupSesiQr($pertemuan);

        expect(fn () => $this->presensi->absenMandiri($token, $mahasiswa))
            ->toThrow(AturanAkademikException::class);
    });
});
