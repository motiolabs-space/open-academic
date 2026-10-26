<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Off-campus student activity (MBKM / Merdeka Belajar Kampus Merdeka).
 *
 * These records are the transactional source behind IKU 2 ("mahasiswa
 * mendapat pengalaman di luar kampus"), which Open Campus computes from data
 * served over Campus Bridge. Open Academic stores the record; it never scores
 * the indicator.
 */
enum StudentActivityType: string
{
    case Magang = 'magang';
    case StudiIndependen = 'studi_independen';
    case AsistensiMengajar = 'asistensi_mengajar';
    case PertukaranPelajar = 'pertukaran_pelajar';
    case Penelitian = 'penelitian';
    case Wirausaha = 'wirausaha';
    case ProyekKemanusiaan = 'proyek_kemanusiaan';
    case MembangunDesa = 'membangun_desa';
    case BelaNegara = 'bela_negara';

    /*
     * The two field programmes that predate MBKM and run outside it: the
     * regular community-service term and the practical-work placement. They
     * are managed programmes here (#37) and reach PDDIKTI as activities like
     * the nine above, but never flagged as Kampus Merdeka.
     */
    case Kkn = 'kkn';
    case Pkl = 'pkl';

    public function label(): string
    {
        return match ($this) {
            self::Magang => 'Magang / Praktik Kerja',
            self::StudiIndependen => 'Studi Independen',
            self::AsistensiMengajar => 'Asistensi Mengajar di Satuan Pendidikan',
            self::PertukaranPelajar => 'Pertukaran Pelajar',
            self::Penelitian => 'Penelitian / Riset',
            self::Wirausaha => 'Kegiatan Wirausaha',
            self::ProyekKemanusiaan => 'Proyek Kemanusiaan',
            self::MembangunDesa => 'Membangun Desa / KKN Tematik',
            self::BelaNegara => 'Bela Negara',
            self::Kkn => 'Kuliah Kerja Nyata (KKN)',
            self::Pkl => 'Praktik Kerja Lapangan (PKL)',
        };
    }

    /**
     * Whether the activity type is recognised for IKU 2 credit conversion.
     * All nine MBKM tracks qualify; the 20-SKS threshold is evaluated on the
     * converted credits, not on the type.
     */
    public function qualifiesForIku2(): bool
    {
        return true;
    }

    /**
     * Whether PDDIKTI should record the activity as Kampus Merdeka.
     *
     * Sent as `untuk_kampus_merdeka`. A regular KKN reported as MBKM would be
     * counted in the ministry's MBKM figures for a programme that never was.
     */
    public function kampusMerdeka(): bool
    {
        return !in_array($this, [self::Kkn, self::Pkl], true);
    }

    /**
     * The kinds a managed field programme can be run as: the regular KKN and
     * PKL, and their MBKM counterparts when the campus runs them as such.
     *
     * @return array<int, self>
     */
    public static function kegiatanLapangan(): array
    {
        return [self::Kkn, self::MembangunDesa, self::Pkl, self::Magang];
    }

    /** @return array<string, string> */
    public static function opsiKegiatanLapangan(): array
    {
        return array_reduce(
            self::kegiatanLapangan(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
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
