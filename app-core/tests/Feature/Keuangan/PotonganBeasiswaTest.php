<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\JenisBeasiswa;
use App\Enums\JenisItemTagihan;
use App\Enums\SemesterType;
use App\Enums\StatusPenerima;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Beasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Models\Sdm\Staff;
use App\Services\Keuangan\BeasiswaService;
use App\Services\Keuangan\PembayaranService;
use App\Services\Keuangan\PotonganService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create();

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('keuangan');

    $this->mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => $this->prodi->id,
        'status' => StudentStatus::Aktif,
    ]);

    $this->potongan = app(PotonganService::class);
    $this->beasiswa = app(BeasiswaService::class);
});

/** Tagihan dengan dua komponen: UKT 5 juta, praktikum 1 juta. */
function tagihanDuaKomponen(?Mahasiswa $mahasiswa = null): Tagihan
{
    $tagihan = Tagihan::create([
        'nomor' => 'INV/'.uniqid(),
        'mahasiswa_id' => ($mahasiswa ?? test()->mahasiswa)->id,
        'tahun_akademik_id' => test()->term->id,
        'keterangan' => 'Biaya kuliah',
        'total' => 6_000_000,
        'terbayar' => 0,
        'status' => InvoiceStatus::BelumBayar,
        'jatuh_tempo' => now()->addMonth(),
    ]);

    foreach ([['UKT Semester', 5_000_000], ['Biaya Praktikum', 1_000_000]] as [$nama, $nominal]) {
        TagihanItem::create([
            'tagihan_id' => $tagihan->id,
            'jenis' => JenisItemTagihan::Tagihan,
            'nama' => $nama,
            'nominal' => $nominal,
        ]);
    }

    return $tagihan->fresh();
}

function skemaBeasiswa(array $atribut = []): Beasiswa
{
    return Beasiswa::create(array_merge([
        'kode' => 'BS-'.uniqid(),
        'nama' => 'Beasiswa Prestasi',
        'jenis' => JenisBeasiswa::Internal,
        'persen' => 100,
        'is_active' => true,
    ], $atribut));
}

describe('kolom yang dulu memblokir seluruh modul', function () {
    it('menyimpan baris tagihan bernilai negatif', function () {
        // tagihan_item.nominal dulu unsigned: potongan bukan "belum dibuat",
        // melainkan tidak dapat ada.
        $tagihan = tagihanDuaKomponen();

        TagihanItem::create([
            'tagihan_id' => $tagihan->id,
            'jenis' => JenisItemTagihan::Potongan,
            'nama' => 'Potongan',
            'nominal' => -1_000_000,
        ]);

        expect((int) TagihanItem::where('tagihan_id', $tagihan->id)->sum('nominal'))
            ->toBe(5_000_000);
    });
});

describe('keringanan', function () {
    it('menurunkan total dan menjaganya tetap sama dengan jumlah item', function () {
        // Invariant yang membuat sepuluh pembaca lain tidak perlu diubah.
        $tagihan = tagihanDuaKomponen();

        $this->potongan->keringanan($tagihan, 2_000_000, 'Musibah keluarga.', $this->staf);

        $segar = $tagihan->fresh();

        expect((int) $segar->total)->toBe(4_000_000)
            ->and((int) $segar->total)
            ->toBe((int) TagihanItem::where('tagihan_id', $tagihan->id)->sum('nominal'));
    });

    it('mewajibkan alasan', function () {
        expect(fn () => $this->potongan->keringanan(tagihanDuaKomponen(), 1_000_000, '', $this->staf))
            ->toThrow(AturanAkademikException::class, 'wajib disertai alasan');
    });

    it('mencatat siapa yang memutuskan', function () {
        $item = $this->potongan->keringanan(tagihanDuaKomponen(), 1_000_000, 'Musibah.', $this->staf);

        expect($item->diputus_by_staff_id)->toBe($this->staf->id)
            ->and($item->diputus_at)->not->toBeNull()
            ->and($item->alasan)->toBe('Musibah.');
    });

    it('menolak potongan yang membuat total negatif', function () {
        // tagihan.total unsigned, dan setiap pembacanya mengasumsikan utang atau
        // nol. Total negatif akan terbaca sebagai angka positif raksasa.
        expect(fn () => $this->potongan->keringanan(tagihanDuaKomponen(), 7_000_000, 'Terlalu besar.', $this->staf))
            ->toThrow(AturanAkademikException::class, 'melebihi sisa tagihan');
    });

    it('mengizinkan potongan tepat sebesar tagihan', function () {
        $tagihan = tagihanDuaKomponen();
        $this->potongan->keringanan($tagihan, 6_000_000, 'Pembebasan penuh.', $this->staf);

        expect((int) $tagihan->fresh()->total)->toBe(0);
    });

    it('mengembalikan nominalnya saat potongan dihapus', function () {
        $tagihan = tagihanDuaKomponen();
        $item = $this->potongan->keringanan($tagihan, 2_000_000, 'Musibah.', $this->staf);

        $this->potongan->hapus($item, $this->staf, 'Diberikan atas data yang keliru.');

        expect((int) $tagihan->fresh()->total)->toBe(6_000_000);
    });
});

describe('tagihan nol', function () {
    it('berstatus lunas, bukan belum bayar', function () {
        /*
         * Bug yang tersingkap oleh fitur ini. Aturan lama menuntut total > 0
         * untuk berstatus lunas, sehingga pembebasan penuh menghasilkan tagihan
         * "belum bayar" senilai Rp0 — yang akan menahan surat keterangan aktif
         * kuliah dan memicu pengingat jatuh tempo.
         */
        $tagihan = tagihanDuaKomponen();
        $this->potongan->keringanan($tagihan, 6_000_000, 'Pembebasan penuh.', $this->staf);

        expect($tagihan->fresh()->status)->toBe(InvoiceStatus::Lunas)
            ->and(Tagihan::belumLunas()->count())->toBe(0);
    });

    it('tidak menghalangi gerbang pembayaran KRS', function () {
        $tagihan = tagihanDuaKomponen();
        $this->potongan->keringanan($tagihan, 6_000_000, 'Pembebasan penuh.', $this->staf);

        expect($tagihan->fresh()->memenuhiSyaratKrs())->toBeTrue();
    });
});

describe('potongan setelah pembayaran', function () {
    it('menandai lunas dan menampilkan kelebihannya, bukan menelannya', function () {
        /*
         * Kasus yang paling mudah kehilangan uang. Mahasiswa membayar lunas
         * bulan Agustus, beasiswanya disetujui bulan September.
         *
         * Baris pembayaran tidak boleh ditulis ulang — uangnya memang berpindah.
         * Kelebihannya harus terlihat agar bagian keuangan dapat mengembalikan
         * atau memindahkannya.
         */
        $tagihan = tagihanDuaKomponen();
        app(PembayaranService::class)->catatManual($tagihan, 6_000_000, $this->staf);

        expect($tagihan->fresh()->status)->toBe(InvoiceStatus::Lunas);

        $this->potongan->keringanan($tagihan->fresh(), 2_000_000, 'Beasiswa terlambat disetujui.', $this->staf);

        $segar = $tagihan->fresh();

        expect((int) $segar->total)->toBe(4_000_000)
            ->and((int) $segar->terbayar)->toBe(6_000_000)
            ->and($segar->kelebihanBayar())->toBe(2_000_000)
            ->and($segar->status)->toBe(InvoiceStatus::Lunas);
    });

    it('mengubah status sebagian menjadi lunas saat potongan menutup sisanya', function () {
        // Tagihan yang tertinggal berstatus "sebagian" akan mengunci KRS dan
        // muncul pada pengingat tunggakan atas utang yang sudah tidak ada.
        $tagihan = tagihanDuaKomponen();
        app(PembayaranService::class)->catatManual($tagihan, 4_000_000, $this->staf);

        expect($tagihan->fresh()->status)->toBe(InvoiceStatus::Sebagian);

        $this->potongan->keringanan($tagihan->fresh(), 2_000_000, 'Keringanan.', $this->staf);

        expect($tagihan->fresh()->status)->toBe(InvoiceStatus::Lunas)
            ->and($tagihan->fresh()->sisa())->toBe(0);
    });
});

describe('beasiswa', function () {
    it('menerapkan potongan persentase ke tagihan yang sudah terbit', function () {
        // Alur yang sebenarnya terjadi: seleksi selesai berminggu-minggu setelah
        // penagihan.
        $tagihan = tagihanDuaKomponen();

        $this->beasiswa->tetapkan(
            skemaBeasiswa(['persen' => 50]),
            $this->mahasiswa,
            $this->term,
            staff: $this->staf,
        );

        expect((int) $tagihan->fresh()->total)->toBe(3_000_000);
    });

    it('hanya memotong komponen yang dicakup', function () {
        // Beasiswa yang membayar UKT tetapi tidak praktikum adalah hal lazim.
        $tagihan = tagihanDuaKomponen();

        $this->beasiswa->tetapkan(
            skemaBeasiswa(['persen' => 100, 'komponen' => ['UKT']]),
            $this->mahasiswa,
            $this->term,
            staff: $this->staf,
        );

        expect((int) $tagihan->fresh()->total)->toBe(1_000_000);
    });

    it('memotong nominal tetap, dibatasi oleh tagihannya', function () {
        $tagihan = tagihanDuaKomponen();

        $this->beasiswa->tetapkan(
            skemaBeasiswa(['persen' => null, 'nominal' => 9_000_000]),
            $this->mahasiswa,
            $this->term,
            staff: $this->staf,
        );

        expect((int) $tagihan->fresh()->total)->toBe(0);
    });

    it('tidak menumpuk dua beasiswa melewati nilai tagihannya', function () {
        // 60% + 60% tidak boleh menjadi 120%.
        $tagihan = tagihanDuaKomponen();

        $this->beasiswa->tetapkan(skemaBeasiswa(['persen' => 60]), $this->mahasiswa, $this->term, staff: $this->staf);
        $this->beasiswa->tetapkan(skemaBeasiswa(['persen' => 60]), $this->mahasiswa, $this->term, staff: $this->staf);

        expect((int) $tagihan->fresh()->total)->toBe(0)
            ->and((int) $tagihan->fresh()->total)->toBeGreaterThanOrEqual(0);
    });

    it('tidak menerapkan potongan dua kali saat dijalankan ulang', function () {
        // Penerbitan ulang atau job yang diulang tidak boleh menjadi beasiswa
        // kedua — totalnya tetap seimbang, jadi tidak ada yang tampak salah.
        $tagihan = tagihanDuaKomponen();

        $this->beasiswa->tetapkan(skemaBeasiswa(['persen' => 50]), $this->mahasiswa, $this->term, staff: $this->staf);

        $this->potongan->terapkan($tagihan->fresh());
        $this->potongan->terapkan($tagihan->fresh());

        expect((int) $tagihan->fresh()->total)->toBe(3_000_000)
            ->and(TagihanItem::where('tagihan_id', $tagihan->id)->potongan()->count())->toBe(1);
    });

    it('menghormati kuota', function () {
        $skema = skemaBeasiswa(['kuota' => 1]);

        $this->beasiswa->tetapkan($skema, $this->mahasiswa, $this->term, staff: $this->staf);

        $lain = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);

        expect(fn () => $this->beasiswa->tetapkan($skema->fresh(), $lain, $this->term, staff: $this->staf))
            ->toThrow(AturanAkademikException::class, 'Kuota beasiswa');
    });

    it('menolak penerimaan kedua atas skema yang sama', function () {
        $skema = skemaBeasiswa();
        $this->beasiswa->tetapkan($skema, $this->mahasiswa, $this->term, staff: $this->staf);

        expect(fn () => $this->beasiswa->tetapkan($skema->fresh(), $this->mahasiswa, $this->term, staff: $this->staf))
            ->toThrow(AturanAkademikException::class, 'masih berjalan');
    });

    it('tidak menyentuh tagihan semester di luar cakupannya', function () {
        $lalu = TahunAkademik::factory()->term(2025, SemesterType::Ganjil)->create();

        $tagihanLalu = Tagihan::create([
            'nomor' => 'INV/LAMA',
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $lalu->id,
            'keterangan' => 'Biaya kuliah',
            'total' => 6_000_000,
            'terbayar' => 0,
            'status' => InvoiceStatus::BelumBayar,
            'jatuh_tempo' => now()->subYear(),
        ]);

        $this->beasiswa->tetapkan(skemaBeasiswa(), $this->mahasiswa, $this->term, staff: $this->staf);

        expect((int) $tagihanLalu->fresh()->total)->toBe(6_000_000);
    });
});

describe('pencabutan beasiswa', function () {
    it('bersifat ke depan dan tidak membongkar tagihan yang sudah dipotong', function () {
        /*
         * Membalik semester yang lalu akan memunculkan kembali utang atas
         * tagihan yang sudah dianggap selesai berbulan-bulan sebelumnya.
         * Pembalikan satu baris tetap tersedia lewat PotonganService::hapus().
         */
        $tagihan = tagihanDuaKomponen();
        $penerima = $this->beasiswa->tetapkan(
            skemaBeasiswa(['persen' => 50]), $this->mahasiswa, $this->term, staff: $this->staf,
        );

        expect((int) $tagihan->fresh()->total)->toBe(3_000_000);

        $this->beasiswa->cabut($penerima, $this->staf, 'Tidak memenuhi syarat IPK.');

        expect($penerima->fresh()->status)->toBe(StatusPenerima::Dicabut)
            ->and((int) $tagihan->fresh()->total)->toBe(3_000_000);
    });

    it('mewajibkan alasan', function () {
        $penerima = $this->beasiswa->tetapkan(skemaBeasiswa(), $this->mahasiswa, $this->term, staff: $this->staf);

        expect(fn () => $this->beasiswa->cabut($penerima, $this->staf, ''))
            ->toThrow(AturanAkademikException::class, 'wajib disertai alasan');
    });

    it('membebaskan skemanya untuk penerimaan baru', function () {
        $skema = skemaBeasiswa(['kuota' => 1]);
        $penerima = $this->beasiswa->tetapkan($skema, $this->mahasiswa, $this->term, staff: $this->staf);

        $this->beasiswa->cabut($penerima, $this->staf, 'Mengundurkan diri.');

        $lain = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);

        expect(fn () => $this->beasiswa->tetapkan($skema->fresh(), $lain, $this->term, staff: $this->staf))
            ->not->toThrow(AturanAkademikException::class);
    });
});
