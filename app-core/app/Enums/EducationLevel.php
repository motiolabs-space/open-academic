<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Degree level of a study programme (jenjang pendidikan).
 *
 * Backing values are the PDDIKTI "id_jenjang_didik" codes. Neo Feeder is the
 * authority: the pull of feeder_ref_jenjang_pendidikan overrides this list if
 * an institution runs a build with a different code set.
 */
enum EducationLevel: string
{
    case D1 = '10';
    case D2 = '15';
    case D3 = '20';
    case D4 = '21';
    case S1 = '30';
    case S2 = '35';
    case S2Terapan = '36';
    case S3 = '40';
    case S3Terapan = '41';
    case Profesi = '05';
    case Spesialis1 = '85';
    case Spesialis2 = '90';

    public function label(): string
    {
        return match ($this) {
            self::D1 => 'D1',
            self::D2 => 'D2',
            self::D3 => 'D3',
            self::D4 => 'D4 / Sarjana Terapan',
            self::S1 => 'S1',
            self::S2 => 'S2',
            self::S2Terapan => 'S2 Terapan',
            self::S3 => 'S3',
            self::S3Terapan => 'S3 Terapan',
            self::Profesi => 'Profesi',
            self::Spesialis1 => 'Spesialis 1',
            self::Spesialis2 => 'Spesialis 2',
        };
    }

    /** Nominal study duration in semesters. */
    public function normalSemesters(): int
    {
        return match ($this) {
            self::D1 => 2,
            self::D2 => 4,
            self::D3 => 6,
            self::D4, self::S1 => 8,
            self::S2, self::S2Terapan, self::Profesi, self::Spesialis1 => 4,
            self::S3, self::S3Terapan, self::Spesialis2 => 6,
        };
    }

    /**
     * The KKNI level this degree sits at.
     *
     * Printed on the diploma supplement, where it is the field a foreign reader
     * actually uses — it is what maps an Indonesian qualification onto their own
     * framework. Derived from the degree because the mapping is fixed by
     * regulation and a stored copy would drift the moment a programme's level
     * was corrected.
     */
    public function jenjangKkni(): int
    {
        return match ($this) {
            self::D1 => 3,
            self::D2 => 4,
            self::D3 => 5,
            self::D4, self::S1 => 6,
            self::Profesi => 7,
            self::S2, self::S2Terapan, self::Spesialis1 => 8,
            self::S3, self::S3Terapan, self::Spesialis2 => 9,
        };
    }

    /** The English name of the degree, for the bilingual supplement. */
    public function labelInggris(): string
    {
        return match ($this) {
            self::D1 => 'Diploma I',
            self::D2 => 'Diploma II',
            self::D3 => 'Diploma III',
            self::D4 => 'Applied Bachelor (D4)',
            self::S1 => "Bachelor's Degree",
            self::S2 => "Master's Degree",
            self::S2Terapan => "Applied Master's Degree",
            self::S3 => 'Doctoral Degree',
            self::S3Terapan => 'Applied Doctoral Degree',
            self::Profesi => 'Professional Programme',
            self::Spesialis1 => 'Specialist I',
            self::Spesialis2 => 'Specialist II',
        };
    }

    /**
     * What this level calls its final project.
     *
     * Derived rather than stored: the name follows from the degree with no
     * exceptions, and a stored copy would drift the moment a programme's level
     * was corrected. It is a label, never a rule — every level below treats
     * the work identically.
     */
    public function sebutanTugasAkhir(): string
    {
        return match ($this) {
            self::S3, self::S3Terapan => 'Disertasi',
            self::S2, self::S2Terapan, self::Spesialis1, self::Spesialis2 => 'Tesis',
            self::S1, self::D4 => 'Skripsi',
            default => 'Tugas Akhir',
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
