<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a workload report has got to.
 *
 * Draft is not a stored state so much as the absence of a submission: while a
 * report is draft its teaching lines are recomputed from live data on every
 * view. Everything from Diajukan onwards reads the frozen snapshot.
 */
enum StatusBkd: string
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case Dinilai = 'dinilai';
    case Disahkan = 'disahkan';
    case Dikembalikan = 'dikembalikan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Diajukan => 'Diajukan',
            self::Dinilai => 'Sudah Dinilai',
            self::Disahkan => 'Disahkan',
            self::Dikembalikan => 'Dikembalikan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Diajukan => 'info',
            self::Dinilai => 'warning',
            self::Disahkan => 'success',
            self::Dikembalikan => 'danger',
        };
    }

    /**
     * Whether the lecturer may still edit.
     *
     * Returned reports become editable again — that is the point of returning
     * one. A report that could only be rejected would leave the lecturer with
     * nothing to do but file a second one.
     */
    public function dapatDisunting(): bool
    {
        return in_array($this, [self::Draft, self::Dikembalikan], true);
    }

    /** Whether the lines are a frozen snapshot rather than a live computation. */
    public function beku(): bool
    {
        return !$this->dapatDisunting();
    }

    public function menungguAsesor(): bool
    {
        return $this === self::Diajukan;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
