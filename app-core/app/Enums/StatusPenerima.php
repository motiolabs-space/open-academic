<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether a scholarship award is still running.
 *
 * Dicabut and Selesai are separate on purpose. An award that ran its course and
 * one that was withdrawn look identical in a total, and completely different to
 * the person it belonged to — and to anyone later asking why the invoices
 * changed shape mid-degree.
 */
enum StatusPenerima: string
{
    case Aktif = 'aktif';
    case Selesai = 'selesai';
    case Dicabut = 'dicabut';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Selesai => 'Selesai',
            self::Dicabut => 'Dicabut',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Aktif => 'success',
            self::Selesai => 'neutral',
            self::Dicabut => 'danger',
        };
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
