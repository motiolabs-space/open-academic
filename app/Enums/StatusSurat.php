<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a letter request has got to.
 *
 * Dicabut exists separately from a deletion because a revoked letter must still
 * be findable. Somebody out there is holding the paper; if verification answered
 * "not found" they would read it as a forgery rather than as a document the
 * campus withdrew, and the difference matters to whoever is being shown it.
 */
enum StatusSurat: string
{
    case Diajukan = 'diajukan';
    case Diterbitkan = 'diterbitkan';
    case Ditolak = 'ditolak';
    case Dicabut = 'dicabut';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Menunggu Persetujuan',
            self::Diterbitkan => 'Terbit',
            self::Ditolak => 'Ditolak',
            self::Dicabut => 'Dicabut',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Diajukan => 'warning',
            self::Diterbitkan => 'success',
            self::Ditolak => 'danger',
            self::Dicabut => 'neutral',
        };
    }

    /** Whether a PDF may be produced for it. */
    public function dapatDiunduh(): bool
    {
        return $this === self::Diterbitkan;
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
