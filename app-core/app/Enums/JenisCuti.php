<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a student is taking leave.
 *
 * The column predates this enum and is read as a plain string across the
 * application, so it is not cast on CutiMahasiswa; the enum is the one list of
 * accepted values and their labels.
 */
enum JenisCuti: string
{
    case Akademik = 'akademik';
    case Sakit = 'sakit';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return __('swalayan.cuti.jenis.'.$this->value);
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
