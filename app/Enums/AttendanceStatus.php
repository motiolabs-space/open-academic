<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Attendance mark for a single class meeting.
 * H/I/S/A follows the notation lecturers already use on paper registers.
 */
enum AttendanceStatus: string
{
    case Hadir = 'H';
    case Izin = 'I';
    case Sakit = 'S';
    case Alpa = 'A';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Alpa => 'Alpa',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Hadir => 'success',
            self::Izin, self::Sakit => 'warning',
            self::Alpa => 'danger',
        };
    }

    /**
     * Whether the mark counts toward the minimum attendance required to sit
     * the final exam. Excused absences (izin/sakit) count as present.
     */
    public function countsAsPresent(): bool
    {
        return $this !== self::Alpa;
    }
}
