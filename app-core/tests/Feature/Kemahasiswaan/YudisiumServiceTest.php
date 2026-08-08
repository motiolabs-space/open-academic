<?php

declare(strict_types=1);

use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Enums\TugasAkhirStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Bridge\BridgeConsumer;
use App\Models\Bridge\BridgeWebhookDelivery;
use App\Models\Kemahasiswaan\Alumni;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Staff;
use App\Models\TugasAkhir\TugasAkhir;
use App\Services\Kemahasiswaan\YudisiumService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create(['sks_lulus' => 12]);
    $this->staff = Staff::factory()->create();
    $this->yudisium = app(YudisiumService::class);

    $this->mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => $this->prodi->id,
        'status' => StudentStatus::Aktif,
    ]);
});

/** Memberi mahasiswa sejumlah SKS lulus dengan huruf tertentu. */
function luluskanMk(Mahasiswa $mahasiswa, int $sks, string $huruf, float $bobot, bool $final = true): Nilai
{
    $test = test();

    $mk = MataKuliah::factory()->create(['prodi_id' => $mahasiswa->prodi_id, 'sks' => $sks]);
    $kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => $test->term->id,
        'mata_kuliah_id' => $mk->id,
        'prodi_id' => $mahasiswa->prodi_id,
        'sks' => $sks,
    ]);

    $krs = Krs::firstOrCreate(
        ['mahasiswa_id' => $mahasiswa->id, 'tahun_akademik_id' => $test->term->id],
        ['semester_ke' => 1, 'batas_sks' => 99, 'status' => 'disetujui'],
    );

    $detail = KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => $sks]);

    return Nilai::create([
        'krs_detail_id' => $detail->id,
        'kelas_kuliah_id' => $kelas->id,
        'mahasiswa_id' => $mahasiswa->id,
        'nilai_angka' => 85,
        'nilai_huruf' => $huruf,
        'bobot' => $bobot,
        'is_final' => $final,
    ]);
}

/**
 * Memberi mahasiswa satu tugas akhir yang sudah lulus sidang.
 *
 * Sejak modul tugas akhir ada, ini bagian dari "memenuhi seluruh syarat" —
 * mahasiswa tanpa karya yang teruji tidak dapat diluluskan.
 */
function selesaikanTugasAkhir(Mahasiswa $mahasiswa, string $judul = 'Rancang Bangun Sistem Informasi Akademik'): TugasAkhir
{
    return TugasAkhir::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'mahasiswa_aktif_id' => null,
        'judul' => $judul,
        'status' => TugasAkhirStatus::Selesai,
        'tanggal_pengajuan' => now()->subMonths(8)->toDateString(),
        'tanggal_selesai' => now()->toDateString(),
        'nilai_akhir' => 87.5,
        'nilai_huruf' => 'A',
    ]);
}

describe('pemeriksaan syarat', function () {
    it('menolak mahasiswa yang SKS-nya belum cukup', function () {
        luluskanMk($this->mahasiswa, 6, 'A', 4.0);

        $syarat = $this->yudisium->periksaSyarat($this->mahasiswa);

        expect($syarat->memenuhi())->toBeFalse()
            ->and($syarat->sksLulus)->toBe(6)
            ->and($syarat->belumTerpenuhi())->toContain('Total SKS lulus');
    });

    it('meloloskan mahasiswa yang memenuhi seluruh syarat', function () {
        luluskanMk($this->mahasiswa, 6, 'A', 4.0);
        luluskanMk($this->mahasiswa, 6, 'A', 4.0);
        selesaikanTugasAkhir($this->mahasiswa);

        $syarat = $this->yudisium->periksaSyarat($this->mahasiswa);

        expect($syarat->memenuhi())->toBeTrue()
            ->and($syarat->sksLulus)->toBe(12)
            ->and($syarat->ipk)->toBe(4.0)
            ->and($syarat->persenSelesai())->toBe(100);
    });

    it('tidak menghitung mata kuliah yang tidak lulus ke dalam SKS kelulusan', function () {
        luluskanMk($this->mahasiswa, 6, 'A', 4.0);
        luluskanMk($this->mahasiswa, 6, 'E', 0.0);

        expect($this->yudisium->periksaSyarat($this->mahasiswa)->sksLulus)->toBe(6);
    });

    it('menahan kelulusan selama masih ada tunggakan keuangan', function () {
        luluskanMk($this->mahasiswa, 12, 'A', 4.0);

        Tagihan::create([
            'nomor' => 'INV/1',
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'keterangan' => 'UKT',
            'total' => 1_000_000,
            'terbayar' => 400_000,
            'jatuh_tempo' => now(),
        ]);

        $syarat = $this->yudisium->periksaSyarat($this->mahasiswa);

        expect($syarat->memenuhi())->toBeFalse()
            ->and($syarat->tunggakan)->toBe(600_000)
            ->and($syarat->belumTerpenuhi())->toContain('Bebas tanggungan keuangan');
    });

    it('menahan kelulusan selama masih ada nilai belum final', function () {
        luluskanMk($this->mahasiswa, 12, 'A', 4.0);
        luluskanMk($this->mahasiswa, 3, 'B', 3.0, final: false);

        // Nilai yang masih dapat berubah akan membuat ijazah berbeda dari
        // catatan; kelulusan ditahan sampai seluruhnya final.
        expect($this->yudisium->periksaSyarat($this->mahasiswa)->adaNilaiBelumFinal)->toBeTrue()
            ->and($this->yudisium->periksaSyarat($this->mahasiswa)->memenuhi())->toBeFalse();
    });

    it('menjelaskan setiap syarat, bukan sekadar lolos atau tidak', function () {
        $syarat = $this->yudisium->periksaSyarat($this->mahasiswa);

        expect($syarat->rincian)->toHaveCount(6)
            ->and($syarat->rincian[0])->toHaveKeys(['kode', 'label', 'terpenuhi', 'keterangan'])
            ->and(collect($syarat->rincian)->pluck('kode'))->toContain('tugas_akhir');
    });

    it('menahan kelulusan selama tugas akhir belum selesai', function () {
        luluskanMk($this->mahasiswa, 12, 'A', 4.0);

        // Seluruh syarat lain terpenuhi. Yang tersisa hanya karya yang teruji —
        // dan tanpa modul ini, judulnya cukup diketik saat yudisium.
        $syarat = $this->yudisium->periksaSyarat($this->mahasiswa);

        expect($syarat->memenuhi())->toBeFalse()
            ->and($syarat->belumTerpenuhi())->toContain('Skripsi selesai');
    });

    it('melewati syarat tugas akhir pada prodi yang tidak mewajibkannya', function () {
        // Sebagian program vokasi dan profesi menggantinya dengan uji
        // kompetensi. Barisnya dihilangkan sama sekali, bukan ditandai lolos,
        // supaya persenSelesai() tidak menghitung syarat yang tidak pernah ada.
        $this->prodi->update(['wajib_tugas_akhir' => false]);
        luluskanMk($this->mahasiswa, 12, 'A', 4.0);

        $syarat = $this->yudisium->periksaSyarat($this->mahasiswa->fresh());

        expect($syarat->memenuhi())->toBeTrue()
            ->and($syarat->rincian)->toHaveCount(5)
            ->and(collect($syarat->rincian)->pluck('kode'))->not->toContain('tugas_akhir');
    });
});

describe('penetapan kelulusan', function () {
    beforeEach(function () {
        luluskanMk($this->mahasiswa, 6, 'A', 4.0);
        luluskanMk($this->mahasiswa, 6, 'B', 3.0);
        selesaikanTugasAkhir($this->mahasiswa);
    });

    it('mengambil judul dari catatan tugas akhir, bukan dari ketikan', function () {
        // Inti dari modul tugas akhir. Judul yang diketik ulang di sini tidak
        // pernah ditandatangani pembimbing dan tidak pernah diuji siapa pun —
        // tidak ada apa pun untuk mencocokkannya.
        $y = $this->yudisium->ajukan($this->mahasiswa, $this->term, 'Judul Yang Diketik Sembarangan');

        expect($y->judul_tugas_akhir)->toBe('Rancang Bangun Sistem Informasi Akademik');
    });

    it('menolak pengajuan bila syarat belum terpenuhi', function () {
        $lain = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);

        expect(fn () => $this->yudisium->ajukan($lain, $this->term))
            ->toThrow(AturanAkademikException::class, 'Syarat kelulusan belum terpenuhi');
    });

    it('menetapkan kelulusan dan membuat record alumni', function () {
        $y = $this->yudisium->ajukan($this->mahasiswa, $this->term, 'Sistem Informasi Akademik Terbuka');
        $hasil = $this->yudisium->tetapkan($y, $this->staff, 'SK/2026/001');

        expect($hasil->status)->toBe('ditetapkan')
            ->and($hasil->nomor_sk)->toBe('SK/2026/001')
            ->and((float) $hasil->ipk)->toBe(3.5)
            ->and($hasil->predikat)->toBe('Sangat Memuaskan')
            ->and($this->mahasiswa->fresh()->status)->toBe(StudentStatus::Lulus)
            ->and(Alumni::where('mahasiswa_id', $this->mahasiswa->id)->exists())->toBeTrue();
    });

    it('menerbitkan event student.graduated untuk tracer study IKU 1', function () {
        BridgeConsumer::create([
            'nama' => 'Open Campus',
            'slug' => 'oc',
            'scopes' => ['graduates.read'],
            'webhook_url' => 'https://oc.test/hook',
            'webhook_events' => ['student.graduated', 'student.status_changed'],
            'is_active' => true,
        ]);

        config(['bridge.enabled' => true]);

        $y = $this->yudisium->ajukan($this->mahasiswa, $this->term);
        $this->yudisium->tetapkan($y, $this->staff);

        expect(BridgeWebhookDelivery::where('event', 'student.graduated')->exists())->toBeTrue();
    });

    it('memeriksa ulang syarat saat penetapan, bukan memercayai pengajuan', function () {
        $y = $this->yudisium->ajukan($this->mahasiswa, $this->term);

        // Berminggu-minggu dapat berlalu antara pengajuan dan penetapan.
        Tagihan::create([
            'nomor' => 'INV/2',
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'keterangan' => 'Wisuda',
            'total' => 500_000,
            'terbayar' => 0,
            'jatuh_tempo' => now(),
        ]);

        expect(fn () => $this->yudisium->tetapkan($y, $this->staff))
            ->toThrow(AturanAkademikException::class, 'tidak lagi terpenuhi');
    });

    it('menolak penetapan ganda', function () {
        $y = $this->yudisium->ajukan($this->mahasiswa, $this->term);
        $this->yudisium->tetapkan($y, $this->staff);

        expect(fn () => $this->yudisium->tetapkan($y->fresh(), $this->staff))
            ->toThrow(AturanAkademikException::class, 'sudah ditetapkan');
    });

    it('mengembalikan status mahasiswa saat penetapan dibatalkan', function () {
        $y = $this->yudisium->ajukan($this->mahasiswa, $this->term);
        $this->yudisium->tetapkan($y, $this->staff);

        $this->yudisium->batalkan($y->fresh(), $this->staff, 'Kesalahan input nomor SK.');

        expect($this->mahasiswa->fresh()->status)->toBe(StudentStatus::Aktif)
            ->and(Yudisium::find($y->id)->status)->toBe('diajukan');
    });

    it('mewajibkan alasan pada pembatalan', function () {
        $y = $this->yudisium->ajukan($this->mahasiswa, $this->term);
        $this->yudisium->tetapkan($y, $this->staff);

        expect(fn () => $this->yudisium->batalkan($y->fresh(), $this->staff, ''))
            ->toThrow(AturanAkademikException::class, 'wajib disertai alasan');
    });
});

describe('kandidat', function () {
    it('mendaftar mahasiswa yang sudah memenuhi syarat tapi belum diajukan', function () {
        luluskanMk($this->mahasiswa, 12, 'A', 4.0);
        selesaikanTugasAkhir($this->mahasiswa);

        $belumCukup = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);
        luluskanMk($belumCukup, 3, 'A', 4.0);

        $kandidat = $this->yudisium->kandidat();

        expect($kandidat)->toHaveCount(1)
            ->and($kandidat->first()['mahasiswa']->id)->toBe($this->mahasiswa->id);
    });

    it('tetap menemukan kandidat dari prodi yang ambang SKS-nya di bawah default', function () {
        // `kandidat()` menyaring kasar dengan agregat SKS sebelum menjalankan
        // daftar periksa penuh, supaya layar yudisium tidak membaca seluruh
        // tabel nilai satu kampus. Bila penyaring itu memakai ambang global
        // alih-alih ambang terendah yang benar-benar berlaku, seluruh mahasiswa
        // dari prodi berambang lebih rendah hilang dari layar tanpa jejak:
        // memenuhi syarat, tetapi tidak pernah muncul untuk ditetapkan.
        expect((int) config('academic.graduation.min_credits'))
            ->toBeGreaterThan((int) $this->prodi->sks_lulus);

        luluskanMk($this->mahasiswa, 12, 'A', 4.0);
        selesaikanTugasAkhir($this->mahasiswa);

        expect($this->yudisium->kandidat())->toHaveCount(1);
    });
});
