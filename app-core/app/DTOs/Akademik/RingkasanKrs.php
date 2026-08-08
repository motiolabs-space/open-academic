<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use App\Models\Akademik\Krs;

/**
 * What a study plan looks like to a screen: the numbers a student watches while
 * filling it, precomputed once instead of recalculated per Blade expression.
 */
final readonly class RingkasanKrs
{
    public function __construct(
        public int $totalSks,
        public int $batasSks,
        public int $sisaSks,
        public int $jumlahKelas,
        public ?float $ipsAcuan,
        public bool $dapatDiubah,
        public bool $dapatDiajukan,
        public ?string $alasanTidakDapatDiajukan,
    ) {}

    public static function dari(Krs $krs, ?string $penghalang): self
    {
        $total = (int) $krs->total_sks;
        $batas = (int) $krs->batas_sks;

        return new self(
            totalSks: $total,
            batasSks: $batas,
            sisaSks: max(0, $batas - $total),
            jumlahKelas: $krs->detail->count(),
            ipsAcuan: $krs->ips_acuan === null ? null : (float) $krs->ips_acuan,
            dapatDiubah: $krs->status->isEditable(),
            dapatDiajukan: $penghalang === null && $krs->status->isEditable() && $total > 0,
            alasanTidakDapatDiajukan: $penghalang,
        );
    }

    public function persenTerisi(): float
    {
        return $this->batasSks > 0
            ? min(100, round($this->totalSks / $this->batasSks * 100, 1))
            : 0.0;
    }
}
