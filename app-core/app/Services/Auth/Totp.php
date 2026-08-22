<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Random\RandomException;

/**
 * Time-based one-time passwords, RFC 6238.
 *
 * Implemented here rather than pulled in, because the whole of it is an HMAC
 * over a counter plus base32 — no cryptography is being invented. PHP's
 * hash_hmac does the only part that matters, and the algorithm is fixed by a
 * published standard with published test vectors, so it can be *verified*
 * rather than trusted. Those vectors are in the test suite.
 *
 * Three details decide whether a TOTP implementation is safe, and all three are
 * easy to get subtly wrong:
 *
 *  1. **The comparison is constant-time.** A normal string compare leaks how
 *     many leading digits were right, one timing measurement at a time.
 *
 *  2. **The window is small and symmetric.** One step either side absorbs
 *     ordinary clock drift between a phone and a server. A wider window is a
 *     wider guessing target for the same six digits.
 *
 *  3. **Replay is not this class's problem, and it says so.** A code stays
 *     valid for its whole step, so the caller must refuse a code it has already
 *     accepted — see DuaFaktorService.
 */
class Totp
{
    /** Seconds per code. Thirty is what every authenticator app assumes. */
    public const PERIODE = 30;

    public const DIGIT = 6;

    private const ALFABET_BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * A fresh shared secret, base32 as the authenticator apps expect.
     *
     * 160 bits, the size RFC 4226 recommends for HMAC-SHA1.
     *
     * @throws RandomException
     */
    public function rahasiaBaru(): string
    {
        return $this->keBase32(random_bytes(20));
    }

    /**
     * The code for one moment.
     *
     * Time comes from Carbon rather than time() so tests can move it. A
     * replay window that cannot be tested is a replay window nobody has
     * checked.
     */
    public function kode(string $rahasia, ?int $waktu = null): string
    {
        $langkah = intdiv($waktu ?? now()->getTimestamp(), self::PERIODE);

        $hmac = hash_hmac(
            'sha1',
            pack('J', $langkah),
            $this->dariBase32($rahasia),
            true,
        );

        // Dynamic truncation, RFC 4226 §5.4: the low nibble of the last byte
        // picks where in the digest to read from.
        $geser = ord($hmac[19]) & 0x0F;

        $angka = (
            ((ord($hmac[$geser]) & 0x7F) << 24)
            | (ord($hmac[$geser + 1]) << 16)
            | (ord($hmac[$geser + 2]) << 8)
            | ord($hmac[$geser + 3])
        ) % (10 ** self::DIGIT);

        return str_pad((string) $angka, self::DIGIT, '0', STR_PAD_LEFT);
    }

    /**
     * Whether a typed code matches, allowing one step of clock drift each way.
     *
     * @param int $toleransi steps either side; 1 is ±30 seconds
     */
    public function cocok(string $rahasia, string $kode, int $toleransi = 1, ?int $waktu = null): bool
    {
        $kode = trim($kode);

        if (!preg_match('/^\d{'.self::DIGIT.'}$/', $kode)) {
            return false;
        }

        $sekarang = $waktu ?? now()->getTimestamp();
        $cocok = false;

        for ($i = -$toleransi; $i <= $toleransi; $i++) {
            // Every candidate is compared, and the loop is not cut short on a
            // hit: returning early would make a correct code measurably faster
            // than a wrong one.
            $cocok = hash_equals($this->kode($rahasia, $sekarang + ($i * self::PERIODE)), $kode) || $cocok;
        }

        return $cocok;
    }

    /**
     * The otpauth:// URI an authenticator app reads from a QR square.
     *
     * The issuer appears twice — as a label prefix and as a parameter —
     * deliberately: older apps read only one of the two, and an entry that says
     * merely "administrator" is unusable on a phone holding a dozen of them.
     */
    public function uri(string $rahasia, string $akun, string $penerbit): string
    {
        return 'otpauth://totp/'.rawurlencode($penerbit.':'.$akun).'?'.http_build_query([
            'secret' => $rahasia,
            'issuer' => $penerbit,
            'algorithm' => 'SHA1',
            'digits' => self::DIGIT,
            'period' => self::PERIODE,
        ]);
    }

    /* ---------------------------------------------------------------------
     | Base32 (RFC 4648, tanpa padding)
     |-------------------------------------------------------------------- */

    private function keBase32(string $biner): string
    {
        $bit = '';

        foreach (str_split($biner) as $bita) {
            $bit .= str_pad(decbin(ord($bita)), 8, '0', STR_PAD_LEFT);
        }

        $hasil = '';

        foreach (str_split($bit, 5) as $potongan) {
            $hasil .= self::ALFABET_BASE32[bindec(str_pad($potongan, 5, '0', STR_PAD_RIGHT))];
        }

        return $hasil;
    }

    private function dariBase32(string $base32): string
    {
        $base32 = rtrim(strtoupper(trim($base32)), '=');
        $bit = '';

        foreach (str_split($base32) as $huruf) {
            $posisi = strpos(self::ALFABET_BASE32, $huruf);

            if ($posisi === false) {
                continue;
            }

            $bit .= str_pad(decbin($posisi), 5, '0', STR_PAD_LEFT);
        }

        $hasil = '';

        // Trailing bits that do not fill a byte are padding, not data.
        foreach (str_split($bit, 8) as $potongan) {
            if (strlen($potongan) === 8) {
                $hasil .= chr(bindec($potongan));
            }
        }

        return $hasil;
    }
}
