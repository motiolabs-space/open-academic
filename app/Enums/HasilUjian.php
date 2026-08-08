<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The verdict of an examining panel.
 *
 * LulusRevisi is the common one and the reason this is not a boolean: most
 * students pass with corrections, and the corrections have a deadline that
 * someone has to be accountable for. A pass recorded without that deadline is
 * how a student is told they graduated and then discovers months later that
 * their manuscript was never accepted.
 */
enum HasilUjian: string
{
    case Lulus = 'lulus';
    case LulusRevisi = 'lulus_revisi';
    case TidakLulus = 'tidak_lulus';

    public function label(): string
    {
        return match ($this) {
            self::Lulus => 'Lulus Tanpa Revisi',
            self::LulusRevisi => 'Lulus Dengan Revisi',
            self::TidakLulus => 'Tidak Lulus — Mengulang',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Lulus => 'success',
            self::LulusRevisi => 'warning',
            self::TidakLulus => 'danger',
        };
    }

    public function lulus(): bool
    {
        return $this !== self::TidakLulus;
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
