<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the numbers say at an evaluation checkpoint.
 *
 * A *finding*, never a verdict. This enum has no case called "drop out",
 * deliberately: the system can observe that a student is below every threshold
 * the campus set, and that observation is still not a decision to end somebody's
 * degree. See KeputusanEvaluasi for the half a person owns.
 */
enum HasilEvaluasi: string
{
    case Lolos = 'lolos';

    /** Below a warning line, but not below a continuation requirement. */
    case Peringatan = 'peringatan';

    /** Below a requirement the campus set for continuing. */
    case TidakMemenuhi = 'tidak_memenuhi';

    public function label(): string
    {
        return match ($this) {
            self::Lolos => 'Memenuhi syarat',
            self::Peringatan => 'Perlu perhatian',
            self::TidakMemenuhi => 'Tidak memenuhi syarat',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Lolos => 'success',
            self::Peringatan => 'warning',
            self::TidakMemenuhi => 'danger',
        };
    }

    /** Whether this finding needs somebody to look at it. */
    public function perluDitindak(): bool
    {
        return $this !== self::Lolos;
    }
}
