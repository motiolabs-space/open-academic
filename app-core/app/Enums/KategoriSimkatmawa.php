<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The four tables a SIMKATMAWA report is filed in (ADR-037 §1).
 *
 * Three describe an achievement and are chosen per catalogue entry. The
 * fourth, pembimbing, is not a kind of achievement at all: it lists the
 * lecturers who supervised the achievements in the other three, and is
 * derived from them rather than recorded.
 */
enum KategoriSimkatmawa: string
{
    case Kompetisi = 'kompetisi';
    case Rekognisi = 'rekognisi';
    case Sertifikasi = 'sertifikasi';
    case Pembimbing = 'pembimbing';

    public function label(): string
    {
        return __('simkatmawa.kategori.'.$this->value);
    }

    /** Whether a catalogue entry can be filed under this category. */
    public function dariKatalog(): bool
    {
        return $this !== self::Pembimbing;
    }

    /**
     * Levels the ministry accepts for this category. Recognition and
     * certification have no district level.
     *
     * @return array<int, TingkatSimkatmawa>
     */
    public function tingkatSah(): array
    {
        return match ($this) {
            self::Kompetisi, self::Pembimbing => TingkatSimkatmawa::cases(),
            self::Rekognisi => [TingkatSimkatmawa::Provinsi, TingkatSimkatmawa::Nasional, TingkatSimkatmawa::Internasional],
            self::Sertifikasi => [TingkatSimkatmawa::Nasional, TingkatSimkatmawa::Internasional],
        };
    }

    /** @return array<string, string> the categories a catalogue entry may take */
    public static function optionsKatalog(): array
    {
        return collect(self::cases())
            ->filter(fn (self $k): bool => $k->dariKatalog())
            ->mapWithKeys(fn (self $k): array => [$k->value => $k->label()])
            ->all();
    }
}
