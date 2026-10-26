<?php

declare(strict_types=1);

namespace App\Enums;

/** Why an employment ended. Every one of them closes the account on its TMT. */
enum JenisPensiun: string
{
    case BatasUsia = 'batas_usia';
    case AtasPermintaanSendiri = 'atas_permintaan_sendiri';
    case MeninggalDunia = 'meninggal_dunia';
    case Diberhentikan = 'diberhentikan';

    public function label(): string
    {
        return __('kepegawaian.pensiun.'.$this->value);
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
