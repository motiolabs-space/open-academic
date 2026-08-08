<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A seat on an examining panel.
 *
 * Supervisors routinely occupy one of these — usually Sekretaris — and that is
 * ordinary practice, not a conflict to be blocked. The rule that matters lives
 * in UjianService: a panel must contain at least one examiner who is not
 * supervising the work.
 */
enum PeranPenguji: string
{
    case Ketua = 'ketua';
    case Sekretaris = 'sekretaris';
    case Anggota = 'anggota';

    public function label(): string
    {
        return match ($this) {
            self::Ketua => 'Ketua Penguji',
            self::Sekretaris => 'Sekretaris',
            self::Anggota => 'Anggota Penguji',
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
