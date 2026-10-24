<?php

declare(strict_types=1);

use App\Enums\SemesterType;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;

/**
 * The KRS payment gate is the rule most likely to be argued about at a service
 * desk, so its edges are pinned down here rather than left to the UI.
 */
function tagihanUji(int $total, int $terbayar, ?string $dispensasiSampai = null, int $denda = 0): Tagihan
{
    $term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->create();

    return Tagihan::create([
        'nomor' => 'INV/UJI/0001',
        'mahasiswa_id' => Mahasiswa::factory()->create()->id,
        'tahun_akademik_id' => $term->id,
        'keterangan' => 'Uji',
        'total' => $total,
        'terbayar' => $terbayar,
        'denda' => $denda,
        'jatuh_tempo' => now()->addDays(10),
        'dispensasi_sampai' => $dispensasiSampai,
    ]);
}

beforeEach(function () {
    config([
        'academic.krs.requires_payment' => true,
        'academic.krs.min_payment_percent' => 50,
    ]);
});

it('menolak KRS bila pembayaran di bawah ambang minimum', function () {
    expect(tagihanUji(1_000_000, 400_000)->memenuhiSyaratKrs())->toBeFalse();
});

it('meloloskan KRS tepat pada ambang minimum', function () {
    expect(tagihanUji(1_000_000, 500_000)->memenuhiSyaratKrs())->toBeTrue();
});

it('meloloskan KRS bila dispensasi masih berlaku meski belum bayar', function () {
    expect(tagihanUji(1_000_000, 0, now()->addWeek()->toDateString())->memenuhiSyaratKrs())->toBeTrue();
});

it('kembali mengunci KRS setelah dispensasi lewat', function () {
    expect(tagihanUji(1_000_000, 0, now()->subDay()->toDateString())->memenuhiSyaratKrs())->toBeFalse();
});

it('meloloskan seluruh mahasiswa bila gerbang pembayaran dimatikan', function () {
    config(['academic.krs.requires_payment' => false]);

    expect(tagihanUji(1_000_000, 0)->memenuhiSyaratKrs())->toBeTrue();
});

it('menghitung sisa tagihan tanpa pernah negatif', function () {
    expect(tagihanUji(1_000_000, 1_200_000)->sisa())->toBe(0);
});

it('mengukur ambang terhadap tagihan pokok, bukan total berdenda', function () {
    // Total 1.100.000 berisi denda 100.000; 500.000 adalah 45% dari total
    // tetapi tepat 50% dari pokok — dan pokoklah yang dimaksud aturan.
    $tagihan = tagihanUji(1_100_000, 500_000, null, 100_000);

    expect($tagihan->pokok())->toBe(1_000_000)
        ->and($tagihan->persenTerbayar())->toBeLessThan(50.0)
        ->and($tagihan->memenuhiSyaratKrs())->toBeTrue();
});

it('menganggap pokok lunas bila pembayaran menutupnya meski denda tersisa', function () {
    $tagihan = tagihanUji(1_100_000, 1_000_000, null, 100_000);

    expect($tagihan->persenTerbayarPokok())->toBe(100.0)
        ->and($tagihan->sisaPokok())->toBe(0)
        ->and($tagihan->sisa())->toBe(100_000);
});
