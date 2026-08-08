<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a study plan (Kartu Rencana Studi).
 *
 * draft -> diajukan -> disetujui | ditolak
 *
 * A rejected KRS returns to draft so the student can revise it; every
 * transition is an event recorded in the activity log, never an overwrite.
 */
enum KrsStatus: string
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Diajukan => 'Diajukan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Diajukan => 'warning',
            self::Disetujui => 'success',
            self::Ditolak => 'danger',
        };
    }

    /** Courses may only be added or removed while the plan is editable. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Ditolak], true);
    }

    public function isLocked(): bool
    {
        return $this === self::Disetujui;
    }

    /** Valid successor states, used to guard service-layer transitions. */
    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Diajukan],
            self::Diajukan => [self::Disetujui, self::Ditolak],
            self::Ditolak => [self::Diajukan],
            self::Disetujui => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
