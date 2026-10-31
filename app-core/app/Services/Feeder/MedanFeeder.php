<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\Models\Kemahasiswaan\Yudisium;

/**
 * Feeder field names a campus may set, and their safe defaults.
 *
 * Two names could not be settled from public documents (ROADMAP #52, #53):
 * the mobile number on InsertBiodataMahasiswa and the diploma date on
 * InsertMahasiswaLulusDO. Both come from config so a campus can follow its
 * own Feeder's dictionary — which the dictionary check reads and suggests —
 * without a code change. A value that is not a plausible field name is
 * ignored: the mobile number falls back to the default name, the diploma
 * date stays unsent.
 */
final class MedanFeeder
{
    public const HP_BAWAAN = 'telepon_seluler';

    /** Where the diploma date is read from, by `feeder.medan.tanggal_ijazah_sumber`. */
    public const SUMBER_TANGGAL_IJAZAH = ['lulus', 'yudisium', 'wisuda'];

    /** The name the biodata carries the mobile number under. */
    public static function hp(): string
    {
        $nama = config('feeder.medan.telepon_seluler');

        return self::sah($nama) ? $nama : self::HP_BAWAAN;
    }

    /** The name the Lulus/DO record carries the diploma date under, or null: not sent. */
    public static function tanggalIjazah(): ?string
    {
        $nama = config('feeder.medan.tanggal_ijazah');

        return self::sah($nama) ? $nama : null;
    }

    public static function sumberTanggalIjazah(): string
    {
        $sumber = (string) config('feeder.medan.tanggal_ijazah_sumber', 'lulus');

        return in_array($sumber, self::SUMBER_TANGGAL_IJAZAH, true) ? $sumber : 'lulus';
    }

    /**
     * The diploma date of a confirmed graduation, from the configured source.
     *
     * `lulus` is the graduation date the yudisium records (the one PISN
     * reads); `yudisium` the date of the decree; `wisuda` the earliest
     * ceremony the graduate is registered for — null until they are.
     */
    public static function tanggalIjazahDari(Yudisium $yudisium): ?\DateTimeInterface
    {
        return match (self::sumberTanggalIjazah()) {
            'yudisium' => $yudisium->tanggal_yudisium,
            'wisuda' => $yudisium->pesertaWisuda
                ->map(fn ($peserta) => $peserta->periode?->tanggal)
                ->filter()
                ->sort()
                ->first(),
            default => $yudisium->tanggal_lulus,
        };
    }

    /**
     * Config values that are set but cannot be used, keyed by env name.
     *
     * @return array<string, string>
     */
    public static function masalah(): array
    {
        $masalah = [];

        foreach (['FEEDER_MEDAN_HP' => 'telepon_seluler', 'FEEDER_MEDAN_TANGGAL_IJAZAH' => 'tanggal_ijazah'] as $env => $kunci) {
            $nilai = config('feeder.medan.'.$kunci);

            if (filled($nilai) && !self::sah($nilai)) {
                $masalah[$env] = (string) $nilai;
            }
        }

        return $masalah;
    }

    private static function sah(mixed $nama): bool
    {
        return is_string($nama) && preg_match('/^[a-z][a-z0-9_]{1,63}$/', $nama) === 1;
    }
}
