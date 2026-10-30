<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kinds of non-competitive recognition SIMKATMAWA accepts.
 *
 * Competency certificates are one of these in the ministry's guide but have
 * their own category here (KategoriSimkatmawa::Sertifikasi): they need a
 * certificate number and an issuing body, which the others do not.
 */
enum JenisRekognisi: string
{
    case Karya = 'karya';
    case JuriPelatihWasit = 'juri_pelatih_wasit';
    case KeynoteKonferensi = 'keynote_konferensi';
    case KeynoteWorkshop = 'keynote_workshop';
    case PameranSeni = 'pameran_seni';
    case CiptaLaguTari = 'cipta_lagu_tari';
    case PenulisBuku = 'penulis_buku';
    case Paten = 'paten';
    case Publikasi = 'publikasi';
    case Duta = 'duta';

    public function label(): string
    {
        return __('simkatmawa.rekognisi.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])
            ->all();
    }
}
