<?php

declare(strict_types=1);

namespace App\Services\Pmb;

use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Mahasiswa;

/**
 * Builds the student number from the campus's own pattern.
 *
 * A NIM is the identifier a person carries for their whole degree, prints on
 * their transcript, and is reported to PDDIKTI with. It is not a surrogate key
 * that can be regenerated when something goes wrong — so this refuses to hand
 * out one it is not certain is free.
 *
 * The pattern lives in config/academic.php because every campus encodes
 * something different: intake year, programme, faculty, admission route.
 */
class NimGenerator
{
    /**
     * How many times to retry when another operator takes the number first.
     *
     * Two staff members accepting applicants in the same second is ordinary
     * during registration week. The unique index on `nim` is the real
     * guarantee; this loop is what turns a collision into a fresh number
     * instead of a 500 in front of a new student.
     */
    private const PERCOBAAN = 25;

    public function untuk(Prodi $prodi, int $angkatan): string
    {
        $awalan = $this->awalan($prodi, $angkatan);
        $panjang = (int) config('academic.nim.sequence_length', 4);

        $urut = $this->urutBerikutnya($awalan, $panjang);

        for ($i = 0; $i < self::PERCOBAAN; $i++) {
            $kandidat = $awalan.str_pad((string) $urut, $panjang, '0', STR_PAD_LEFT);

            // withTrashed: a soft-deleted student still owns their number. It
            // appears on a transcript that was already issued, and handing it
            // to somebody else would make two people share an identity.
            if (!Mahasiswa::withTrashed()->where('nim', $kandidat)->exists()) {
                return $kandidat;
            }

            $urut++;
        }

        throw new AturanAkademikException(
            "Tidak dapat menerbitkan NIM untuk {$prodi->nama} angkatan {$angkatan}: "
                .'seluruh nomor pada pola yang berlaku sudah terpakai. Perpanjang panjang urutan '
                .'pada config/academic.php.',
        );
    }

    /**
     * The pattern with everything but the sequence filled in.
     *
     * Placeholders are documented in config/academic.php; an unknown one is
     * left as-is rather than silently dropped, so a typo shows up in the number
     * itself instead of producing a plausible-looking wrong NIM.
     */
    public function awalan(Prodi $prodi, int $angkatan): string
    {
        return str_replace(
            ['{year}', '{yy}', '{prodi}', '{seq}'],
            [(string) $angkatan, substr((string) $angkatan, -2), $this->kodeProdi($prodi), ''],
            (string) config('academic.nim.pattern', '{yy}{prodi}{seq}'),
        );
    }

    /**
     * The programme's slot in the number.
     *
     * Prefers the PDDIKTI code when present: it is the one identifier that is
     * stable nationally, so a NIM built on it stays meaningful if the campus
     * renames its internal codes.
     */
    private function kodeProdi(Prodi $prodi): string
    {
        return $prodi->kode_pddikti ?: $prodi->kode;
    }

    private function urutBerikutnya(string $awalan, int $panjang): int
    {
        // Case-sensitive on purpose, and stated rather than inherited from the
        // engine's collation: a NIM prefix built from a programme code is an
        // exact string, and "IF" must not match a row beginning "if".
        $terakhir = Mahasiswa::withTrashed()
            ->whereLike('nim', $awalan.'%', caseSensitive: true)
            ->orderByDesc('nim')
            ->value('nim');

        if ($terakhir === null) {
            return 1;
        }

        return ((int) substr($terakhir, -$panjang)) + 1;
    }
}
