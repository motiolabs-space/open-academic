<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who a calendar entry is meant for.
 *
 * `Semua` is a value of its own rather than "every other case at once", so a
 * filter for lecturers matches entries meant for lecturers and entries meant
 * for everyone — and nothing else.
 */
enum SasaranPeristiwa: string
{
    case Semua = 'semua';
    case Mahasiswa = 'mahasiswa';
    case Dosen = 'dosen';
    case Staf = 'staf';

    public function label(): string
    {
        return __('kalender.sasaran.'.$this->value);
    }

    /**
     * The audiences a portal may see.
     *
     * A lecturer sees what is aimed at students too — an advisor is asked
     * about the KRS deadline more often than anyone — but not the staff-only
     * entries. A student sees what is aimed at students and at everyone.
     *
     * @return list<self>
     */
    public static function terlihatOleh(UserRole $peran): array
    {
        return match ($peran) {
            UserRole::Staff => self::cases(),
            UserRole::Dosen => [self::Semua, self::Dosen, self::Mahasiswa],
            UserRole::Mahasiswa => [self::Semua, self::Mahasiswa],
        };
    }

    /**
     * @param list<self>|null $kasus
     * @return array<string, string>
     */
    public static function options(?array $kasus = null): array
    {
        return array_reduce(
            $kasus ?? self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
