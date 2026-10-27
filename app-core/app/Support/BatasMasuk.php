<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Failed sign-in attempts, counted three ways.
 *
 * Per identity + address alone — the original limit — stops one machine
 * hammering one account and nothing else. Rotating addresses resets it for a
 * targeted account, and one address can try one common password against every
 * sequential NIM in a faculty without ever tripping it. So two wider counters
 * sit on top:
 *
 *   - per account, across all addresses: distributed guessing of one account.
 *     The price is that an attacker can hold one account's door shut for a
 *     quarter of an hour; that is the usual trade, and far cheaper than the
 *     account falling.
 *   - per address, across all accounts: password spraying. Set well above what
 *     a campus NAT produces in honest typos, because a whole faculty can share
 *     one address.
 *
 * Only failures count. A successful sign-in clears the first two but never the
 * address counter — otherwise one valid account would reset a spraying run.
 */
final class BatasMasuk
{
    public function __construct(
        private readonly string $ruang,
        private readonly string $identitas,
        private readonly string $alamat,
        private readonly int $maksPerIdentitasAlamat = 5,
    ) {}

    public function kunciIdentitasAlamat(): string
    {
        return $this->ruang.'|'.$this->identitasBaku().'|'.$this->alamat;
    }

    /** Seconds until another attempt is allowed, or null when it is allowed now. */
    public function sisaDetik(): ?int
    {
        foreach ($this->batas() as [$kunci, $maks]) {
            if (RateLimiter::tooManyAttempts($kunci, $maks)) {
                return RateLimiter::availableIn($kunci);
            }
        }

        return null;
    }

    public function catatGagal(): void
    {
        foreach ($this->batas() as [$kunci, , $detik]) {
            RateLimiter::hit($kunci, $detik);
        }
    }

    public function bersihkan(): void
    {
        RateLimiter::clear($this->kunciIdentitasAlamat());
        RateLimiter::clear($this->ruang.'-akun|'.$this->identitasBaku());
    }

    /** @return list<array{0: string, 1: int, 2: int}> key, max attempts, decay seconds */
    private function batas(): array
    {
        return [
            [$this->kunciIdentitasAlamat(), $this->maksPerIdentitasAlamat, 60],
            [$this->ruang.'-akun|'.$this->identitasBaku(), ...$this->dariKonfigurasi('per_akun')],
            [$this->ruang.'-alamat|'.$this->alamat, ...$this->dariKonfigurasi('per_alamat')],
        ];
    }

    /** @return array{0: int, 1: int} */
    private function dariKonfigurasi(string $jenis): array
    {
        return [
            (int) config("auth.batas_masuk.{$jenis}.maks"),
            (int) config("auth.batas_masuk.{$jenis}.detik"),
        ];
    }

    private function identitasBaku(): string
    {
        return Str::transliterate(Str::lower($this->identitas));
    }
}
