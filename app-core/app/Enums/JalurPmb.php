<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The admission route a wave runs.
 *
 * Decides what happens to an accepted applicant: transfer and RPL applicants
 * arrive with study done elsewhere and go through the transfer intake (#33),
 * which records the origin institution and recognised credits; everybody else
 * goes through the ordinary daftar ulang as a new entrant.
 */
enum JalurPmb: string
{
    case Reguler = 'reguler';
    case Prestasi = 'prestasi';
    case Rpl = 'rpl';
    case Transfer = 'transfer';

    public function label(): string
    {
        return __('pmb.jalur.'.$this->value);
    }

    /** Whether applicants on this route carry an origin into the intake. */
    public function pindahan(): bool
    {
        return $this === self::Rpl || $this === self::Transfer;
    }

    /** The enrolment kind the transfer intake form starts from. */
    public function jenisDaftarBawaan(): ?JenisDaftar
    {
        return match ($this) {
            self::Transfer => JenisDaftar::Pindahan,
            self::Rpl => JenisDaftar::RplPerolehan,
            default => null,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $jalur): array => [$jalur->value => $jalur->label()])
            ->all();
    }
}
