<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which of PDDIKTI's two correction procedures a locked-column change falls
 * under.
 *
 * PDM (Perubahan Data Mahasiswa) covers the identity a student was reported
 * with; PJK (Perubahan Jenis Keluar) covers how and when a registration was
 * closed. Both are decided at PDDIKTI, not here — this enum only names which
 * form the operator will be filling in.
 */
enum JenisPerubahanFeeder: string
{
    case Pdm = 'pdm';
    case Pjk = 'pjk';

    public function label(): string
    {
        return match ($this) {
            self::Pdm => 'Perubahan Data Mahasiswa',
            self::Pjk => 'Perubahan Jenis Keluar',
        };
    }

    public function singkatan(): string
    {
        return strtoupper($this->value);
    }
}
