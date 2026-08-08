<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Semester within an academic year.
 *
 * The backing value is the last digit of the PDDIKTI term code:
 * 20261 = odd semester of academic year 2026/2027.
 */
enum SemesterType: string
{
    case Ganjil = '1';
    case Genap = '2';
    case Antara = '3';

    public function label(): string
    {
        return match ($this) {
            self::Ganjil => 'Ganjil',
            self::Genap => 'Genap',
            self::Antara => 'Antara',
        };
    }

    /**
     * Build the PDDIKTI term code for a starting academic year.
     * Example: Ganjil->termCode(2026) === '20261'.
     */
    public function termCode(int $startYear): string
    {
        return $startYear.$this->value;
    }

    /**
     * Human label of an academic year for this semester.
     * Example: Ganjil->academicYearLabel(2026) === '2026/2027 Ganjil'.
     */
    public function academicYearLabel(int $startYear): string
    {
        return sprintf('%d/%d %s', $startYear, $startYear + 1, $this->label());
    }

    public static function fromTermCode(string $termCode): self
    {
        return self::from(substr($termCode, -1));
    }

    /**
     * Year in which the semester starts, taken from a PDDIKTI term code.
     */
    public static function startYearFromTermCode(string $termCode): int
    {
        return (int) substr($termCode, 0, 4);
    }
}
