<?php

declare(strict_types=1);

namespace App\DTOs\Feeder;

use App\Enums\JenisTemuanKamus;

/**
 * One syncable entity measured against one dictionary reading.
 *
 * `terbaca` false means the act's dictionary could not be read, and the
 * entity is neither in agreement nor in disagreement — it is unknown.
 */
final readonly class LaporanKamusEntitas
{
    /**
     * @param array<int, TemuanKamus> $temuan
     * @param array<int, string> $opsionalTidakDikirim
     */
    public function __construct(
        public string $entity,
        public string $label,
        public string $act,
        public bool $terbaca,
        public array $temuan,
        public array $opsionalTidakDikirim = [],
        public ?string $galat = null,
    ) {}

    public function cocok(): bool
    {
        return $this->terbaca && $this->temuan === [];
    }

    public function jumlah(JenisTemuanKamus $jenis): int
    {
        return count(array_filter($this->temuan, fn (TemuanKamus $t): bool => $t->jenis === $jenis));
    }
}
