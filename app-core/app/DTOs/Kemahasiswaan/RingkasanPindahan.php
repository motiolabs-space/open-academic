<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

/**
 * Where a transfer student stands: what has been recognised, what that does to
 * their first term, and what Feeder would still refuse.
 */
final readonly class RingkasanPindahan
{
    /** @param array<int, array{rule: string, severity: string, message: string}> $isuFeeder */
    public function __construct(
        public int $batas,
        public int $diakui,
        public int $sisa,
        public int $menunggu,
        public int $semesterAwal,
        public ?float $ipsKonversi,
        public int $batasKrsAwal,
        public int $semesterDiAsal,
        public array $isuFeeder,
    ) {}

    /** Proposals waiting that would not all fit under the ceiling if approved. */
    public function usulanMelebihiSisa(): bool
    {
        return $this->menunggu > $this->sisa;
    }

    public function siapFeeder(): bool
    {
        return collect($this->isuFeeder)->doesntContain('severity', 'error');
    }
}
