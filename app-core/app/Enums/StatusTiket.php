<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a helpdesk ticket is in its life.
 *
 * The states are ordered the way a ticket normally travels, but the thing that
 * matters is the transition table in dapatKe(): it is the only place that says
 * which moves are legal, and the service refuses anything outside it. A ticket
 * that jumps from "baru" straight to "ditutup" is a staff decision (spam, a
 * duplicate); a ticket that goes from "ditutup" to anything is not a move at all
 * — the applicant opens a new one, so the trail of the old one stays intact.
 *
 * "Menunggu pemohon" exists so the SLA clock can tell the two kinds of waiting
 * apart: a ticket waiting on the campus is late; a ticket waiting on the person
 * who filed it is not.
 */
enum StatusTiket: string
{
    case Baru = 'baru';
    case Didisposisi = 'didisposisi';
    case Diproses = 'diproses';
    case MenungguPemohon = 'menunggu_pemohon';
    case Selesai = 'selesai';
    case Ditutup = 'ditutup';

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::Didisposisi => 'Didisposisi',
            self::Diproses => 'Diproses',
            self::MenungguPemohon => 'Menunggu Pemohon',
            self::Selesai => 'Selesai',
            self::Ditutup => 'Ditutup',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Baru => 'info',
            self::Didisposisi, self::Diproses => 'warning',
            self::MenungguPemohon => 'gold',
            self::Selesai => 'success',
            self::Ditutup => 'neutral',
        };
    }

    /** Still somebody's responsibility — counted in queues and by the SLA clock. */
    public function terbuka(): bool
    {
        return !in_array($this, [self::Selesai, self::Ditutup], true);
    }

    /** Waiting on the campus rather than on the applicant. */
    public function menungguKampus(): bool
    {
        return in_array($this, [self::Baru, self::Didisposisi, self::Diproses], true);
    }

    /**
     * Legal next states.
     *
     * @return array<int, self>
     */
    public function tujuanSah(): array
    {
        return match ($this) {
            // A quick public answer may skip disposition entirely; the first
            // reply takes the ticket (see HelpdeskService::balas).
            self::Baru => [self::Didisposisi, self::Diproses, self::MenungguPemohon, self::Ditutup],
            self::Didisposisi => [self::Didisposisi, self::Diproses, self::MenungguPemohon, self::Ditutup],
            self::Diproses => [self::Didisposisi, self::MenungguPemohon, self::Selesai, self::Ditutup],
            self::MenungguPemohon => [self::Diproses, self::Selesai, self::Ditutup],

            // An applicant who replies to a resolved ticket reopens it; staff
            // may also close it for good.
            self::Selesai => [self::Diproses, self::Ditutup],

            self::Ditutup => [],
        };
    }

    public function dapatKe(self $tujuan): bool
    {
        return in_array($tujuan, $this->tujuanSah(), true);
    }

    /**
     * The states a staff member may pick by hand on the ticket screen.
     *
     * Disposition has its own form, and "menunggu pemohon" is what a public
     * reply does — offering them here too would give two buttons that do the
     * same thing with different side effects.
     *
     * @return array<int, self>
     */
    public function pilihanManual(): array
    {
        return array_values(array_filter(
            $this->tujuanSah(),
            fn (self $s): bool => in_array($s, [self::Diproses, self::Selesai, self::Ditutup], true),
        ));
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
