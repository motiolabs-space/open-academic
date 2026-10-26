<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

/**
 * The ceremony checklist for one graduate.
 *
 * Two kinds of row. A *wajib* row gates registration: the button stays
 * disabled and the service refuses until it is met. An informational row is
 * shown so the graduate can act on it early — diploma-number readiness above
 * all — but never blocks the ceremony, because nothing in it is the
 * graduate's to fix.
 */
final readonly class SyaratWisuda
{
    /** @param array<int, array{kode: string, label: string, terpenuhi: bool, wajib: bool, keterangan: string}> $rincian */
    public function __construct(
        public array $rincian,
    ) {}

    /** Every required row is met; informational rows are ignored. */
    public function memenuhi(): bool
    {
        return collect($this->rincian)
            ->where('wajib', true)
            ->every(fn (array $s): bool => $s['terpenuhi']);
    }

    /** @return array<int, string> labels of the required rows not yet met */
    public function belumTerpenuhi(): array
    {
        return collect($this->rincian)
            ->where('wajib', true)
            ->reject(fn (array $s): bool => $s['terpenuhi'])
            ->pluck('label')
            ->values()
            ->all();
    }

    /** @return array{kode: string, label: string, terpenuhi: bool, wajib: bool, keterangan: string}|null */
    public function baris(string $kode): ?array
    {
        return collect($this->rincian)->firstWhere('kode', $kode);
    }
}
