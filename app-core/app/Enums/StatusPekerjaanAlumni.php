<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a graduate was doing when last traced (ADR-037 §2).
 *
 * The five answers of the national tracer-study questionnaire, plus the value
 * a graduate starts with. `belum` is what YudisiumService has always written
 * at graduation — it means "not traced yet", not "not working", which is why
 * the case is named for what it means.
 */
enum StatusPekerjaanAlumni: string
{
    case Bekerja = 'bekerja';
    case Wirausaha = 'wirausaha';
    case StudiLanjut = 'studi_lanjut';
    case MencariKerja = 'mencari_kerja';
    case BelumMemungkinkan = 'belum_memungkinkan';
    case BelumDitelusuri = 'belum';

    public function label(): string
    {
        return __('lulusan.status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Bekerja, self::Wirausaha, self::StudiLanjut => 'success',
            self::MencariKerja, self::BelumMemungkinkan => 'warning',
            self::BelumDitelusuri => 'neutral',
        };
    }

    /** Whether the graduate has been traced at all. */
    public function ditelusuri(): bool
    {
        return $this !== self::BelumDitelusuri;
    }

    /**
     * An answer as a tracer file writes it: our code, or the questionnaire's
     * own wording in any of its common spellings.
     *
     * Order matters. "Tidak kerja tetapi sedang mencari kerja" contains
     * "kerja", and "belum memungkinkan bekerja" contains "bekerja"; both are
     * matched before the plain answer they would otherwise be mistaken for.
     */
    public static function dariTeks(string $teks): ?self
    {
        $t = trim(preg_replace('/[^a-z]+/', ' ', strtolower($teks)) ?? '');

        if ($t === '') {
            return null;
        }

        $kode = self::tryFrom(str_replace(' ', '_', $t));

        if ($kode !== null && $kode !== self::BelumDitelusuri) {
            return $kode;
        }

        return match (true) {
            str_contains($t, 'mencari') => self::MencariKerja,
            str_contains($t, 'belum memungkinkan') => self::BelumMemungkinkan,
            str_contains($t, 'wira') => self::Wirausaha,
            str_contains($t, 'lanjut') || str_contains($t, 'pendidikan') || str_contains($t, 'studi') => self::StudiLanjut,
            str_contains($t, 'kerja') => self::Bekerja,
            default => null,
        };
    }

    /** @return array<string, string> the answers an import may carry */
    public static function optionsTelusur(): array
    {
        return collect(self::cases())
            ->filter(fn (self $s): bool => $s->ditelusuri())
            ->mapWithKeys(fn (self $s): array => [$s->value => $s->label()])
            ->all();
    }
}
