<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lead or member.
 *
 * Two values because every rubric distinguishes them and none distinguishes
 * more. The share each carries is a weight that changes with regulation, so it
 * lives in config/bkd.php rather than here.
 */
enum PeranKegiatan: string
{
    case Ketua = 'ketua';
    case Anggota = 'anggota';

    public function label(): string
    {
        return match ($this) {
            self::Ketua => 'Ketua',
            self::Anggota => 'Anggota',
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
