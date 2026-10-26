<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which examination of a class's term this session is.
 *
 * Not JenisUjian, which names the final-project examinations. The two share a
 * word and nothing else: these seat a whole class under invigilators.
 *
 * Two cases today. A campus that adds a quiz week or a practical exam adds a
 * case here; nothing else keys off the list of cases, only off the questions
 * below.
 */
enum JenisUjianKelas: string
{
    case Uts = 'uts';
    case Uas = 'uas';

    public function label(): string
    {
        return __('jadwal_ujian.jenis.'.$this->value);
    }

    /** "UTS", for chips and table cells. */
    public function singkatan(): string
    {
        return __('jadwal_ujian.jenis_singkat.'.$this->value);
    }

    /**
     * Whether sitting this exam requires the minimum attendance.
     *
     * Only the final: attendance is measured over the whole term, and at mid-term
     * there is not yet a term's worth of it to fall short of.
     */
    public function butuhKehadiran(): bool
    {
        return $this === self::Uas;
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
