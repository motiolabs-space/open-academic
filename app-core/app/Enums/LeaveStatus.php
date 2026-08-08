<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Approval state of a study-leave request (pengajuan cuti akademik).
 *
 * Approval flips the student's per-term status to StudentStatus::Cuti, which
 * is what eventually reaches PDDIKTI through the Feeder sync.
 */
enum LeaveStatus: string
{
    case Diajukan = 'diajukan';
    case DisetujuiProdi = 'disetujui_prodi';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Diajukan',
            self::DisetujuiProdi => 'Disetujui Program Studi',
            self::Disetujui => 'Disetujui BAAK',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Diajukan, self::DisetujuiProdi => 'warning',
            self::Disetujui => 'success',
            self::Ditolak => 'danger',
            self::Dibatalkan => 'neutral',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Disetujui, self::Ditolak, self::Dibatalkan], true);
    }
}
