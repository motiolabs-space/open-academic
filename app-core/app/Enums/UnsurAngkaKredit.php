<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The five elements a lecturer's credit is counted in — Kum A to E.
 *
 * Not UnsurBkd with one more case. BKD has four elements because a semester
 * has four kinds of work; a credit file has five because formal education (a
 * degree earned) counts on its own, apart from teaching. BKD's "pendidikan" is
 * Kum B here, not Kum A — the one mapping that is easy to get wrong, so it
 * lives in dariUnsurBkd() and nowhere else.
 */
enum UnsurAngkaKredit: string
{
    case Pendidikan = 'pendidikan';
    case Pengajaran = 'pengajaran';
    case Penelitian = 'penelitian';
    case Pengabdian = 'pengabdian';
    case Penunjang = 'penunjang';

    /** The letter the ministry's forms use. */
    public function kode(): string
    {
        return match ($this) {
            self::Pendidikan => 'A',
            self::Pengajaran => 'B',
            self::Penelitian => 'C',
            self::Pengabdian => 'D',
            self::Penunjang => 'E',
        };
    }

    public function label(): string
    {
        return __('angka_kredit.unsur.'.$this->value);
    }

    public static function dariUnsurBkd(UnsurBkd $unsur): self
    {
        return match ($unsur) {
            UnsurBkd::Pendidikan => self::Pengajaran,
            UnsurBkd::Penelitian => self::Penelitian,
            UnsurBkd::Pengabdian => self::Pengabdian,
            UnsurBkd::Penunjang => self::Penunjang,
        };
    }

    /** Whether the regulation caps this element's share of the total. */
    public function dibatasi(): bool
    {
        return in_array($this, [self::Pengabdian, self::Penunjang], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->kode().' — '.$case->label()],
            [],
        );
    }
}
