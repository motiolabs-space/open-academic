<?php

declare(strict_types=1);

namespace App\Enums;

/** The kinds of training SISTER's "Diklat" group distinguishes. */
enum JenisDiklat: string
{
    case Prajabatan = 'prajabatan';
    case Kepemimpinan = 'kepemimpinan';
    case Fungsional = 'fungsional';
    case Teknis = 'teknis';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return __('kepegawaian.diklat.'.$this->value);
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
