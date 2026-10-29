<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

/**
 * Whether one graduate's record will pass the PISN validators, item by item.
 *
 * The PISN site answers "tidak eligible" per validator, and the campus then
 * hunts through Feeder one screen at a time. Every item here carries its
 * reason so the hunt happens before the site is opened, not after — and not
 * years later, when a graduate's employer looks the number up and finds
 * nothing.
 */
final readonly class KesiapanPisn
{
    /** @param array<int, array{kode: string, label: string, siap: bool, keterangan: string}> $rincian */
    public function __construct(
        public array $rincian,
    ) {}

    public function siap(): bool
    {
        return collect($this->rincian)->every(fn (array $r): bool => $r['siap']);
    }

    /** @return array<int, string> labels of the items still open */
    public function belumSiap(): array
    {
        return collect($this->rincian)
            ->reject(fn (array $r): bool => $r['siap'])
            ->pluck('label')
            ->values()
            ->all();
    }

    public function jumlahBelumSiap(): int
    {
        return count($this->belumSiap());
    }
}
