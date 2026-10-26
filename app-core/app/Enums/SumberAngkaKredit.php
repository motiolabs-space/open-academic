<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a credit line came from.
 *
 * Two are derived — rebuilt from an endorsed BKD report or a degree on the
 * portfolio whenever the ledger is reassembled, and never typed. Two are
 * entered by the personnel office: semesters from before this application
 * existed, converted at the regulation's factor, and adjustments with a
 * written reason. A reviewer of the ledger asks "who decided this number"
 * first, and this is the answer.
 */
enum SumberAngkaKredit: string
{
    case Bkd = 'bkd';
    case PendidikanFormal = 'pendidikan_formal';
    case KonversiManual = 'konversi_manual';
    case Penyesuaian = 'penyesuaian';

    public function label(): string
    {
        return __('angka_kredit.sumber.'.$this->value);
    }

    /** Rebuilt from its source on every reassembly, and never edited by hand. */
    public function turunan(): bool
    {
        return in_array($this, [self::Bkd, self::PendidikanFormal], true);
    }
}
