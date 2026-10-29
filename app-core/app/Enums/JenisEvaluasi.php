<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What kind of assessment a grade component is.
 *
 * PDDIKTI classifies every component of a class's evaluation plan, and two of
 * the kinds carry weight beyond the grade: a class flagged as case method or
 * team-based project is recognised for IKU 7 only when its participatory and
 * project components together reach half the marks. The kind is therefore not
 * decoration on a component — it is what makes the IKU 7 flag true or false.
 *
 * Backing values are local. The PDDIKTI codes come from the jenis_evaluasi
 * reference pull and live in feeder_mappings, like every other code.
 */
enum JenisEvaluasi: string
{
    case Partisipatif = 'partisipatif';
    case Proyek = 'proyek';
    case Tugas = 'tugas';
    case Kuis = 'kuis';
    case Uts = 'uts';
    case Uas = 'uas';

    public function label(): string
    {
        return match ($this) {
            self::Partisipatif => 'Aktivitas Partisipatif',
            self::Proyek => 'Hasil Proyek',
            self::Tugas => 'Tugas',
            self::Kuis => 'Kuis',
            self::Uts => 'Ujian Tengah Semester',
            self::Uas => 'Ujian Akhir Semester',
        };
    }

    /** Whether this kind counts towards the collaborative-class threshold. */
    public function menghitungKolaboratif(): bool
    {
        return $this === self::Partisipatif || $this === self::Proyek;
    }

    /**
     * The share of marks a collaborative class takes from participatory and
     * project work. Set by the eight-indicator IKU numbering (its IKU 7); the
     * 2026 numbering has no such indicator, and the rule stays as the
     * campus's own definition of the flag (ADR-036 §1).
     */
    public const AMBANG_KOLABORATIF_PERSEN = 50;

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
