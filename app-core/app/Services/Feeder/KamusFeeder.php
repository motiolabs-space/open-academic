<?php

declare(strict_types=1);

namespace App\Services\Feeder;

/**
 * Reads the field list out of a GetDictionary reply.
 *
 * The reply's shape is not published, and builds differ. Rather than bet on
 * one shape this looks for the fields where every shape seen in the wild
 * keeps them — a map keyed by field name, or a list of rows naming one — and
 * under the wrappers they arrive in (the act's own name, `request`,
 * `record`, ...). A reply in none of those shapes is reported as unreadable,
 * never as an act with no fields: a dictionary that could not be read has
 * not said that nothing is required.
 */
final class KamusFeeder
{
    /** Wrappers a field list arrives under, outermost first. */
    private const WADAH = ['request', 'record', 'kolom', 'columns', 'fields', 'field', 'parameter', 'parameters', 'data'];

    /** Keys a row in list form names its field with. */
    private const KUNCI_NAMA = ['nama_kolom', 'column_name', 'kolom', 'nama_field', 'field', 'column', 'name', 'nama'];

    /** Keys that mark a value as a field's attributes rather than data. */
    private const ATRIBUT = [
        'type', 'tipe', 'data_type', 'required', 'wajib', 'mandatory', 'is_required', 'nullable', 'is_nullable',
        'not_null', 'primary', 'primary_key', 'is_primary', 'keterangan', 'description', 'deskripsi', 'length', 'panjang',
    ];

    private const KEDALAMAN_MAKS = 5;

    /**
     * The fields one reply lists, or null when the reply's shape is not one
     * this recognises.
     *
     * @return array<int, array{nama: string, wajib: bool, kunci: bool, tipe: ?string, keterangan: ?string}>|null
     */
    public static function uraikan(mixed $data, string $act): ?array
    {
        $wadah = self::cariWadah($data, $act, 0, false);

        if ($wadah === null) {
            return null;
        }

        $medan = [];

        foreach ($wadah as $kunci => $isi) {
            $nama = array_is_list($wadah) ? self::namaDariBaris($isi) : (string) $kunci;

            if ($nama === null || $nama === '' || isset($medan[$nama])) {
                continue;
            }

            $medan[$nama] = [
                'nama' => $nama,
                'wajib' => self::wajib($isi),
                'kunci' => self::kunci($isi),
                'tipe' => self::teks($isi, ['type', 'tipe', 'data_type']) ?? (is_string($isi) ? $isi : null),
                'keterangan' => self::teks($isi, ['keterangan', 'description', 'deskripsi']),
            ];
        }

        return $medan === [] ? null : array_values($medan);
    }

    /**
     * The version a reply reports about the Feeder that sent it, if any.
     *
     * @param array<string, mixed> $raw
     */
    public static function versi(array $raw): ?string
    {
        foreach ([$raw, is_array($raw['data'] ?? null) ? $raw['data'] : []] as $tempat) {
            foreach (['versi', 'version', 'versi_feeder', 'app_version'] as $kunci) {
                if (is_scalar($tempat[$kunci] ?? null) && filled($tempat[$kunci])) {
                    return mb_substr((string) $tempat[$kunci], 0, 32);
                }
            }
        }

        return null;
    }

    /** @return array<int|string, mixed>|null */
    private static function cariWadah(mixed $node, string $act, int $kedalaman, bool $diWadah): ?array
    {
        if (!is_array($node) || $node === [] || $kedalaman > self::KEDALAMAN_MAKS) {
            return null;
        }

        if (array_is_list($node)) {
            if (collect($node)->every(fn (mixed $baris): bool => self::namaDariBaris($baris) !== null)) {
                return $node;
            }

            return count($node) === 1 ? self::cariWadah($node[0], $act, $kedalaman + 1, $diWadah) : null;
        }

        if (isset($node[$act])) {
            return self::cariWadah($node[$act], $act, $kedalaman + 1, true);
        }

        foreach (self::WADAH as $kunci) {
            if (is_array($node[$kunci] ?? null)) {
                $hasil = self::cariWadah($node[$kunci], $act, $kedalaman + 1, true);

                if ($hasil !== null) {
                    return $hasil;
                }
            }
        }

        return self::petaMedan($node, $diWadah) ? $node : null;
    }

    /**
     * Whether a map is keyed by field name.
     *
     * Every value must look like a field's attributes. Plain strings count
     * only inside a known wrapper: at the top level a map of strings is far
     * more likely the reply's own metadata than a field list.
     *
     * @param array<string, mixed> $node
     */
    private static function petaMedan(array $node, bool $diWadah): bool
    {
        return collect($node)->every(fn (mixed $isi): bool => is_array($isi)
            ? array_intersect(array_map('strval', array_keys($isi)), self::ATRIBUT) !== []
            : $diWadah && is_string($isi));
    }

    private static function namaDariBaris(mixed $baris): ?string
    {
        if (!is_array($baris) || array_is_list($baris)) {
            return null;
        }

        foreach (self::KUNCI_NAMA as $kunci) {
            if (is_string($baris[$kunci] ?? null) && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $baris[$kunci])) {
                return $baris[$kunci];
            }
        }

        return null;
    }

    private static function wajib(mixed $isi): bool
    {
        if (is_string($isi)) {
            return self::menyebutWajib($isi);
        }

        if (!is_array($isi)) {
            return false;
        }

        foreach (['required', 'wajib', 'mandatory', 'is_required', 'not_null'] as $kunci) {
            if (array_key_exists($kunci, $isi)) {
                return self::benar($isi[$kunci]);
            }
        }

        foreach (['nullable', 'is_nullable'] as $kunci) {
            if (array_key_exists($kunci, $isi)) {
                return !self::benar($isi[$kunci]);
            }
        }

        return self::menyebutWajib((string) (self::teks($isi, ['type', 'tipe', 'data_type']) ?? '').' '.(self::teks($isi, ['keterangan', 'description', 'deskripsi']) ?? ''));
    }

    private static function kunci(mixed $isi): bool
    {
        if (!is_array($isi)) {
            return false;
        }

        foreach (['primary', 'primary_key', 'is_primary'] as $kunci) {
            if (array_key_exists($kunci, $isi)) {
                return self::benar($isi[$kunci]);
            }
        }

        return str_contains(mb_strtolower((string) self::teks($isi, ['type', 'tipe', 'data_type'])), 'primary');
    }

    private static function menyebutWajib(string $teks): bool
    {
        return preg_match('/\b(wajib|required|mandatory|not null|harus diisi)\b/i', $teks) === 1;
    }

    private static function benar(mixed $nilai): bool
    {
        if (is_bool($nilai)) {
            return $nilai;
        }

        if (is_int($nilai) || is_float($nilai)) {
            return $nilai > 0;
        }

        return in_array(mb_strtolower(trim((string) $nilai)), ['1', 'y', 'ya', 'yes', 'true', 'wajib', 'required', 'not null'], true);
    }

    /** @param array<int, string> $kunci */
    private static function teks(mixed $isi, array $kunci): ?string
    {
        if (!is_array($isi)) {
            return null;
        }

        foreach ($kunci as $k) {
            if (is_scalar($isi[$k] ?? null) && filled($isi[$k])) {
                return mb_substr((string) $isi[$k], 0, 255);
            }
        }

        return null;
    }
}
