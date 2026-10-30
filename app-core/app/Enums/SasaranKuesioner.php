<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;

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

    /** The audience a signed-in person belongs to, or null for anyone else. */
    public static function untuk(object $responden): ?self
    {
        return match (true) {
            $responden instanceof Mahasiswa => self::Mahasiswa,
            $responden instanceof Dosen => self::Dosen,
            $responden instanceof Staff => self::Staf,
            default => null,
        };
    }

    /** Students and lecturers belong to a study programme; staff do not. */
    public function berprodi(): bool
    {
        return $this !== self::Staf;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s): array => [$s->value => $s->label()])->all();
    }
}
