<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

/**
 * One course a student has earned credit for, however they earned it.
 *
 * The point of this object is that a transcript row, a graduation checklist
 * entry, and a line in the IPK sum are the same thing seen from three angles.
 * Before it existed they were three separate pieces of code reading the same
 * table and reaching the same answer independently — which is a bug waiting for
 * one of them to be edited.
 */
final readonly class PerolehanBaris
{
    public function __construct(
        public int $mataKuliahId,
        public string $kode,
        public string $nama,
        public int $sks,
        public ?string $huruf,
        public float $bobot,
        public bool $lulus,

        /** True when this came from elsewhere rather than from a class here. */
        public bool $konversi,

        /** "T" or "R" on a transcript; null for ordinary grades. */
        public ?string $tanda,

        /** Term name for a taught course; source institution for a conversion. */
        public ?string $periode,

        /**
         * Whether this line enters the IPK.
         *
         * Always true for taught courses. For conversions it follows
         * config('academic.konversi.hitung_ipk') — see the config note on why
         * the default is off.
         */
        public bool $masukIpk,
    ) {}

    /** Grade points contributed, for the weighted average. */
    public function mutu(): float
    {
        return $this->bobot * $this->sks;
    }
}
