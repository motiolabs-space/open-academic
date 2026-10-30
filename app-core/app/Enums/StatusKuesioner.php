<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a questionnaire stands, read from its columns rather than stored.
 *
 * Draft until first published; published while `is_active`; closed when it was
 * published once and is no longer active. A closed questionnaire may be opened
 * again — its questions stay frozen either way.
 */
enum StatusKuesioner: string
{
    case Draf = 'draf';
    case Terbit = 'terbit';
    case Ditutup = 'ditutup';

    public function label(): string
    {
        return __('kuesioner.status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draf => 'neutral',
            self::Terbit => 'success',
            self::Ditutup => 'warning',
        };
    }
}
