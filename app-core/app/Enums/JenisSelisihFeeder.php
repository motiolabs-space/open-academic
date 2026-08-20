<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How one row differs between this application and PDDIKTI.
 *
 * The three cases are not variations of one problem — each has a different
 * cause and a different remedy, and collapsing them into "tidak cocok" would
 * hide the only part an operator needs in order to act.
 */
enum JenisSelisihFeeder: string
{
    /** Here, not there. Either never pushed, or removed at the other end. */
    case HanyaLokal = 'hanya_lokal';

    /**
     * There, not here.
     *
     * Usually a row entered directly in Feeder, or one left behind by data
     * this application has since deleted. It is the case a push-only sync can
     * never notice, and the reason this comparison exists.
     */
    case HanyaFeeder = 'hanya_feeder';

    /** Present at both ends, disagreeing on at least one field we send. */
    case Berbeda = 'berbeda';

    /**
     * Present, but carrying no usable key on one side.
     *
     * Reported rather than dropped: a row that cannot be matched is not a row
     * that agrees, and counting it as agreement is how a comparison starts
     * lying.
     */
    case TanpaKunci = 'tanpa_kunci';

    public function label(): string
    {
        return match ($this) {
            self::HanyaLokal => 'Hanya di SIAKAD',
            self::HanyaFeeder => 'Hanya di Feeder',
            self::Berbeda => 'Isinya berbeda',
            self::TanpaKunci => 'Tidak dapat dicocokkan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::HanyaLokal => 'warning',
            self::HanyaFeeder => 'danger',
            self::Berbeda => 'danger',
            self::TanpaKunci => 'neutral',
        };
    }

    /** What an operator is expected to do about it. */
    public function saran(): string
    {
        return match ($this) {
            self::HanyaLokal => 'Jalankan sinkronisasi untuk entitas ini, lalu bandingkan ulang.',
            self::HanyaFeeder => 'Periksa di Feeder: baris ini tidak berasal dari SIAKAD.',
            self::Berbeda => 'Tentukan sisi mana yang benar sebelum melapor.',
            self::TanpaKunci => 'Lengkapi data kuncinya di SIAKAD.',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
