<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Study leave, or permission to study alongside the job.
 *
 * The distinction is the whole reason this is an enum. Tugas belajar releases a
 * lecturer from teaching, and with it from the workload report; izin belajar
 * leaves every obligation in place. Recording both under one word would make
 * BKD either chase people on leave or excuse people who are not.
 */
enum JenisStudiLanjut: string
{
    case TugasBelajar = 'tugas_belajar';
    case IzinBelajar = 'izin_belajar';

    public function label(): string
    {
        return __('kepegawaian.studi_lanjut.'.$this->value);
    }

    /** Whether this suspends the obligation to report BKD while it lasts. */
    public function membebaskanBkd(): bool
    {
        return $this === self::TugasBelajar;
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
