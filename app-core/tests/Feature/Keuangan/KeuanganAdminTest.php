<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Pembayaran;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\Tarif;
use App\Models\Sdm\Staff;
use App\Services\Keuangan\PembayaranService;
use App\Services\Keuangan\PenerbitanTagihanService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->berjalan()->create(['is_active' => true, 'tahun_mulai' => 2026]);
    $this->prodi = Prodi::factory()->create();

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('keuangan');

    $this->penerbitan = app(PenerbitanTagihanService::class);
    $this->pembayaran = app(PembayaranService::class);
});

function tarifUkt(int $nominal = 5_000_000): Tarif
{
    return Tarif::create([
        'prodi_id' => test()->prodi->id,
        'komponen' => 'ukt',
        'nama' => 'UKT',
        'nominal' => $nominal,
        'is_active' => true,
    ]);
}

describe('penerbitan tagihan massal', function () {
    it('menerbitkan tagihan untuk mahasiswa aktif saja', function () {
        tarifUkt();

        Mahasiswa::factory()->count(3)->create([
            'prodi_id' => $this->prodi->id,
            'status' => StudentStatus::Aktif,
        ]);

        // Mahasiswa cuti tidak ditagih untuk semester yang tidak ia jalani.
        Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id, 'status' => StudentStatus::Cuti]);

        $hasil = $this->penerbitan->terbitkan($this->term);

        expect($hasil['terbit'])->toBe(3)
            ->and($hasil['total_rupiah'])->toBe(15_000_000)
            ->and(Tagihan::count())->toBe(3);
    });

    it('tidak menagih dua kali bila dijalankan ulang', function () {
        tarifUkt();
        Mahasiswa::factory()->count(2)->create(['prodi_id' => $this->prodi->id, 'status' => StudentStatus::Aktif]);

        $this->penerbitan->terbitkan($this->term);
        $kedua = $this->penerbitan->terbitkan($this->term);

        expect($kedua['terbit'])->toBe(0)
            ->and($kedua['dilewati'])->toBe(2)
            ->and(Tagihan::count())->toBe(2);
    });

    it('melaporkan mahasiswa yang tidak punya tarif alih-alih menagih nol', function () {
        // Tagihan nol rupiah terbaca sebagai lunas di setiap layar; ketiadaan
        // yang dilaporkan jelas jauh lebih aman.
        Mahasiswa::factory()->count(2)->create(['prodi_id' => $this->prodi->id, 'status' => StudentStatus::Aktif]);

        $hasil = $this->penerbitan->terbitkan($this->term);

        expect($hasil['terbit'])->toBe(0)
            ->and($hasil['tanpa_tarif'])->toBe(2)
            ->and(Tagihan::count())->toBe(0);
    });

    it('pratinjau tidak menulis apa pun', function () {
        tarifUkt();
        Mahasiswa::factory()->count(2)->create(['prodi_id' => $this->prodi->id, 'status' => StudentStatus::Aktif]);

        $pratinjau = $this->penerbitan->pratinjau($this->term);

        expect($pratinjau['akan_terbit'])->toBe(2)
            ->and($pratinjau['total_rupiah'])->toBe(10_000_000)
            ->and(Tagihan::count())->toBe(0);
    });

    it('menyaring per angkatan', function () {
        tarifUkt();
        Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id, 'angkatan' => 2026, 'status' => StudentStatus::Aktif]);
        Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id, 'angkatan' => 2025, 'status' => StudentStatus::Aktif]);

        expect($this->penerbitan->terbitkan($this->term, 2026)['terbit'])->toBe(1);
    });
});

describe('pencatatan pembayaran', function () {
    beforeEach(function () {
        tarifUkt();
        $this->mahasiswa = Mahasiswa::factory()->create([
            'prodi_id' => $this->prodi->id,
            'status' => StudentStatus::Aktif,
        ]);
        $this->penerbitan->terbitkan($this->term);
        $this->tagihan = Tagihan::firstOrFail();
    });

    it('memperbarui total terbayar dan status tagihan', function () {
        $this->pembayaran->catatManual($this->tagihan, 2_000_000, $this->staf);

        $segar = $this->tagihan->fresh();
        expect((int) $segar->terbayar)->toBe(2_000_000)
            ->and($segar->status)->toBe(InvoiceStatus::Sebagian);

        $this->pembayaran->catatManual($segar, 3_000_000, $this->staf);

        expect($this->tagihan->fresh()->status)->toBe(InvoiceStatus::Lunas);
    });

    it('menolak pembayaran melebihi sisa tagihan', function () {
        // Kelebihan bayar hampir selalu salah ketik nol, dan meloloskannya
        // menghasilkan saldo negatif yang harus di-kecualikan setiap layar.
        expect(fn () => $this->pembayaran->catatManual($this->tagihan, 6_000_000, $this->staf))
            ->toThrow(AturanAkademikException::class, 'melebihi sisa');

        expect((int) $this->tagihan->fresh()->terbayar)->toBe(0);
    });

    it('menghitung ulang total dari baris pembayaran, bukan menambahkan', function () {
        $bayar = $this->pembayaran->catatManual($this->tagihan, 5_000_000, $this->staf);

        // Penambahan yang berjalan dua kali — job diulang, formulir terkirim
        // ganda — diam-diam melunasi utang yang belum lunas.
        $this->pembayaran->hitungUlang($this->tagihan->fresh());
        $this->pembayaran->hitungUlang($this->tagihan->fresh());

        expect((int) $this->tagihan->fresh()->terbayar)->toBe(5_000_000);
    });

    it('mengembalikan status tagihan saat pembayaran dibatalkan', function () {
        $bayar = $this->pembayaran->catatManual($this->tagihan, 5_000_000, $this->staf);

        expect($this->tagihan->fresh()->status)->toBe(InvoiceStatus::Lunas);

        $this->pembayaran->batalkan($bayar, $this->staf, 'Transfer ternyata gagal di bank.');

        $segar = $this->tagihan->fresh();
        expect((int) $segar->terbayar)->toBe(0)
            ->and($segar->status)->toBe(InvoiceStatus::BelumBayar)

            // Barisnya tidak dihapus — catatan keuangan yang bisa lenyap
            // bukan catatan.
            ->and($bayar->fresh()->status)->toBe(PaymentStatus::Refund)
            ->and(Pembayaran::count())->toBe(1);
    });

    it('mewajibkan alasan pada pembatalan', function () {
        $bayar = $this->pembayaran->catatManual($this->tagihan, 1_000_000, $this->staf);

        expect(fn () => $this->pembayaran->batalkan($bayar, $this->staf, ''))
            ->toThrow(AturanAkademikException::class, 'wajib disertai alasan');
    });
});

describe('layar keuangan', function () {
    it('merender layar dan menampilkan pratinjau', function () {
        tarifUkt();
        Mahasiswa::factory()->count(2)->create(['prodi_id' => $this->prodi->id, 'status' => StudentStatus::Aktif]);

        $this->actingAs($this->staf, 'staff')->get('/admin/keuangan')->assertOk();

        $this->actingAs($this->staf, 'staff')
            ->post('/admin/keuangan/pratinjau', ['tahun_akademik_id' => $this->term->id])
            ->assertRedirect()
            ->assertSessionHas('pratinjau');

        expect(Tagihan::count())->toBe(0);
    });

    it('memperingatkan bila ada mahasiswa tanpa tarif', function () {
        Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id, 'status' => StudentStatus::Aktif]);

        $this->actingAs($this->staf, 'staff')
            ->post('/admin/keuangan/terbitkan', ['tahun_akademik_id' => $this->term->id])
            ->assertSessionHas('peringatan');
    });

    it('menolak staf tanpa izin keuangan', function () {
        $baak = Staff::factory()->create();
        $baak->assignRole('baak');

        $this->actingAs($baak, 'staff')->get('/admin/keuangan')->assertForbidden();
    });
});
