<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The quartile of the journal in its index, in the year of publication.
 *
 * Recorded as the fact on the index's own page. The weights Kepmen 358
 * attaches to each quartile, and its "top tier" class above Q1, are the
 * consumer's to apply (ADR-041 §2).
 */
enum KuartilPublikasi: string
{
    case Q1 = 'q1';
    case Q2 = 'q2';
    case Q3 = 'q3';
    case Q4 = 'q4';

    public function label(): string
    {
        return strtoupper($this->value);
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
