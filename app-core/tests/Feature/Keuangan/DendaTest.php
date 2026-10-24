<?php

declare(strict_types=1);

use App\DTOs\Keuangan\AturanDenda;
use App\Enums\BentukDenda;
use App\Enums\InvoiceStatus;
use App\Enums\JenisItemTagihan;
use App\Enums\PeriodeDenda;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Models\Sdm\Staff;
use App\Services\Keuangan\DendaService;
use App\Services\Keuangan\PembayaranService;
use App\Services\Keuangan\PotonganService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create([
        'krs_mulai' => now()->subDays(3),
        'krs_selesai' => now()->addDays(60),
    ]);
    $this->prodi = Prodi::factory()->create();

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('keuangan');

    $this->mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => $this->prodi->id,
        'status' => StudentStatus::Aktif,
    ]);

    $this->denda = app(DendaService::class);
    $this->pembayaran = app(PembayaranService::class);

    // Hari ini dipaku supaya "terlambat N hari" berarti satu hal yang sama di
    // setiap mesin dan setiap jam.
    $this->hari = Carbon::create(2026, 10, 24);
});

/** Nominal tetap per hari, tanpa tenggang, tanpa batas — kecuali ditimpa. */
function aturanDenda(array $ubah = []): AturanDenda
{
    return new AturanDenda(...array_merge([
        'aktif' => true,
        'bentuk' => BentukDenda::Nominal,
        'periode' => PeriodeDenda::Hari,
        'nominal' => 10_000,
        'persen' => 1,
        'maksimum' => 0,
        'tenggangHari' => 0,
    ], $ubah));
}

/**
 * Tagihan UKT 5 juta yang jatuh tempo $hariLalu hari sebelum $this->hari.
 *
 * Satu mahasiswa hanya boleh punya satu tagihan per semester (indeks unik),
 * jadi pemanggilan kedua tanpa mahasiswa eksplisit membuat mahasiswa baru.
 */
function tagihanTerlambat(int $hariLalu, int $terbayar = 0, ?Mahasiswa $mahasiswa = null): Tagihan
{
    $mahasiswa ??= Tagihan::where('mahasiswa_id', test()->mahasiswa->id)->exists()
        ? Mahasiswa::factory()->create(['prodi_id' => test()->prodi->id, 'status' => StudentStatus::Aktif])
        : test()->mahasiswa;

    $tagihan = Tagihan::create([
        'nomor' => 'INV/'.uniqid(),
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'keterangan' => 'Biaya kuliah',
        'total' => 5_000_000,
        'terbayar' => $terbayar,
        'status' => InvoiceStatus::fromAmounts(5_000_000, $terbayar),
        'jatuh_tempo' => test()->hari->copy()->subDays($hariLalu),
    ]);

    TagihanItem::create([
        'tagihan_id' => $tagihan->id,
        'jenis' => JenisItemTagihan::Tagihan,
        'nama' => 'UKT',
        'nominal' => 5_000_000,
    ]);

    return $tagihan->fresh();
}

describe('perhitungan', function () {
    it('tidak menghitung apa pun selagi kebijakan nonaktif', function () {
        $tagihan = tagihanTerlambat(10);

        expect($this->denda->hitung($tagihan, aturanDenda(['aktif' => false]), $this->hari))->toBe(0)
            ->and($this->denda->jalankan(aturanDenda(['aktif' => false]), $this->hari))
            ->toMatchArray(['diperiksa' => 0, 'dikenai' => 0, 'total_rupiah' => 0]);
    });

    it('mengenakan nominal tetap per hari keterlambatan', function () {
        $tagihan = tagihanTerlambat(3);

        $tambahan = $this->denda->terapkan($tagihan, aturanDenda(), $this->hari);

        $segar = $tagihan->fresh();
        expect($tambahan)->toBe(30_000)
            ->and((int) $segar->denda)->toBe(30_000)
            ->and((int) $segar->total)->toBe(5_030_000)
            ->and($segar->itemDenda->nominal)->toBe(30_000)
            ->and($segar->itemDenda->jenis)->toBe(JenisItemTagihan::Denda)
            ->and($segar->denda_dihitung_sampai->toDateString())->toBe($this->hari->toDateString());
    });

    it('diam pada hari jatuh tempo dan selama masa tenggang', function () {
        expect($this->denda->hitung(tagihanTerlambat(0), aturanDenda(), $this->hari))->toBe(0)
            ->and($this->denda->hitung(tagihanTerlambat(7), aturanDenda(['tenggangHari' => 7]), $this->hari))->toBe(0)
            ->and($this->denda->hitung(tagihanTerlambat(8), aturanDenda(['tenggangHari' => 7]), $this->hari))->toBe(10_000);
    });

    it('menghitung minggu yang baru berjalan sebagai minggu penuh', function () {
        $aturan = aturanDenda(['periode' => PeriodeDenda::Minggu, 'nominal' => 50_000]);

        expect($this->denda->hitung(tagihanTerlambat(1), $aturan, $this->hari))->toBe(50_000)
            ->and($this->denda->hitung(tagihanTerlambat(7), $aturan, $this->hari))->toBe(50_000)
            ->and($this->denda->hitung(tagihanTerlambat(8), $aturan, $this->hari))->toBe(100_000);
    });

    it('menghitung persen dari sisa pokok, dibulatkan ke bawah', function () {
        // Sisa pokok 3.333.333; 1% = 33.333,33 → 33.333, bukan 33.334.
        $tagihan = tagihanTerlambat(1, 1_666_667);

        expect($this->denda->hitung($tagihan, aturanDenda(['bentuk' => BentukDenda::Persen, 'persen' => 1]), $this->hari))
            ->toBe(33_333);
    });

    it('membatasi denda kumulatif pada maksimum', function () {
        $tagihan = tagihanTerlambat(10);
        $aturan = aturanDenda(['maksimum' => 75_000]);

        expect($this->denda->terapkan($tagihan, $aturan, $this->hari))->toBe(75_000);

        // Hari berikutnya tidak menambah apa pun lagi.
        expect($this->denda->terapkan($tagihan->fresh(), $aturan, $this->hari->copy()->addDay()))->toBe(0)
            ->and((int) $tagihan->fresh()->denda)->toBe(75_000);
    });

    it('berhenti bertambah begitu pokok terbayar meski denda belum', function () {
        $tagihan = tagihanTerlambat(2);
        $this->denda->terapkan($tagihan, aturanDenda(), $this->hari);

        // Melunasi pokok 5 juta; denda 20 ribu masih tersisa.
        $this->pembayaran->catatManual($tagihan->fresh(), 5_000_000, $this->staf);

        expect($tagihan->fresh()->status)->toBe(InvoiceStatus::Sebagian)
            ->and($this->denda->hitung($tagihan->fresh(), aturanDenda(), $this->hari->copy()->addDays(5)))->toBe(0);
    });

    it('tidak menghitung selama dispensasi dan memulai jam dari akhir dispensasi', function () {
        $tagihan = tagihanTerlambat(10);
        $tagihan->update(['dispensasi_sampai' => $this->hari->copy()->addDays(5)]);

        expect($this->denda->hitung($tagihan->fresh(), aturanDenda(), $this->hari))->toBe(0)
            ->and($this->denda->kandidat(aturanDenda(), $this->hari)->count())->toBe(0);

        // Dua hari sesudah dispensasi berakhir: dua hari, bukan tujuh belas.
        $sesudah = $this->hari->copy()->addDays(7);
        Carbon::setTestNow($sesudah);

        expect($this->denda->hitung($tagihan->fresh(), aturanDenda(), $sesudah))->toBe(20_000);

        Carbon::setTestNow();
    });
});

describe('idempoten', function () {
    it('tidak menggandakan denda bila dijalankan dua kali pada hari yang sama', function () {
        $tagihan = tagihanTerlambat(3);

        $this->denda->jalankan(aturanDenda(), $this->hari);
        $kedua = $this->denda->jalankan(aturanDenda(), $this->hari);
        $this->denda->jalankan(aturanDenda(), $this->hari);

        expect($kedua['dikenai'])->toBe(0)
            ->and((int) $tagihan->fresh()->denda)->toBe(30_000)
            ->and($tagihan->item()->denda()->count())->toBe(1);
    });

    it('menambah hanya periode baru pada hari berikutnya', function () {
        $tagihan = tagihanTerlambat(3);

        $this->denda->jalankan(aturanDenda(), $this->hari);
        $this->denda->jalankan(aturanDenda(), $this->hari->copy()->addDays(2));

        expect((int) $tagihan->fresh()->denda)->toBe(50_000)
            ->and((int) $tagihan->fresh()->total)->toBe(5_050_000)
            ->and($tagihan->item()->denda()->count())->toBe(1);
    });

    it('menagih persen atas sisa pokok hari itu, bukan menghitung ulang dari awal', function () {
        $aturan = aturanDenda(['bentuk' => BentukDenda::Persen, 'persen' => 1]);
        $tagihan = tagihanTerlambat(1);

        $this->denda->terapkan($tagihan, $aturan, $this->hari); // 1% × 5.000.000
        $this->pembayaran->catatManual($tagihan->fresh(), 4_000_000, $this->staf);
        $this->denda->terapkan($tagihan->fresh(), $aturan, $this->hari->copy()->addDay()); // 1% × 1.050.000 → sisa pokok 1.000.000

        // Denda hari pertama tidak menyusut karena pembayaran yang datang
        // kemudian; hari kedua dihitung dari pokok yang tersisa.
        expect((int) $tagihan->fresh()->denda)->toBe(60_000);
    });

    it('tidak membuka kembali periode yang sudah ditagih bila dijalankan untuk hari yang lebih lampau', function () {
        $tagihan = tagihanTerlambat(5);

        $this->denda->terapkan($tagihan, aturanDenda(), $this->hari);
        $this->denda->terapkan($tagihan->fresh(), aturanDenda(), $this->hari->copy()->subDays(2));

        expect((int) $tagihan->fresh()->denda)->toBe(50_000)
            ->and($tagihan->fresh()->denda_dihitung_sampai->toDateString())->toBe($this->hari->toDateString());
    });
});

describe('kandidat', function () {
    it('melewati tagihan lunas, mahasiswa nonaktif, dan yang dendanya dibebaskan', function () {
        tagihanTerlambat(5); // kandidat

        tagihanTerlambat(5, 5_000_000); // lunas — status dari fromAmounts

        $cuti = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id, 'status' => StudentStatus::Cuti]);
        tagihanTerlambat(5, 0, $cuti);

        $bebas = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id, 'status' => StudentStatus::Aktif]);
        tagihanTerlambat(5, 0, $bebas)->update(['denda_dibebaskan_at' => now()]);

        expect($this->denda->kandidat(aturanDenda(), $this->hari)->count())->toBe(1);
    });
});

describe('pembebasan', function () {
    beforeEach(function () {
        $this->tagihan = tagihanTerlambat(4);
        $this->denda->terapkan($this->tagihan, aturanDenda(), $this->hari);
        $this->tagihan = $this->tagihan->fresh();
    });

    it('mencatat pembebasan sebagai baris potongan beralasan dan menghentikan penghitungan', function () {
        $item = $this->denda->bebaskan($this->tagihan, $this->staf, 'Bukti transfer tertahan di bank.');

        $segar = $this->tagihan->fresh();
        expect($item->jenis)->toBe(JenisItemTagihan::Potongan)
            ->and($item->nominal)->toBe(-40_000)
            ->and($item->alasan)->toBe('Bukti transfer tertahan di bank.')
            ->and($item->diputus_by_staff_id)->toBe($this->staf->id)
            ->and((int) $segar->denda)->toBe(0)
            ->and((int) $segar->total)->toBe(5_000_000)
            ->and($segar->dendaDibebaskan())->toBeTrue()

            // Baris denda kotor tetap ada: bruto, pembebasan, neto semua terlihat.
            ->and($segar->itemDenda->nominal)->toBe(40_000)
            ->and($segar->pembebasanDenda->id)->toBe($item->id);

        expect($this->denda->hitung($segar, aturanDenda(), $this->hari->copy()->addDays(30)))->toBe(0)
            ->and($this->denda->kandidat(aturanDenda(), $this->hari->copy()->addDays(30))->count())->toBe(0);
    });

    it('mengembalikan status lunas bila pembayaran sudah menutup pokok', function () {
        $this->pembayaran->catatManual($this->tagihan, 5_000_000, $this->staf);
        expect($this->tagihan->fresh()->status)->toBe(InvoiceStatus::Sebagian);

        $this->denda->bebaskan($this->tagihan->fresh(), $this->staf, 'Keterlambatan karena gangguan sistem bank.');

        expect($this->tagihan->fresh()->status)->toBe(InvoiceStatus::Lunas);
    });

    it('mewajibkan alasan', function () {
        expect(fn () => $this->denda->bebaskan($this->tagihan, $this->staf, '  '))
            ->toThrow(AturanAkademikException::class, 'wajib disertai alasan');
    });

    it('menolak pembebasan kedua dan tagihan tanpa denda', function () {
        $this->denda->bebaskan($this->tagihan, $this->staf, 'Alasan pertama yang sah.');

        expect(fn () => $this->denda->bebaskan($this->tagihan->fresh(), $this->staf, 'Lagi.'))
            ->toThrow(AturanAkademikException::class, 'sudah dibebaskan');

        expect(fn () => $this->denda->bebaskan(tagihanTerlambat(1), $this->staf, 'Tidak ada denda.'))
            ->toThrow(AturanAkademikException::class, 'tidak memiliki denda');
    });

    it('menulis jejak audit dan memberi tahu mahasiswa', function () {
        $this->denda->bebaskan($this->tagihan, $this->staf, 'Bukti transfer tertahan di bank.');

        expect(DB::table('log_aktivitas')->where('event', 'late_fee_waived')->count())->toBe(1)
            ->and(DB::table('log_aktivitas')->where('event', 'late_fee_applied')->count())->toBe(1);

        $data = json_decode((string) DB::table('notifications')->latest('created_at')->value('data'), true);
        expect($data['judul'])->toContain('dibebaskan')
            ->and($data['ringkasan'])->toContain('Rp40.000')
            ->and($data['ringkasan'])->toContain('tertahan di bank');
    });

    it('tidak dapat dibatalkan lewat penghapusan potongan biasa', function () {
        $item = $this->denda->bebaskan($this->tagihan, $this->staf, 'Bukti transfer tertahan di bank.');

        expect(fn () => app(PotonganService::class)->hapus($item, $this->staf, 'Coba hapus.'))
            ->toThrow(AturanAkademikException::class, 'tidak dapat dibatalkan');
    });
});

describe('gerbang KRS', function () {
    beforeEach(function () {
        config(['academic.krs.requires_payment' => true, 'academic.krs.min_payment_percent' => 50]);
    });

    it('tidak terkunci lebih keras oleh denda', function () {
        // Tepat 50% pokok sudah dibayar — lolos sebelum denda, harus tetap lolos sesudahnya.
        $tagihan = tagihanTerlambat(3);
        $this->pembayaran->catatManual($tagihan, 2_500_000, $this->staf);
        expect($tagihan->fresh()->memenuhiSyaratKrs())->toBeTrue();

        $this->denda->terapkan($tagihan->fresh(), aturanDenda(['nominal' => 100_000]), $this->hari);

        $segar = $tagihan->fresh();
        expect((int) $segar->denda)->toBe(300_000)
            ->and($segar->persenTerbayar())->toBeLessThan(50.0)
            ->and($segar->persenTerbayarPokok())->toBe(50.0)
            ->and($segar->memenuhiSyaratKrs())->toBeTrue();
    });

    it('masih mengunci yang memang di bawah ambang', function () {
        $tagihan = tagihanTerlambat(3);
        $this->pembayaran->catatManual($tagihan, 2_000_000, $this->staf);
        $this->denda->terapkan($tagihan->fresh(), aturanDenda(), $this->hari);

        expect($tagihan->fresh()->memenuhiSyaratKrs())->toBeFalse();
    });
});

describe('perintah artisan', function () {
    it('mengenakan denda lewat perintah dan melaporkan ringkasannya', function () {
        config(['payment.denda' => [
            'aktif' => true, 'bentuk' => 'nominal', 'periode' => 'hari',
            'nominal' => 10_000, 'persen' => 0, 'maksimum' => 0, 'tenggang_hari' => 0,
        ]]);
        $tagihan = tagihanTerlambat(3);

        $this->artisan('openacademic:hitung-denda', ['--hari' => $this->hari->toDateString()])
            ->assertSuccessful();

        expect((int) $tagihan->fresh()->denda)->toBe(30_000);
    });

    it('mode kering tidak menulis apa pun', function () {
        config(['payment.denda' => [
            'aktif' => true, 'bentuk' => 'nominal', 'periode' => 'hari',
            'nominal' => 10_000, 'persen' => 0, 'maksimum' => 0, 'tenggang_hari' => 0,
        ]]);
        $tagihan = tagihanTerlambat(3);

        $this->artisan('openacademic:hitung-denda', ['--hari' => $this->hari->toDateString(), '--kering' => true])
            ->assertSuccessful();

        expect((int) $tagihan->fresh()->denda)->toBe(0)
            ->and($tagihan->fresh()->denda_dihitung_sampai)->toBeNull();
    });

    it('berhenti sopan selagi nonaktif', function () {
        config(['payment.denda.aktif' => false]);
        $tagihan = tagihanTerlambat(3);

        $this->artisan('openacademic:hitung-denda')->assertSuccessful();

        expect((int) $tagihan->fresh()->denda)->toBe(0);
    });
});

describe('pengingat tagihan', function () {
    it('menyebut aturan denda dan denda berjalan bila kebijakan aktif', function () {
        config([
            'notifikasi.pengingat.tagihan' => [-7],
            'payment.denda' => [
                'aktif' => true, 'bentuk' => 'nominal', 'periode' => 'hari',
                'nominal' => 10_000, 'persen' => 0, 'maksimum' => 500_000, 'tenggang_hari' => 0,
            ],
        ]);

        $tagihan = tagihanTerlambat(7);
        $tagihan->update(['jatuh_tempo' => now()->subDays(7)]);
        $this->denda->terapkan($tagihan->fresh(), AturanDenda::dariConfig(), Carbon::today());

        $this->artisan('openacademic:kirim-pengingat')->assertSuccessful();

        $data = json_decode((string) DB::table('notifications')->latest('created_at')->value('data'), true);
        expect($data['ringkasan'])->toContain('Rp10.000 per hari')
            ->and($data['ringkasan'])->toContain('paling banyak Rp500.000')
            ->and($data['ringkasan'])->toContain('telah berjalan')
            ->and($data['ringkasan'])->toContain('Rp70.000');
    });

    it('tidak menyebut denda bila kebijakan nonaktif', function () {
        config(['notifikasi.pengingat.tagihan' => [7], 'payment.denda.aktif' => false]);
        tagihanTerlambat(-7)->update(['jatuh_tempo' => now()->addDays(7)]);

        $this->artisan('openacademic:kirim-pengingat')->assertSuccessful();

        $data = json_decode((string) DB::table('notifications')->value('data'), true);
        expect($data['ringkasan'])->not->toContain('denda');
    });
});

describe('layar', function () {
    beforeEach(function () {
        $this->tagihan = tagihanTerlambat(4);
        $this->denda->terapkan($this->tagihan, aturanDenda(), $this->hari);
    });

    it('staf keuangan membebaskan denda lewat layar dengan alasan', function () {
        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.keuangan.denda.bebaskan', $this->tagihan), [
                'alasan' => 'Bukti transfer tertahan di bank sejak sebelum jatuh tempo.',
            ])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        expect($this->tagihan->fresh()->dendaDibebaskan())->toBeTrue();
    });

    it('menolak alasan kosong atau terlalu singkat', function () {
        $this->actingAs($this->staf, 'staff')
            ->from(route('admin.keuangan'))
            ->post(route('admin.keuangan.denda.bebaskan', $this->tagihan), ['alasan' => 'ok'])
            ->assertRedirect(route('admin.keuangan'))
            ->assertSessionHasErrors('alasan');

        expect($this->tagihan->fresh()->dendaDibebaskan())->toBeFalse();
    });

    it('menolak staf tanpa izin keuangan', function () {
        $baak = Staff::factory()->create();
        $baak->assignRole('baak');

        $this->actingAs($baak, 'staff')
            ->post(route('admin.keuangan.denda.bebaskan', $this->tagihan), ['alasan' => 'Alasan yang panjang.'])
            ->assertForbidden();
    });

    it('layar admin menampilkan denda dan tombol pembebasan', function () {
        $this->actingAs($this->staf, 'staff')
            ->get(route('admin.keuangan'))
            ->assertOk()
            ->assertSee('termasuk denda Rp40.000')
            ->assertSee('Bebaskan Denda');
    });

    it('mahasiswa melihat denda berjalan dengan penjelasan', function () {
        config(['payment.denda' => [
            'aktif' => true, 'bentuk' => 'nominal', 'periode' => 'hari',
            'nominal' => 10_000, 'persen' => 0, 'maksimum' => 0, 'tenggang_hari' => 0,
        ]]);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.tagihan'))
            ->assertOk()
            ->assertSee('data-denda="berjalan"', false)
            ->assertSee('Rp40.000')
            ->assertSee('Denda Keterlambatan')
            ->assertSee('tidak memengaruhi syarat pembayaran');
    });

    it('mahasiswa melihat pembebasan beserta alasannya', function () {
        $this->denda->bebaskan($this->tagihan->fresh(), $this->staf, 'Bukti transfer tertahan di bank.');

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.tagihan'))
            ->assertOk()
            ->assertSee('data-denda="dibebaskan"', false)
            ->assertSee('Bukti transfer tertahan di bank')
            ->assertSee('− Rp40.000');
    });
});
