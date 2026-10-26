<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether an exam session is still being arranged or has been announced.
 *
 * A draft is invisible outside the registrar's screen: students and lecturers
 * plan their week around what they are shown, so they are shown only what has
 * passed the clash check. Publishing is the moment that check is enforced.
 */
enum StatusJadwalUjian: string
{
    case Draf = 'draf';
    case Terbit = 'terbit';

    public function label(): string
    {
        return __('jadwal_ujian.status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draf => 'neutral',
            self::Terbit => 'success',
        };
    }
}
