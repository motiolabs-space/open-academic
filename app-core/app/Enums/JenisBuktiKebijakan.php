<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The official document that names the lecturer.
 *
 * IKU 8 is recognised only on a document of this kind; a photo or a
 * certificate of attendance is not one. Recorded beside the file so the
 * reviewer knows what they are about to open.
 */
enum JenisBuktiKebijakan: string
{
    case SkPenugasan = 'sk_penugasan';
    case UndanganResmi = 'undangan_resmi';
    case LaporanFgd = 'laporan_fgd';
    case Notulen = 'notulen';
    case DokumenKebijakan = 'dokumen_kebijakan';

    public function label(): string
    {
        return __('portofolio.kebijakan.bukti.'.$this->value);
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
