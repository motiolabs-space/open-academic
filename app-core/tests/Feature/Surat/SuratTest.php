<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\JenisSurat;
use App\Enums\SemesterType;
use App\Enums\StatusSurat;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Staff;
use App\Models\Surat\Surat;
use App\Services\Surat\SuratPdfService;
use App\Services\Surat\SuratService;
use App\Services\Surat\VerifikasiSurat;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create();

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('baak');

    $this->mahasiswa = mahasiswaSurat();

    $this->surat = app(SuratService::class);
    $this->verifikasi = app(VerifikasiSurat::class);
});

function mahasiswaSurat(StudentStatus $status = StudentStatus::Aktif): Mahasiswa
{
    $mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => test()->prodi->id,
        'status' => $status,
    ]);

    $mahasiswa->assignRole('mahasiswa');

    StatusMahasiswa::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'semester_ke' => 5,
        'status' => $status,
        'sks_semester' => 20,
        'sks_kumulatif' => 100,
        'ips' => 3.4,
        'ipk' => 3.4,
    ]);

    return $mahasiswa->fresh();
}

/** Meluluskan mahasiswa langsung ke tabel, tanpa daftar periksa yudisium. */
function luluskanUntukSurat(Mahasiswa $mahasiswa): Yudisium
{
    $yudisium = Yudisium::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'nomor_sk' => 'SK/YUD/2026/001',
        'tanggal_yudisium' => now()->subMonth(),
        'tanggal_lulus' => now()->subMonth(),
        'total_sks' => 144,
        'ipk' => 3.62,
        'predikat' => 'Dengan Pujian',
        'judul_tugas_akhir' => 'Rancang Bangun Sistem Verifikasi Dokumen Akademik',
        'status' => 'ditetapkan',
        'ditetapkan_at' => now()->subMonth(),
    ]);

    $mahasiswa->update(['status' => StudentStatus::Lulus]);

    return $yudisium;
}

describe('swalayan', function () {
    it('menerbitkan surat aktif kuliah seketika, lengkap dengan nomor', function () {
        // Antrean loket tertinggi. Kampus tidak sedang memutuskan apa pun di
        // sini — ia hanya membacakan kolom status.
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);

        expect($surat->status)->toBe(StatusSurat::Diterbitkan)
            ->and($surat->nomor)->not->toBeNull()
            ->and($surat->diterbitkan_by_staff_id)->toBeNull()
            ->and($surat->berlaku_sampai)->not->toBeNull();
    });

    it('menahan surat pengantar untuk diputus manusia', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::Pengantar, 'Penelitian tugas akhir.');

        expect($surat->status)->toBe(StatusSurat::Diajukan)
            ->and($surat->nomor)->toBeNull();
    });

    it('menolak surat aktif kuliah untuk mahasiswa yang tidak aktif', function () {
        $cuti = mahasiswaSurat(StudentStatus::Cuti);

        expect(fn () => $this->surat->ajukan($cuti, JenisSurat::AktifKuliah))
            ->toThrow(AturanAkademikException::class, 'menyatakan mahasiswa sedang aktif');
    });

    it('mewajibkan keperluan pada surat pengantar', function () {
        expect(fn () => $this->surat->ajukan($this->mahasiswa, JenisSurat::Pengantar))
            ->toThrow(AturanAkademikException::class, 'wajib menyebutkan keperluannya');
    });

    it('menahan surat aktif kuliah bila kampus menyalakan aturan tunggakan', function () {
        config(['surat.syarat.tahan_bila_menunggak' => true]);

        Tagihan::create([
            'nomor' => 'INV/1',
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'keterangan' => 'UKT',
            'total' => 5_000_000,
            'terbayar' => 0,
            'status' => InvoiceStatus::BelumBayar,
            'jatuh_tempo' => now()->subWeek(),
        ]);

        expect(fn () => $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah))
            ->toThrow(AturanAkademikException::class, 'melewati jatuh tempo');
    });

    it('tidak menahan karena tagihan yang belum jatuh tempo', function () {
        // Memblokir setiap tagihan yang belum lunas akan menolak surat kepada
        // semua orang di pekan pertama semester — termasuk yang membutuhkannya
        // untuk mencairkan beasiswa yang akan membayar tagihan itu.
        config(['surat.syarat.tahan_bila_menunggak' => true]);

        Tagihan::create([
            'nomor' => 'INV/2',
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'keterangan' => 'UKT',
            'total' => 5_000_000,
            'terbayar' => 0,
            'status' => InvoiceStatus::BelumBayar,
            'jatuh_tempo' => now()->addMonth(),
        ]);

        expect(fn () => $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah))
            ->not->toThrow(AturanAkademikException::class);
    });
});

describe('penomoran', function () {
    it('berurutan per jenis dan per tahun', function () {
        $a = $this->surat->ajukan(mahasiswaSurat(), JenisSurat::AktifKuliah);
        $b = $this->surat->ajukan(mahasiswaSurat(), JenisSurat::AktifKuliah);

        expect($a->nomor_urut)->toBe(1)
            ->and($b->nomor_urut)->toBe(2)
            ->and($a->tahun)->toBe((int) now()->year);
    });

    it('memisahkan urutan antar jenis', function () {
        $aktif = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);

        $lulusan = mahasiswaSurat();
        luluskanUntukSurat($lulusan);
        $skl = $this->surat->ajukan($lulusan->fresh(), JenisSurat::KeteranganLulus);
        $skl = $this->surat->terbitkan($skl, $this->staf);

        expect($aktif->nomor_urut)->toBe(1)
            ->and($skl->nomor_urut)->toBe(1)
            ->and($aktif->nomor)->not->toBe($skl->nomor);
    });

    it('tidak memakai nomor untuk permohonan yang ditolak', function () {
        // Lubang pada deret adalah pertanyaan yang harus dijawab seseorang saat
        // audit, bertahun-tahun kemudian, tanpa ada yang mengingatnya.
        $ditolak = $this->surat->ajukan($this->mahasiswa, JenisSurat::Pengantar, 'Magang.');
        $this->surat->tolak($ditolak, $this->staf, 'Instansi tujuan tidak jelas.');

        $berikut = $this->surat->ajukan(mahasiswaSurat(), JenisSurat::AktifKuliah);

        expect($ditolak->fresh()->nomor)->toBeNull()
            ->and($berikut->nomor_urut)->toBe(1);
    });

    it('tidak pernah memakai ulang nomor surat yang dihapus', function () {
        // Nomor yang pernah tercetak sudah meninggalkan gedung. Memberikannya
        // kepada surat lain berarti dua kertas asli mengaku identitas yang sama.
        $pertama = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);
        $pertama->delete();

        $kedua = $this->surat->ajukan(mahasiswaSurat(), JenisSurat::AktifKuliah);

        expect($kedua->nomor_urut)->toBe(2);
    });
});

describe('snapshot fakta', function () {
    it('membekukan keadaan saat terbit, bukan saat dibaca', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);

        expect($surat->isi('status'))->toBe('Aktif');

        // Mahasiswanya berhenti kuliah bulan berikutnya.
        $this->mahasiswa->update(['status' => StudentStatus::DropOut]);

        // Suratnya tetap menjadi catatan yang jujur tentang bulan lalu.
        expect($surat->fresh()->isi('status'))->toBe('Aktif')
            ->and($surat->fresh()->isi('nama'))->toBe($this->mahasiswa->nama);
    });

    it('tidak pernah memuat NIK', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);

        expect(array_keys((array) $surat->konten))->not->toContain('nik')
            ->and(json_encode($surat->konten))->not->toContain($this->mahasiswa->nik ?? 'xxxxx');
    });
});

describe('verifikasi', function () {
    it('menemukan surat lewat uuid', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);

        $laporan = $this->verifikasi->laporan($this->verifikasi->lewatUuid($surat->uuid));

        expect($laporan['asli'])->toBeTrue()
            ->and($laporan['berlaku'])->toBeTrue()
            ->and($laporan['nomor'])->toBe($surat->nomor);
    });

    it('menuntut nomor dan NIM sekaligus pada pencarian manual', function () {
        // Deret nomor memang bisa ditebak. Endpoint publik yang mengonfirmasi
        // nama dari nomor tebakan adalah direktori semua orang yang pernah
        // dikirimi surat oleh kampus.
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);

        expect($this->verifikasi->lewatNomor($surat->nomor, $this->mahasiswa->nim))->not->toBeNull()
            ->and($this->verifikasi->lewatNomor($surat->nomor, 'nim-salah'))->toBeNull();
    });

    it('tidak menemukan apa pun untuk uuid yang dikarang', function () {
        expect($this->verifikasi->lewatUuid('00000000-0000-0000-0000-000000000000'))->toBeNull();
    });

    it('tidak menemukan permohonan yang belum terbit', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::Pengantar, 'Magang.');

        expect($this->verifikasi->lewatUuid($surat->uuid))->toBeNull();
    });

    it('menyatakan surat yang dicabut sebagai asli tetapi tidak berlaku', function () {
        /*
         * Pembedaan yang menjadi alasan pencabutan tidak dilakukan dengan
         * menghapus baris. Seseorang memegang kertasnya; jawaban "tidak
         * ditemukan" akan terbaca sebagai pemalsuan dan menempatkan pemegangnya
         * pada posisi bersalah.
         */
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);
        $this->surat->cabut($surat, $this->staf, 'Diterbitkan atas data yang keliru.');

        $laporan = $this->verifikasi->laporan($this->verifikasi->lewatUuid($surat->uuid));

        expect($laporan['asli'])->toBeTrue()
            ->and($laporan['berlaku'])->toBeFalse()
            ->and($laporan['dicabut'])->toBeTrue()
            ->and($laporan['catatan'])->toContain('dicabut');
    });

    it('menyatakan surat kedaluwarsa sebagai asli tetapi tidak lagi menggambarkan keadaan', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);
        $surat->update(['berlaku_sampai' => now()->subDay()]);

        $laporan = $this->verifikasi->laporan($surat->fresh());

        expect($laporan['asli'])->toBeTrue()
            ->and($laporan['berlaku'])->toBeFalse()
            ->and($laporan['kedaluwarsa'])->toBeTrue()
            ->and($laporan['catatan'])->toContain('masa berlakunya sudah lewat');
    });

    it('tidak membocorkan apa pun di luar yang tercetak pada dokumen', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);
        $laporan = $this->verifikasi->laporan($surat);

        expect(array_keys($laporan))->not->toContain('nik')
            ->and(array_keys($laporan))->not->toContain('alamat')
            ->and(array_keys($laporan))->not->toContain('ipk');
    });
});

describe('pencabutan', function () {
    it('menolak mencabut surat yang belum terbit', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::Pengantar, 'Magang.');

        expect(fn () => $this->surat->cabut($surat, $this->staf, 'Salah.'))
            ->toThrow(AturanAkademikException::class, 'sudah terbit');
    });

    it('mewajibkan alasan', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);

        expect(fn () => $this->surat->cabut($surat, $this->staf, ''))
            ->toThrow(AturanAkademikException::class, 'wajib disertai alasan');
    });

    it('mempertahankan nomornya', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);
        $nomor = $surat->nomor;

        $this->surat->cabut($surat, $this->staf, 'Data keliru.');

        expect($surat->fresh()->nomor)->toBe($nomor);
    });
});

describe('memeriksa ulang saat menerbitkan', function () {
    it('menolak menerbitkan bila syaratnya sudah tidak terpenuhi', function () {
        // Berminggu-minggu bisa berlalu antara permohonan dan penerbitan.
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::Pengantar, 'Magang.');

        $this->mahasiswa->update(['status' => StudentStatus::Cuti]);

        expect(fn () => $this->surat->terbitkan($surat->fresh(), $this->staf))
            ->toThrow(AturanAkademikException::class, 'tidak lagi terpenuhi');
    });
});

describe('SKPI', function () {
    it('terbit sendiri saat kelulusan ditetapkan', function () {
        $lulusan = mahasiswaSurat();
        luluskanUntukSurat($lulusan);

        $skpi = $this->surat->terbitkanSkpi($lulusan->fresh(), $this->staf);

        expect($skpi)->not->toBeNull()
            ->and($skpi->jenis)->toBe(JenisSurat::Skpi)
            ->and($skpi->nomor)->not->toBeNull()
            ->and($skpi->berlaku_sampai)->toBeNull();
    });

    it('tidak menerbitkan yang kedua', function () {
        $lulusan = mahasiswaSurat();
        luluskanUntukSurat($lulusan);

        $pertama = $this->surat->terbitkanSkpi($lulusan->fresh(), $this->staf);
        $kedua = $this->surat->terbitkanSkpi($lulusan->fresh(), $this->staf);

        expect($kedua->id)->toBe($pertama->id)
            ->and(Surat::where('jenis', JenisSurat::Skpi->value)->count())->toBe(1);
    });

    it('tidak terbit untuk yang belum lulus', function () {
        expect($this->surat->terbitkanSkpi($this->mahasiswa, $this->staf))->toBeNull();
    });

    it('memuat jenjang KKNI dan menyatakan CPL kosong secara terbuka', function () {
        // Bagian yang hilang tanpa keterangan terbaca sebagai kelalaian
        // pemegang ijazahnya, bukan kelalaian penerbitnya.
        $lulusan = mahasiswaSurat();
        luluskanUntukSurat($lulusan);

        $skpi = $this->surat->terbitkanSkpi($lulusan->fresh(), $this->staf);

        expect($skpi->isi('kkni'))->toBeInt()
            ->and($skpi->isi('cpl_kosong'))->toBeTrue()
            ->and($skpi->isi('jenjang_en'))->not->toBeNull();
    });

    it('menolak diajukan mahasiswa', function () {
        expect(fn () => $this->surat->ajukan($this->mahasiswa, JenisSurat::Skpi))
            ->toThrow(AturanAkademikException::class, 'bukan atas permintaan');
    });
});

describe('berkas PDF', function () {
    it('merender setiap jenis surat tanpa galat', function () {
        $pdf = app(SuratPdfService::class);

        $aktif = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);

        $pengantar = $this->surat->ajukan(mahasiswaSurat(), JenisSurat::Pengantar, 'Penelitian lapangan.');
        $pengantar = $this->surat->terbitkan($pengantar, $this->staf);

        $lulusan = mahasiswaSurat();
        luluskanUntukSurat($lulusan);
        $skl = $this->surat->terbitkan(
            $this->surat->ajukan($lulusan->fresh(), JenisSurat::KeteranganLulus),
            $this->staf,
        );
        $skpi = $this->surat->terbitkanSkpi($lulusan->fresh(), $this->staf);

        foreach ([$aktif, $pengantar, $skl, $skpi] as $surat) {
            expect(strlen($pdf->pdf($surat)->output()))->toBeGreaterThan(1000);
        }
    });

    it('menyematkan QR yang menunjuk halaman verifikasi', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);
        $data = app(SuratPdfService::class)->data($surat);

        expect($data['tautanVerifikasi'])->toContain($surat->uuid)
            ->and($data['qr'])->toStartWith('data:image/png;base64,');
    });

    it('menolak mengunduh surat yang belum terbit', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::Pengantar, 'Magang.');

        expect(fn () => app(SuratPdfService::class)->pdf($surat))
            ->toThrow(AturanAkademikException::class, 'berstatus terbit');
    });
});
