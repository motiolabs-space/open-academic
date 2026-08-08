<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\JenisDokumenAkuntansi;
use App\Enums\JenisItemTagihan;
use App\Enums\SemesterType;
use App\Enums\StatusDokumenAkuntansi;
use App\Enums\UserRole;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Akuntansi\DokumenAkuntansi;
use App\Models\Akuntansi\PemetaanAkuntansi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Models\Sdm\Staff;
use App\Services\Akuntansi\Contracts\AkuntansiClientInterface;
use App\Services\Akuntansi\HasilKirim;
use App\Services\Akuntansi\PengirimAkuntansi;
use App\Services\Akuntansi\PenjurnalanService;
use App\Services\Keuangan\PembayaranService;
use App\Services\Keuangan\PotonganService;
use App\Support\Akuntansi;
use App\Support\Navigation;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    // Bawaannya nonaktif, jadi hampir setiap tes di berkas ini menyalakannya
    // lebih dulu — yang dengan sendirinya sudah membuktikan modulnya opt-in.
    config([
        'akuntansi.driver' => Akuntansi::PALSU,
        'akuntansi.perlakuan.beasiswa' => 'bruto',
    ]);

    // Resolved once and kept: the fake accumulates what it was sent, and a
    // second instance would not remember any of it.
    $this->klien = app(AkuntansiClientInterface::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create();

    $this->mahasiswa = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);

    $this->penjurnalan = app(PenjurnalanService::class);
    $this->pengirim = app(PengirimAkuntansi::class);
});

function tagihanAkuntansi(int $total = 5_000_000, int $potongan = 0): Tagihan
{
    $tagihan = Tagihan::create([
        'nomor' => 'INV-UJI-'.uniqid(),
        'mahasiswa_id' => test()->mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'keterangan' => 'Biaya kuliah uji',
        'total' => $total - $potongan,
        'terbayar' => 0,
        'status' => InvoiceStatus::BelumBayar,
        'jatuh_tempo' => now()->addDays(30),
    ]);

    TagihanItem::create([
        'tagihan_id' => $tagihan->id,
        'jenis' => JenisItemTagihan::Tagihan,
        'nama' => 'UKT',
        'nominal' => $total,
    ]);

    if ($potongan > 0) {
        TagihanItem::create([
            'tagihan_id' => $tagihan->id,
            'jenis' => JenisItemTagihan::Potongan,
            'nama' => 'Beasiswa Prestasi',
            'nominal' => -$potongan,
        ]);
    }

    return $tagihan->fresh(['item', 'mahasiswa']);
}

describe('opsional dan tidak mengikat', function () {
    beforeEach(function () {
        config(['akuntansi.driver' => Akuntansi::NONAKTIF]);
    });

    it('nonaktif secara bawaan', function () {
        /*
         * Berkas config-nya dibaca ulang langsung, bukan lewat config() yang
         * sudah disetel beforeEach. Yang diuji di sini adalah nilai bawaan yang
         * *dikirimkan* repo ini — instalasi baru tidak boleh diam-diam mulai
         * menumpuk dokumen untuk sistem yang belum tentu dipakai kampusnya.
         */
        $bawaan = require base_path('config/akuntansi.php');

        expect($bawaan['driver'])->toBe(Akuntansi::NONAKTIF);
    });

    it('membaca AKUNTANSI_DRIVER kosong sebagai nonaktif', function () {
        // `AKUNTANSI_DRIVER=` di .env menghasilkan string kosong, bukan kunci
        // yang hilang. Membacanya sebagai driver tak dikenal berarti setiap
        // penerbitan tagihan melempar exception.
        config(['akuntansi.driver' => '']);

        expect(Akuntansi::aktif())->toBeFalse()
            ->and(Akuntansi::driver())->toBe(Akuntansi::NONAKTIF);

        $this->penjurnalan->tagihanTerbit(tagihanAkuntansi());

        expect(DokumenAkuntansi::count())->toBe(0);
    });

    it('tidak mencatat apa pun saat tagihan terbit', function () {
        $this->penjurnalan->tagihanTerbit(tagihanAkuntansi(total: 5_000_000, potongan: 1_000_000));

        expect(DokumenAkuntansi::count())->toBe(0);
    });

    it('tidak mencatat apa pun saat pembayaran masuk maupun dibatalkan', function () {
        $tagihan = tagihanAkuntansi(total: 1_000_000);
        $staf = Staff::factory()->create();

        $pembayaran = app(PembayaranService::class)->catatManual($tagihan, 300_000, $staf, 'tunai');
        app(PembayaranService::class)->batalkan($pembayaran, $staf, 'Salah input.');

        expect(DokumenAkuntansi::count())->toBe(0);
    });

    it('membiarkan penagihan berjalan persis seperti sebelum modul ini ada', function () {
        /*
         * Inti dari "tidak mengikat": mematikan integrasi tidak boleh mengubah
         * satu pun angka di sisi Open Academic.
         */
        $tagihan = tagihanAkuntansi(total: 2_000_000);
        $staf = Staff::factory()->create();

        $pembayaran = app(PembayaranService::class)->catatManual($tagihan, 750_000, $staf, 'tunai');

        expect($pembayaran->exists)->toBeTrue()
            ->and($tagihan->refresh()->terbayar)->toBe(750_000)
            ->and($tagihan->sisa())->toBe(1_250_000);
    });

    it('menyembunyikan menu Akuntansi dari sidebar', function () {
        // Menu untuk sistem yang tidak dimiliki kampus hanya mengundang
        // pertanyaan yang tidak punya jawaban berguna.
        $menu = collect(Navigation::for(UserRole::Staff))
            ->flatMap(fn (array $grup): array => $grup['items'])
            ->pluck('label');

        expect($menu)->not->toContain('Integrasi Akuntansi');

        config(['akuntansi.driver' => Akuntansi::PALSU]);

        $menuAktif = collect(Navigation::for(UserRole::Staff))
            ->flatMap(fn (array $grup): array => $grup['items'])
            ->pluck('label');

        expect($menuAktif)->toContain('Integrasi Akuntansi');
    });

    it('membuat perintah terjadwal keluar tanpa mengerjakan apa pun', function () {
        $this->artisan('openacademic:kirim-akuntansi')
            ->expectsOutputToContain('Integrasi akuntansi nonaktif')
            ->assertSuccessful();
    });

    it('tetap membuka layarnya dan menjelaskan cara menyalakan', function () {
        // Rutenya tetap dapat dijangkau: seseorang yang mencari-cari modul ini
        // harus menemukan penjelasan, bukan 404.
        $staf = Staff::factory()->create()->assignRole('super-admin');

        $this->actingAs($staf, 'staff')
            ->get('/admin/akuntansi')
            ->assertOk()
            ->assertSee('AKUNTANSI_DRIVER=palsu', false);
    });
});

describe('pemetaan ke jurnal', function () {
    it('mengantre invoice sebesar tarif penuh, bukan setelah potongan', function () {
        // Perlakuan bruto: pendapatan diakui penuh, potongannya jadi beban.
        // Kalau invoice-nya netto, angka beasiswa lenyap dari laporan.
        $tagihan = tagihanAkuntansi(total: 5_000_000, potongan: 2_000_000);

        $this->penjurnalan->tagihanTerbit($tagihan);

        $invoice = DokumenAkuntansi::where('jenis', JenisDokumenAkuntansi::Invoice->value)->sole();

        expect($invoice->nominal)->toBe(5_000_000)
            ->and($invoice->payload['amount'])->toBe(5_000_000);
    });

    it('membukukan potongan sebagai beban beasiswa terhadap piutang', function () {
        $tagihan = tagihanAkuntansi(total: 5_000_000, potongan: 2_000_000);

        $this->penjurnalan->tagihanTerbit($tagihan);

        $jurnal = DokumenAkuntansi::where('jenis', JenisDokumenAkuntansi::Jurnal->value)->sole();
        $entries = collect($jurnal->payload['entries']);

        expect($jurnal->nominal)->toBe(2_000_000)
            ->and($entries->firstWhere('account_code', config('akuntansi.akun.beban_beasiswa'))['debit'])
            ->toBe(2_000_000)
            ->and($entries->firstWhere('account_code', config('akuntansi.akun.piutang'))['credit'])
            ->toBe(2_000_000);
    });

    it('tidak membukukan beban apa pun pada perlakuan netto', function () {
        config(['akuntansi.perlakuan.beasiswa' => 'netto']);

        $tagihan = tagihanAkuntansi(total: 5_000_000, potongan: 2_000_000);

        $this->penjurnalan->tagihanTerbit($tagihan);

        expect(DokumenAkuntansi::where('jenis', JenisDokumenAkuntansi::Jurnal->value)->count())->toBe(0)
            ->and(DokumenAkuntansi::where('jenis', JenisDokumenAkuntansi::Invoice->value)->sole()->nominal)
            ->toBe(3_000_000);
    });

    it('membukukan dua keringanan bernilai sama sebagai dua dokumen', function () {
        /*
         * Kunci idempotensi dipasang pada baris tagihan, bukan pada nominalnya.
         *
         * Kalau memakai nominal, keringanan kedua yang kebetulan sebesar
         * keringanan pertama akan tertelan sebagai duplikat — kampus sudah
         * memberikan uangnya dan tidak pernah membukukannya.
         */
        $tagihan = tagihanAkuntansi(total: 10_000_000);

        foreach ([1_000_000, 1_000_000] as $nominal) {
            $item = TagihanItem::create([
                'tagihan_id' => $tagihan->id,
                'jenis' => JenisItemTagihan::Potongan,
                'nama' => 'Keringanan',
                'nominal' => -$nominal,
            ]);

            $this->penjurnalan->potonganDiberikan($item->load('tagihan'));
        }

        expect(DokumenAkuntansi::where('jenis', JenisDokumenAkuntansi::Jurnal->value)->count())->toBe(2);
    });

    it('menolak jurnal yang tidak seimbang sebelum sempat mengantre', function () {
        /*
         * Diuji langsung karena tidak ada jalur publik yang dapat menghasilkannya
         * — dan itu memang tujuannya. easyERP akan menolak jurnal timpang juga,
         * tetapi menemukannya saat kirim mengubah satu kesalahan koding menjadi
         * antrean penuh kegagalan yang harus ditelusuri satu per satu.
         */
        $periksa = function (array $entries): void {
            $this->pastikanSeimbang($entries);
        };

        expect(fn () => $periksa->call(app(PenjurnalanService::class), [
            ['debit' => 100, 'credit' => 0],
            ['debit' => 0, 'credit' => 90],
        ]))->toThrow(LogicException::class, 'tidak seimbang');
    });
});

describe('idempotensi', function () {
    it('tidak mengantre dokumen yang sama dua kali', function () {
        // Penerbitan tagihan yang diulang setelah gagal separuh jalan tidak
        // menambah apa pun.
        $tagihan = tagihanAkuntansi();

        $this->penjurnalan->tagihanTerbit($tagihan);
        $this->penjurnalan->tagihanTerbit($tagihan);

        expect(DokumenAkuntansi::count())->toBe(1);
    });

    it('mengirim kunci idempotensi yang diturunkan dari peristiwanya', function () {
        $tagihan = tagihanAkuntansi();

        $this->penjurnalan->tagihanTerbit($tagihan);
        $this->pengirim->jalankan();

        $kunci = collect($this->klien->terkirim)->pluck('kunci');

        expect($kunci)->toContain('oa-inv-'.$tagihan->uuid)
            ->and($kunci)->toContain('oa-kontak-'.$this->mahasiswa->uuid);
    });

    it('mengembalikan jawaban pertama untuk kunci yang diulang', function () {
        // Perilaku easyERP ditiru persis. Tiruan yang lebih pemaaf daripada
        // aslinya membiarkan bug kunci ganda lolos dari test suite.
        $pertama = $this->klien->kirim('invoices', ['amount' => 1], 'kunci-sama');
        $kedua = $this->klien->kirim('invoices', ['amount' => 999], 'kunci-sama');

        expect($kedua->easyerpId)->toBe($pertama->easyerpId)
            ->and($this->klien->jumlahKirim('invoices'))->toBe(1);
    });
});

describe('pengiriman', function () {
    it('membuat kontak lebih dulu lalu memakai id-nya pada invoice', function () {
        $tagihan = tagihanAkuntansi();
        $this->penjurnalan->tagihanTerbit($tagihan);

        $this->pengirim->jalankan();

        $invoice = collect($this->klien->payloadUntuk('invoices'))->first();

        expect($this->klien->payloadUntuk('contacts'))->toHaveCount(1)
            ->and($invoice)->toHaveKey('contact_id')

            // uuid mahasiswa tidak boleh ikut terkirim — itu kunci internal,
            // dan payload-nya sudah menyebut orang lewat contact_id.
            ->and($invoice)->not->toHaveKey('kontak_mahasiswa');
    });

    it('memetakan satu mahasiswa sekali saja untuk delapan semester', function () {
        // Tanpa singgahan ini, satu-satunya cara menemukan mahasiswa kembali
        // adalah mencarinya lewat nama — dan dua Muhammad Rizki akhirnya
        // ditagih sebagai satu orang.
        // Semester yang berbeda tiap kali: satu tagihan per mahasiswa per
        // semester dijaga indeks unik, dan itu memang aturannya.
        foreach ([2027, 2028, 2029] as $tahun) {
            $this->term = TahunAkademik::factory()->term($tahun, SemesterType::Ganjil)->create();

            $this->penjurnalan->tagihanTerbit(tagihanAkuntansi());
            $this->pengirim->jalankan();
        }

        expect($this->klien->jumlahKirim('contacts'))->toBe(1)
            ->and(PemetaanAkuntansi::count())->toBe(1);
    });

    it('tidak pernah mengirim NIK maupun alamat rumah', function () {
        $this->mahasiswa->update(['nik' => '3201010101900001', 'alamat' => 'Jalan Rahasia 1']);

        $this->penjurnalan->tagihanTerbit(tagihanAkuntansi());
        $this->pengirim->jalankan();

        $kontak = json_encode($this->klien->payloadUntuk('contacts'));

        expect($kontak)->not->toContain('3201010101900001')
            ->and($kontak)->not->toContain('Jalan Rahasia');
    });

    it('menunda lalu menyerah setelah batas percobaan', function () {
        config(['akuntansi.pengiriman.maks_percobaan' => 3]);

        $this->klien->paksaGagal = HasilKirim::gagal('Server sedang bermasalah.', 503);

        $this->penjurnalan->tagihanTerbit(tagihanAkuntansi());
        $dokumen = DokumenAkuntansi::where('jenis', JenisDokumenAkuntansi::Invoice->value)->sole();

        foreach (range(1, 3) as $i) {
            $dokumen->update(['coba_lagi_setelah' => null]);
            $this->pengirim->kirimSatu($dokumen->refresh());
        }

        expect($dokumen->refresh()->status)->toBe(StatusDokumenAkuntansi::Gagal)
            ->and($dokumen->galat)->toContain('Menyerah setelah 3 percobaan');
    });

    it('menunda alih-alih mematikan invoice saat kontaknya gagal sesaat', function () {
        /*
         * Kegagalan sementara di tengah pembuatan kontak tidak boleh mematikan
         * invoice di belakangnya. Tanpa pembedaan ini, gangguan jaringan lima
         * detik menghasilkan antrean dokumen yang harus disadari dan
         * dikembalikan satu per satu oleh manusia.
         */
        $this->klien->paksaGagal = HasilKirim::gagal('Koneksi terputus.');

        $this->penjurnalan->tagihanTerbit(tagihanAkuntansi());
        $this->pengirim->jalankan();

        $dokumen = DokumenAkuntansi::where('jenis', JenisDokumenAkuntansi::Invoice->value)->sole();

        expect($dokumen->status)->toBe(StatusDokumenAkuntansi::Menunggu)
            ->and($dokumen->coba_lagi_setelah)->not->toBeNull();

        // Dan begitu jaringannya pulih, ia terkirim tanpa campur tangan siapa pun.
        $this->klien->paksaGagal = null;
        $dokumen->update(['coba_lagi_setelah' => null]);
        $this->pengirim->jalankan();

        expect($dokumen->refresh()->status)->toBe(StatusDokumenAkuntansi::Terkirim);
    });

    it('langsung menyerah pada penolakan yang tidak akan berubah', function () {
        /*
         * 422 berarti payload-nya salah — hampir selalu kode akun yang belum ada
         * di seberang. Mengulanginya seratus kali hanya mengubur sebabnya di
         * balik angka percobaan yang membesar.
         */
        $this->klien->paksaGagal = HasilKirim::gagal('Akun 6-1101 tidak ditemukan.', 422);

        $this->penjurnalan->tagihanTerbit(tagihanAkuntansi());
        $this->pengirim->jalankan();

        $dokumen = DokumenAkuntansi::where('jenis', JenisDokumenAkuntansi::Invoice->value)->sole();

        expect($dokumen->status)->toBe(StatusDokumenAkuntansi::Gagal)
            ->and($dokumen->percobaan)->toBe(1)
            ->and($dokumen->galat)->toContain('6-1101');
    });

    it('mengembalikan dokumen gagal ke antrean setelah sebabnya diperbaiki', function () {
        $this->klien->paksaGagal = HasilKirim::gagal('Akun tidak ditemukan.', 422);
        $this->penjurnalan->tagihanTerbit(tagihanAkuntansi());
        $this->pengirim->jalankan();

        $dokumen = DokumenAkuntansi::where('jenis', JenisDokumenAkuntansi::Invoice->value)->sole();

        $this->klien->paksaGagal = null;
        $this->pengirim->ulangi($dokumen);
        $this->pengirim->jalankan();

        expect($dokumen->refresh()->status)->toBe(StatusDokumenAkuntansi::Terkirim);
    });
});

describe('peristiwa keuangan', function () {
    it('mengantre penerimaan kas saat pembayaran dicatat', function () {
        $tagihan = tagihanAkuntansi(total: 1_000_000);
        $staf = Staff::factory()->create();

        app(PembayaranService::class)->catatManual($tagihan, 400_000, $staf, 'tunai');

        $jurnal = DokumenAkuntansi::where('jenis', JenisDokumenAkuntansi::Jurnal->value)->sole();
        $entries = collect($jurnal->payload['entries']);

        expect($entries->firstWhere('account_code', config('akuntansi.akun.kas'))['debit'])->toBe(400_000)
            ->and($entries->firstWhere('account_code', config('akuntansi.akun.piutang'))['credit'])->toBe(400_000);
    });

    it('memisahkan transfer ke akun bank, bukan kas', function () {
        // Salah menaruhnya tidak membuat neraca timpang, tetapi membuat
        // rekonsiliasi bank mustahil.
        $tagihan = tagihanAkuntansi(total: 1_000_000);
        $staf = Staff::factory()->create();

        app(PembayaranService::class)->catatManual($tagihan, 500_000, $staf, 'bca_va');

        $entries = collect(DokumenAkuntansi::where('jenis', JenisDokumenAkuntansi::Jurnal->value)
            ->sole()->payload['entries']);

        expect($entries->pluck('account_code'))->toContain(config('akuntansi.akun.bank'))
            ->and($entries->pluck('account_code'))->not->toContain(config('akuntansi.akun.kas'));
    });

    it('membalik jurnal saat pembayaran dibatalkan, bukan menghapusnya', function () {
        // Kuitansinya pernah terjadi dan pernah dibukukan. Menghapusnya
        // meninggalkan jejak audit yang tidak dapat menjelaskan dirinya, dan
        // tidak mungkin lagi begitu periode di seberang ditutup.
        $tagihan = tagihanAkuntansi(total: 1_000_000);
        $staf = Staff::factory()->create();

        $pembayaran = app(PembayaranService::class)->catatManual($tagihan, 300_000, $staf, 'tunai');
        app(PembayaranService::class)->batalkan($pembayaran, $staf, 'Salah input.');

        $jurnal = DokumenAkuntansi::where('jenis', JenisDokumenAkuntansi::Jurnal->value)
            ->orderBy('id')->get();

        expect($jurnal)->toHaveCount(2);

        $balik = collect($jurnal->last()->payload['entries']);

        expect($balik->firstWhere('account_code', config('akuntansi.akun.piutang'))['debit'])->toBe(300_000)
            ->and($balik->firstWhere('account_code', config('akuntansi.akun.kas'))['credit'])->toBe(300_000);
    });

    it('mengantre keringanan yang diberikan setelah tagihan terbit', function () {
        $tagihan = tagihanAkuntansi(total: 5_000_000);
        $staf = Staff::factory()->create();

        app(PotonganService::class)->keringanan($tagihan, 750_000, 'Musibah keluarga.', $staf);

        $jurnal = DokumenAkuntansi::where('jenis', JenisDokumenAkuntansi::Jurnal->value)->sole();

        expect($jurnal->nominal)->toBe(750_000);
    });

    it('tidak membatalkan pembayaran ketika penjurnalan bermasalah', function () {
        /*
         * Prinsip yang sama dengan Notifier: mencatat peristiwa di buku besar
         * tidak boleh dapat membatalkan peristiwanya. Uangnya sudah diterima.
         */
        config(['akuntansi.akun' => null]); // memaksa penjurnalan meledak

        $tagihan = tagihanAkuntansi(total: 1_000_000);
        $staf = Staff::factory()->create();

        $pembayaran = app(PembayaranService::class)->catatManual($tagihan, 250_000, $staf, 'tunai');

        expect($pembayaran->exists)->toBeTrue()
            ->and($tagihan->refresh()->terbayar)->toBe(250_000);
    });
});

describe('layar & ekspor', function () {
    beforeEach(function () {
        $this->staf = Staff::factory()->create()->assignRole('super-admin');
    });

    it('mengatakan terus terang bahwa driver palsu tidak mengirim apa pun', function () {
        // Instalasi yang mengira dirinya terhubung akan berjalan berbulan-bulan
        // dengan buku besar kosong tanpa ada yang memberi tahu.
        $this->actingAs($this->staf, 'staff')
            ->get('/admin/akuntansi')
            ->assertOk()
            ->assertSee('tidak ada apa pun yang dikirim ke Easy Accounting', false);
    });

    it('mengekspor jurnal seimbang sebagai CSV', function () {
        $this->penjurnalan->tagihanTerbit(tagihanAkuntansi(total: 5_000_000, potongan: 1_000_000));

        $isi = $this->actingAs($this->staf, 'staff')
            ->get('/admin/akuntansi/ekspor/jurnal')
            ->assertOk()
            ->streamedContent();

        $baris = array_slice(array_filter(explode("\n", trim($isi))), 1);

        $debit = 0;
        $kredit = 0;

        foreach ($baris as $satu) {
            $kolom = str_getcsv(trim($satu));
            $debit += (int) ($kolom[4] ?? 0);
            $kredit += (int) ($kolom[5] ?? 0);
        }

        // Invoice 5jt (Dr Piutang / Cr Pendapatan) + potongan 1jt
        // (Dr Beban / Cr Piutang) = 6jt di kedua sisi.
        expect($debit)->toBe(6_000_000)
            ->and($debit)->toBe($kredit);
    });
});
