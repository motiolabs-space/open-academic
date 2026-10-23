<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a student entered the programme — PDDIKTI's "jenis pendaftaran".
 *
 * It decides what the enrolment record must carry: a transfer or a
 * recognition of prior learning is refused by Feeder without the credits
 * recognised and the institution they came from, and refused again at the
 * diploma-number portal when those credits fall short. The codes themselves
 * come from the mapping table, like every other PDDIKTI code.
 */
enum JenisDaftar: string
{
    case Baru = 'baru';
    case Pindahan = 'pindahan';
    case RplTransfer = 'rpl_transfer';
    case RplPerolehan = 'rpl_perolehan';
    case FastTrack = 'fast_track';
    case NonGelar = 'non_gelar';

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Peserta didik baru',
            self::Pindahan => 'Pindahan',
            self::RplTransfer => 'RPL — transfer SKS',
            self::RplPerolehan => 'RPL — perolehan SKS',
            self::FastTrack => 'Fast track',
            self::NonGelar => 'Non-gelar',
        };
    }

    /** Whether PDDIKTI requires recognised credits and an origin for this kind. */
    public function butuhAsal(): bool
    {
        return in_array($this, [self::Pindahan, self::RplTransfer, self::RplPerolehan], true);
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
