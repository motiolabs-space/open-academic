<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a publication is indexed, as the lecturer states it and staff check
 * it (ADR-041 §2).
 *
 * The one an article is reported under, not every database that lists it:
 * IKU 6 asks whether it is in Scopus or Web of Science, and a national
 * report asks for its SINTA rank. Nothing here looks the journal up — the
 * fact is declared with a DOI or a link beside it so a person can.
 */
enum IndeksPublikasi: string
{
    case Scopus = 'scopus';
    case WebOfScience = 'wos';
    case Sinta1 = 'sinta_1';
    case Sinta2 = 'sinta_2';
    case Sinta3 = 'sinta_3';
    case Sinta4 = 'sinta_4';
    case Sinta5 = 'sinta_5';
    case Sinta6 = 'sinta_6';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return __('portofolio.publikasi.indeks.'.$this->value);
    }

    /**
     * Scopus or Web of Science — the "reputable international" databases
     * IKU 6 of Kepmendiktisaintek 358/M/KEP/2025 names, and the only two
     * whose journals carry a quartile.
     */
    public function bereputasiInternasional(): bool
    {
        return $this === self::Scopus || $this === self::WebOfScience;
    }

    /** @return array<int, string> */
    public static function berkuartil(): array
    {
        return [self::Scopus->value, self::WebOfScience->value];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
