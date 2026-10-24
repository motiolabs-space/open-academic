<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a PDM/PJK request stands.
 *
 * Menunggu: recorded here, nothing filed yet. Diajukan: filed at PDDIKTI
 * admin (letter number on the row) or sent through the web service and
 * awaiting the other end. Selesai: PDDIKTI now holds the new value — the only
 * state in which the student's biodata may be pushed again.
 */
enum StatusPerubahanFeeder: string
{
    case Menunggu = 'menunggu';
    case Diajukan = 'diajukan';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Belum diajukan',
            self::Diajukan => 'Diajukan',
            self::Selesai => 'Selesai',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Menunggu => 'danger',
            self::Diajukan => 'warning',
            self::Selesai => 'success',
        };
    }

    public function terbuka(): bool
    {
        return $this !== self::Selesai;
    }
}
