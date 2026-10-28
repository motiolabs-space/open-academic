<?php

declare(strict_types=1);

namespace App\Enums;

/** How an academic advisor came to be assigned — kept on each history row. */
enum CaraPenetapanWali: string
{
    /** One student, from the advisor screen or the student's detail page. */
    case Perorangan = 'perorangan';

    /** A cohort split evenly across the chosen lecturers. */
    case Merata = 'merata';

    /** A cohort assigned row by row on the advisor screen. */
    case Manual = 'manual';

    public function label(): string
    {
        return __('dosen_wali.cara.'.$this->value);
    }
}
