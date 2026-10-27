<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a row of the fee matrix charges for.
 *
 * Two families, and the difference decides *when* a row is billed:
 *
 *  - **Fixed** components (UKT, SPP, …) are owed for being enrolled in the term.
 *    They are issued to a cohort up front by `PenerbitanTagihanService`, and they
 *    are what the KRS payment gate measures.
 *  - **KRS-derived** components are owed for what the student actually takes:
 *    `per_sks` is a rate per credit, `mata_kuliah` a flat fee for one named
 *    course (a paid lab, KKN). They cannot be known before the plan exists, so
 *    `TagihanKrsService` bills them when the advisor approves it. See ADR-030.
 *
 * This list used to live as a private constant on the tariff controller, which
 * meant the resolver could not tell the two families apart.
 */
enum KomponenTarif: string
{
    case Ukt = 'ukt';
    case Spp = 'spp';
    case Praktikum = 'praktikum';
    case Registrasi = 'registrasi';
    case Wisuda = 'wisuda';
    case Lainnya = 'lainnya';
    case PerSks = 'per_sks';
    case MataKuliah = 'mata_kuliah';

    public function label(): string
    {
        return __('keuangan.komponen.'.$this->value);
    }

    /** Whether the amount follows from the approved study plan. */
    public function dariKrs(): bool
    {
        return in_array($this, self::dariKrsSemua(), true);
    }

    /** @return array<int, self> */
    public static function dariKrsSemua(): array
    {
        return [self::PerSks, self::MataKuliah];
    }

    /** @return array<int, string> */
    public static function nilaiDariKrs(): array
    {
        return array_map(fn (self $k): string => $k->value, self::dariKrsSemua());
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
