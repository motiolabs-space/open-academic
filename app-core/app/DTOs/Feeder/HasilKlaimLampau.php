<?php

declare(strict_types=1);

namespace App\DTOs\Feeder;

/**
 * The outcome of one claim of past students.
 *
 * Either `galat` lists every selected row that would not be claimed and
 * nothing was sent, or it is empty and each row was sent once: claimed,
 * refused by Feeder (recorded, and claimable again), or skipped because an
 * earlier claim already went through. Never both.
 */
final readonly class HasilKlaimLampau
{
    /**
     * @param list<array{nim: string|null, nama: string|null, pesan: string}> $galat
     * @param list<array{nim: string|null, pesan: string}> $ditolak
     * @param array<string, mixed>|null $adopsi the adoption run that followed, as FeederAdopsi::adopsi() reports it
     * @param list<string> $menungguKeputusan NIMs claimed now that adoption left ambiguous, for a person to link or separate
     * @param list<string> $belumTeradopsi NIMs claimed now that adoption neither linked nor found ambiguous
     */
    public function __construct(
        public array $galat = [],
        public int $diklaim = 0,
        public int $dilewati = 0,
        public array $ditolak = [],
        public ?array $adopsi = null,
        public ?string $adopsiGagal = null,
        public array $menungguKeputusan = [],
        public array $belumTeradopsi = [],
    ) {}

    public function berhasil(): bool
    {
        return $this->galat === [];
    }
}
