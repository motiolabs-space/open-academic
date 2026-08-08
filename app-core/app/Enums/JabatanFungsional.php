<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The academic rank ladder.
 *
 * Four rungs plus the state before the first one. `TenagaPengajar` is not a
 * rank — it is what a newly appointed lecturer holds until their first
 * appointment decree, and it exists here because leaving it out would force
 * somebody to record a rank that has not been awarded.
 *
 * The minimum credit scores are the national thresholds and belong to the rank,
 * not to a config file, because they are what the rank *means*. What varies by
 * regulation is how credit is earned; what it takes to hold Lektor Kepala has
 * been 550 for a long time.
 */
enum JabatanFungsional: string
{
    case TenagaPengajar = 'tenaga_pengajar';
    case AsistenAhli = 'asisten_ahli';
    case Lektor = 'lektor';
    case LektorKepala = 'lektor_kepala';
    case GuruBesar = 'guru_besar';

    public function label(): string
    {
        return match ($this) {
            self::TenagaPengajar => 'Tenaga Pengajar',
            self::AsistenAhli => 'Asisten Ahli',
            self::Lektor => 'Lektor',
            self::LektorKepala => 'Lektor Kepala',
            self::GuruBesar => 'Guru Besar (Profesor)',
        };
    }

    /** Minimum cumulative credit, in whole points. */
    public function angkaKreditMinimum(): int
    {
        return match ($this) {
            self::TenagaPengajar => 0,
            self::AsistenAhli => 150,
            self::Lektor => 200,
            self::LektorKepala => 550,
            self::GuruBesar => 850,
        };
    }

    /**
     * Rank order, for comparing two appointments.
     *
     * Ranks are stored as strings, so "is this a promotion" cannot be answered
     * by comparing values. This is the only place that ordering lives.
     */
    public function tingkat(): int
    {
        return match ($this) {
            self::TenagaPengajar => 0,
            self::AsistenAhli => 1,
            self::Lektor => 2,
            self::LektorKepala => 3,
            self::GuruBesar => 4,
        };
    }

    /**
     * Whether this rank may supervise a doctoral candidate on its own.
     *
     * Recorded here because it is the question most often asked of a rank, and
     * answering it from a label comparison scattered across screens is how two
     * screens end up disagreeing.
     */
    public function dapatMembimbingDoktor(): bool
    {
        return $this->tingkat() >= self::LektorKepala->tingkat();
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
