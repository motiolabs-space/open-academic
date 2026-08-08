<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a final project has got to.
 *
 * Diajukan and Disetujui are deliberately separate from Dibimbing. At most
 * campuses a title is approved in a departmental meeting and supervisors are
 * assigned afterwards, sometimes weeks afterwards — and a student sitting
 * approved-but-unsupervised is a real, common, and invisible failure. Collapsing
 * the two states would hide it; worse, it would push departments to record a
 * placeholder supervisor just to get the title approved.
 */
enum TugasAkhirStatus: string
{
    case Diajukan = 'diajukan';
    case Disetujui = 'disetujui';
    case Dibimbing = 'dibimbing';
    case Selesai = 'selesai';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Judul Diajukan',
            self::Disetujui => 'Judul Disetujui',
            self::Dibimbing => 'Dalam Bimbingan',
            self::Selesai => 'Selesai',
            self::Ditolak => 'Judul Ditolak',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Diajukan => 'warning',
            self::Disetujui, self::Dibimbing => 'info',
            self::Selesai => 'success',
            self::Ditolak => 'danger',
            self::Dibatalkan => 'neutral',
        };
    }

    /**
     * Whether the project still occupies the student's one active slot.
     *
     * This is the predicate behind tugas_akhir.mahasiswa_aktif_id — see the
     * migration for why the database holds the guarantee rather than only this
     * enum.
     *
     * @return array<int, string>
     */
    public static function nilaiAktif(): array
    {
        return [self::Diajukan->value, self::Disetujui->value, self::Dibimbing->value];
    }

    public function aktif(): bool
    {
        return in_array($this->value, self::nilaiAktif(), true);
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
