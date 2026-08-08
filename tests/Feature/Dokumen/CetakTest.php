<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\KrsStatus;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\System\Setting;
use App\Services\Dokumen\CetakService;
use App\Services\Dokumen\PengaturanDokumen;
use Database\Seeders\RolePermissionSeeder;

/**
 * The four routinely printed documents, and the settings behind them.
 *
 * What is tested here is the gates and the authorisation, not the typography:
 * a register lists every student in a class by name, and an exam card is a
 * document a campus deliberately withholds.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();

    $this->prodi = Prodi::factory()->create();
    $this->kurikulum = Kurikulum::factory()->create(['prodi_id' => $this->prodi->id]);

    $this->cetak = app(CetakService::class);
});

function mahasiswaCetak(): Mahasiswa
{
    $mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => test()->prodi->id,
        'kurikulum_id' => test()->kurikulum->id,
        'status' => StudentStatus::Aktif,
    ]);

    $mahasiswa->assignRole('mahasiswa');

    return $mahasiswa;
}

function kelasCetak(?Dosen $dosen = null): KelasKuliah
{
    $mk = MataKuliah::factory()->create(['prodi_id' => test()->prodi->id, 'sks' => 3]);

    test()->kurikulum->mataKuliah()->attach($mk->id, ['semester' => 1, 'jenis' => 'wajib']);

    $kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => test()->term->id,
        'mata_kuliah_id' => $mk->id,
        'prodi_id' => test()->prodi->id,
        'sks' => 3,
        'kuota' => 40,
    ]);

    if ($dosen !== null) {
        $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu', 'porsi_sks' => 3]);
    }

    return $kelas;
}

/** An unpaid invoice, the reason a campus withholds an exam card. */
function tagihanCetak(Mahasiswa $mahasiswa, int $total, int $terbayar = 0): Tagihan
{
    return Tagihan::create([
        'nomor' => 'INV-CETAK-'.uniqid(),
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'keterangan' => 'Biaya kuliah uji',
        'total' => $total,
        'terbayar' => $terbayar,
        'status' => $terbayar > 0 ? InvoiceStatus::Sebagian : InvoiceStatus::BelumBayar,
        'jatuh_tempo' => now()->addDays(30),
    ]);
}

function krsCetak(Mahasiswa $mahasiswa, KrsStatus $status = KrsStatus::Disetujui): Krs
{
    $krs = Krs::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'semester_ke' => 1,
        'status' => $status,
        'total_sks' => 3,
        'batas_sks' => 24,
    ]);

    KrsDetail::create([
        'krs_id' => $krs->id,
        'kelas_kuliah_id' => kelasCetak()->id,
        'sks' => 3,
    ]);

    return $krs->refresh();
}

describe('pengaturan dokumen', function () {
    it('memakai bawaan config sebelum kampus mengubah apa pun', function () {
        $dok = app(PengaturanDokumen::class)->untuk('ktm');

        expect($dok['judul'])->toBe('Kartu Tanda Mahasiswa')
            ->and($dok['bertanda_tangan'])->toBeTrue();
    });

    it('mendahulukan setelan kampus atas bawaan', function () {
        Setting::put(PengaturanDokumen::GRUP, 'ktm_judul', 'Kartu Mahasiswa');

        expect(app(PengaturanDokumen::class)->untuk('ktm')['judul'])->toBe('Kartu Mahasiswa');
    });

    it('tidak mencetak penandatangan pada dokumen yang ditandatangani di ruangan', function () {
        /*
         * Absensi dan jurnal ditandatangani di kertas oleh yang hadir. Nama
         * pejabat tercetak di situ bukan sekadar mubazir — ia keliru, karena
         * orang itu tidak ada di ruangan.
         */
        $dok = app(PengaturanDokumen::class)->untuk('absensi');

        expect($dok['bertanda_tangan'])->toBeFalse()
            ->and($dok['penandatangan'])->toBeNull();
    });

    it('menolak jenis dokumen yang tidak dikenal alih-alih memberi halaman kosong', function () {
        // Salah ketik jenis dokumen kalau didiamkan menghasilkan kop tanpa judul
        // dan tanpa catatan kaki — terbaca sebagai bug tata letak, lalu dikejar
        // sejam di berkas templat.
        expect(fn () => app(PengaturanDokumen::class)->untuk('kartu_sakti'))
            ->toThrow(InvalidArgumentException::class, 'tidak dikenal');
    });

    it('menyimpan setelan dokumen lewat layar admin', function () {
        $staf = Staff::factory()->create();
        $staf->assignRole('super-admin');

        $this->actingAs($staf, 'staff')
            ->put('/admin/pengaturan/dokumen', [
                'kop_alamat' => 'Jalan Merdeka 1, Bandung',
                'ktm_ttd_nama' => 'Dr. Siti Rahayu',
            ])
            ->assertRedirect();

        $dok = app(PengaturanDokumen::class)->untuk('ktm');

        expect($dok['alamat'])->toBe('Jalan Merdeka 1, Bandung')
            ->and($dok['penandatangan']['nama'])->toBe('Dr. Siti Rahayu');
    });
});

describe('kartu ujian', function () {
    it('ditahan ketika rencana studi belum disetujui', function () {
        $krs = krsCetak(mahasiswaCetak(), KrsStatus::Draft);

        $hasil = $this->cetak->kelayakanKartuUjian($krs);

        expect($hasil['layak'])->toBeFalse()
            ->and($hasil['alasan'][0])->toContain('belum disetujui');
    });

    it('ditahan ketika masih ada tagihan belum lunas', function () {
        $mahasiswa = mahasiswaCetak();

        tagihanCetak($mahasiswa, 500_000_00, 100_000_00);

        $hasil = $this->cetak->kelayakanKartuUjian(krsCetak($mahasiswa));

        expect($hasil['layak'])->toBeFalse()
            ->and($hasil['alasan'][0])->toContain('belum lunas');
    });

    it('dapat dicetak ketika kampus memilih tidak menahan penunggak', function () {
        // Menahan atau tidak itu kebijakan, bukan fakta — sebagian kampus
        // menahan, sebagian tidak, sebagian hanya untuk UAS.
        config(['dokumen.kartu_ujian.tahan_bila_menunggak' => false]);

        $mahasiswa = mahasiswaCetak();

        tagihanCetak($mahasiswa, 500_000_00);

        expect($this->cetak->kelayakanKartuUjian(krsCetak($mahasiswa))['layak'])->toBeTrue();
    });

    it('menyebutkan alasan penahanan kepada mahasiswanya', function () {
        // Kartu yang sekadar tidak muncul mengirim mahasiswa bertanya ke loket.
        $mahasiswa = mahasiswaCetak();
        krsCetak($mahasiswa, KrsStatus::Draft);

        $this->actingAs($mahasiswa, 'mahasiswa')
            ->get('/mahasiswa/kartu-ujian')
            ->assertRedirect();

        expect(session('galat'))->toContain('belum disetujui');
    });

    it('mencetak kartu untuk mahasiswa yang memenuhi syarat', function () {
        // Jalur suksesnya, bukan hanya penolakannya: tanpa ini templatnya bisa
        // saja tidak pernah dirender sama sekali dan tetap "hijau".
        $mahasiswa = mahasiswaCetak();
        krsCetak($mahasiswa);

        $this->actingAs($mahasiswa, 'mahasiswa')
            ->get('/mahasiswa/kartu-ujian')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('melempar ketika servicenya dipanggil langsung untuk yang tidak layak', function () {
        // Gerbangnya ada di service, bukan hanya di controller — jalur lain
        // yang memanggilnya kelak tetap terjaga.
        $krs = krsCetak(mahasiswaCetak(), KrsStatus::Draft);

        expect(fn () => $this->cetak->kartuUjian($krs))
            ->toThrow(AturanAkademikException::class);
    });
});

describe('otorisasi cetak', function () {
    it('menolak dosen mencetak absensi kelas yang tidak diampunya', function () {
        /*
         * Daftar hadir memuat nama seluruh peserta — persis jenis daftar yang
         * tidak boleh dicetak siapa pun yang menebak id kelas.
         */
        $pengampu = Dosen::factory()->create(['prodi_id' => $this->prodi->id]);
        $lain = Dosen::factory()->create(['prodi_id' => $this->prodi->id]);
        $lain->assignRole('dosen');

        $kelas = kelasCetak($pengampu);

        $this->actingAs($lain, 'dosen')
            ->get('/dosen/kelas/'.$kelas->uuid.'/absensi')
            ->assertForbidden();
    });

    it('mengizinkan dosen pengampu mencetak absensi kelasnya', function () {
        $dosen = Dosen::factory()->create(['prodi_id' => $this->prodi->id]);
        $dosen->assignRole('dosen');

        $kelas = kelasCetak($dosen);

        $this->actingAs($dosen, 'dosen')
            ->get('/dosen/kelas/'.$kelas->uuid.'/absensi')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('menolak staf tanpa izin kelas mencetak jurnal', function () {
        $staf = Staff::factory()->create();
        $staf->assignRole('keuangan');

        $this->actingAs($staf, 'staff')
            ->get('/admin/cetak/jurnal/'.kelasCetak()->uuid)
            ->assertForbidden();
    });
});

describe('isi dokumen', function () {
    it('tidak pernah mencetak NIK atau alamat rumah pada KTM', function () {
        /*
         * Kartu adalah dokumen yang paling sering hilang, dan segala yang
         * tercetak padanya ikut tersebar bersamanya.
         *
         * Diperiksa pada HTML sebelum dirender, bukan pada PDF-nya: dompdf
         * memampatkan aliran teksnya, jadi mencari string di dalam PDF akan
         * selalu "lolos" dan tidak membuktikan apa pun.
         */
        $mahasiswa = mahasiswaCetak();
        $mahasiswa->forceFill([
            'nik' => '3273010101000001',
            'alamat' => 'Jalan Rahasia Nomor Tujuh',
        ])->save();

        $html = view('pdf.cetak.ktm', [
            'dok' => app(PengaturanDokumen::class)->untuk('ktm'),
            'mahasiswa' => $mahasiswa->fresh()->load('prodi.fakultas'),
            'subjudul' => null,
            'qr' => null,
            'blokSendiri' => true,
        ])->render();

        expect($html)
            ->not->toContain('3273010101000001')
            ->not->toContain('Jalan Rahasia Nomor Tujuh')

            // Yang memang harus ada, supaya tes ini tidak lulus hanya karena
            // halamannya kebetulan kosong.
            ->toContain($mahasiswa->nim)
            ->toContain($mahasiswa->nama);
    });

    it('mencetak jurnal dengan baris pertemuan yang belum terisi', function () {
        // Jaraknya justru intinya: empat dari empat belas terisi adalah temuan
        // yang dicari monitoring, dan lembar yang menyembunyikan baris kosongnya
        // ikut menyembunyikan temuan itu.
        $dosen = Dosen::factory()->create(['prodi_id' => $this->prodi->id]);
        $kelas = kelasCetak($dosen);

        expect($this->cetak->jurnal($kelas)->output())->toBeString()->not->toBeEmpty();
    });

    it('mencetak absensi untuk kelas yang belum berpeserta', function () {
        expect($this->cetak->absensi(kelasCetak())->output())->toBeString()->not->toBeEmpty();
    });
});
