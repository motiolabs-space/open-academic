<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Letter grade awarded for a course.
 *
 * The numeric boundaries and weights are institution-configurable through
 * config/academic.php; this enum only fixes the vocabulary. Resolve a score
 * through GradeLetter::fromScore() rather than comparing numbers inline.
 */
enum GradeLetter: string
{
    case A = 'A';
    case AB = 'AB';
    case B = 'B';
    case BC = 'BC';
    case C = 'C';
    case D = 'D';
    case E = 'E';

    /** Grade point (bobot) from the configured scale. */
    public function weight(): float
    {
        foreach (config('academic.grading.scale') as $row) {
            if ($row['letter'] === $this->value) {
                return (float) $row['weight'];
            }
        }

        return 0.0;
    }

    /** Lowest score that still earns this letter. */
    public function minScore(): float
    {
        foreach (config('academic.grading.scale') as $row) {
            if ($row['letter'] === $this->value) {
                return (float) $row['min_score'];
            }
        }

        return 0.0;
    }

    public function isPassing(): bool
    {
        return $this !== self::E;
    }

    /**
     * The passing letters, for use inside a query.
     *
     * Derived from isPassing() rather than listed again: a campus that moves
     * where the pass line sits must not have to remember there is a second copy
     * of the rule hiding in a WHERE clause.
     *
     * @return array<int, string>
     */
    public static function passingValues(): array
    {
        return array_values(array_map(
            fn (self $huruf): string => $huruf->value,
            array_filter(self::cases(), fn (self $huruf): bool => $huruf->isPassing()),
        ));
    }

    /**
     * Resolve a 0–100 score into a letter using the configured scale.
     * The scale is ordered from highest to lowest, so the first match wins.
     */
    public static function fromScore(float $score): self
    {
        foreach (config('academic.grading.scale') as $row) {
            if ($score >= (float) $row['min_score']) {
                return self::from($row['letter']);
            }
        }

        return self::E;
    }

    /** Whether this grade satisfies a course prerequisite. */
    public function satisfiesPrerequisite(): bool
    {
        $minimum = self::from(config('academic.krs.prerequisite_min_grade', 'D'));

        return $this->weight() >= $minimum->weight();
    }
}
