<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The grade on an accreditation decree.
 *
 * Both scales are here because both are still in force on somebody's
 * decree: A/B/C from the older instrument, Unggul/Baik Sekali/Baik from the
 * current one, plus the bare "Terakreditasi" of a programme accredited
 * without a grade and the provisional status of a new programme. The one
 * value that does not accredit is recorded too — a refusal is an event in
 * the history, not a gap in it.
 */
enum PeringkatAkreditasi: string
{
    case Unggul = 'unggul';
    case BaikSekali = 'baik_sekali';
    case Baik = 'baik';
    case Terakreditasi = 'terakreditasi';
    case TerakreditasiSementara = 'terakreditasi_sementara';
    case A = 'a';
    case B = 'b';
    case C = 'c';
    case TidakTerakreditasi = 'tidak_terakreditasi';

    public function label(): string
    {
        return __('akreditasi.peringkat.'.$this->value);
    }

    public function terakreditasi(): bool
    {
        return $this !== self::TidakTerakreditasi;
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
