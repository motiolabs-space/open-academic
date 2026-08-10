<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The life of one audit finding.
 *
 * Closing is one way. A finding that can be reopened and edited is a finding
 * that can be smoothed over before a site visit, and the entire value of an
 * internal audit rests on that being impossible.
 */
enum StatusTemuan: string
{
    case Terbuka = 'terbuka';
    case Ditindaklanjuti = 'ditindaklanjuti';
    case Ditutup = 'ditutup';

    public function label(): string
    {
        return match ($this) {
            self::Terbuka => 'Terbuka',
            self::Ditindaklanjuti => 'Ditindaklanjuti',
            self::Ditutup => 'Ditutup',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Terbuka => 'danger',
            self::Ditindaklanjuti => 'warning',
            self::Ditutup => 'success',
        };
    }

    public function dapatDiubah(): bool
    {
        return $this !== self::Ditutup;
    }
}
