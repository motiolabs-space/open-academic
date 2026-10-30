<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The body that accredited a programme.
 *
 * BAN-PT for programmes not yet under a discipline body, otherwise the LAM
 * for the field. Extended when the ministry recognises a new LAM — a new
 * case, never free text, so a report can group by it (ADR-041 §3).
 */
enum LembagaAkreditasi: string
{
    case BanPt = 'ban_pt';
    case LamPtkes = 'lam_ptkes';
    case Lamdik = 'lamdik';
    case Lamemba = 'lamemba';
    case Lamsama = 'lamsama';
    case Laminfokom = 'laminfokom';
    case LamTeknik = 'lam_teknik';
    case Lamspak = 'lamspak';
    case Internasional = 'internasional';

    public function label(): string
    {
        return __('akreditasi.lembaga.'.$this->value);
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
