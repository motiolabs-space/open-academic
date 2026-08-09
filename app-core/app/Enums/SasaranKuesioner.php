<?php

declare(strict_types=1);

namespace App\Enums;

/** Who a questionnaire is put to. One audience per form, never a mixture. */
enum SasaranKuesioner: string
{
    case Mahasiswa = 'mahasiswa';
    case Dosen = 'dosen';
    case Staf = 'staf';

    public function label(): string
    {
        return match ($this) {
            self::Mahasiswa => 'Mahasiswa',
            self::Dosen => 'Dosen',
            self::Staf => 'Staf',
        };
    }

    /** The auth guard whose user may answer this. */
    public function guard(): string
    {
        return match ($this) {
            self::Mahasiswa => 'mahasiswa',
            self::Dosen => 'dosen',
            self::Staf => 'staff',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s): array => [$s->value => $s->label()])->all();
    }
}
