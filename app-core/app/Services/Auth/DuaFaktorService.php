<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Sdm\Staff;
use App\Services\Branding\BrandingService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Enrolment, verification and recovery for staff two-factor.
 *
 * Totp does the arithmetic; this decides what counts as a valid sign-in, which
 * is a different question and the one with the sharp edges.
 *
 * Three of those edges are handled here rather than left to the caller:
 *
 *  1. **Replay.** A code is valid for its whole 30-second step, so the same six
 *     digits work twice — once for the owner and once for whoever was reading
 *     over their shoulder. The step of the last accepted code is remembered and
 *     anything at or below it is refused.
 *
 *  2. **Enrolment is not finished until a code comes back.** A generated secret
 *     alone confirms nothing. Scanning a QR and then losing the phone is the
 *     ordinary way people lock themselves out; until one code is typed back
 *     correctly, the account still signs in on its password alone.
 *
 *  3. **Recovery codes are hashed and single-use.** They are passwords, not
 *     tokens, and they are stored the way passwords are. Shown exactly once at
 *     enrolment — the campus that skips writing them down has an operator
 *     locked out on the week that matters, which is the failure this whole
 *     feature is otherwise supposed to prevent.
 */
class DuaFaktorService
{
    public const JUMLAH_PEMULIHAN = 8;

    public function __construct(
        private readonly Totp $totp,
        private readonly BrandingService $brand,
    ) {}

    /* ---------------------------------------------------------------------
     | Pendaftaran
     |-------------------------------------------------------------------- */

    /**
     * Starts enrolment: a fresh secret, not yet confirmed.
     *
     * Overwrites any previous unconfirmed attempt, and deliberately refuses to
     * touch an account that already has 2FA working — re-enrolling would
     * silently invalidate the authenticator entry the person is still using.
     *
     * @return array{rahasia: string, uri: string}
     */
    public function mulai(Staff $staff): array
    {
        if ($staff->duaFaktorAktif()) {
            return [
                'rahasia' => (string) $staff->two_factor_secret,
                'uri' => $this->uri($staff, (string) $staff->two_factor_secret),
            ];
        }

        $rahasia = $this->totp->rahasiaBaru();

        $staff->forceFill([
            'two_factor_secret' => $rahasia,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery' => null,
            'two_factor_langkah_terakhir' => null,
        ])->save();

        return ['rahasia' => $rahasia, 'uri' => $this->uri($staff, $rahasia)];
    }

    /**
     * Finishes enrolment once a code proves the pairing works.
     *
     * @return array<int, string>|null the recovery codes, in the clear, once —
     *                                 or null when the code was wrong
     */
    public function konfirmasi(Staff $staff, string $kode): ?array
    {
        if (blank($staff->two_factor_secret) || !$this->totp->cocok((string) $staff->two_factor_secret, $kode)) {
            return null;
        }

        $polos = $this->kodePemulihanBaru();

        $staff->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery' => array_map(fn (string $k): string => Hash::make($k), $polos),
            'two_factor_langkah_terakhir' => intdiv(now()->getTimestamp(), Totp::PERIODE),
        ])->save();

        return $polos;
    }

    /**
     * Turns 2FA off for one account.
     *
     * Never silent: the caller records who did it. An administrator disabling
     * someone else's second factor is exactly the move an attacker with an
     * administrator session would make.
     */
    public function matikan(Staff $staff): void
    {
        $staff->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_langkah_terakhir' => null,
        ])->save();
    }

    /* ---------------------------------------------------------------------
     | Verifikasi
     |-------------------------------------------------------------------- */

    /**
     * Whether this sign-in attempt clears the second factor.
     *
     * Accepts either a six-digit code or one recovery code, and consumes
     * whichever it used.
     */
    public function lolos(Staff $staff, string $masukan): bool
    {
        if (!$staff->duaFaktorAktif()) {
            return true;
        }

        return $this->lolosTotp($staff, $masukan) || $this->lolosPemulihan($staff, $masukan);
    }

    private function lolosTotp(Staff $staff, string $kode): bool
    {
        if (!$this->totp->cocok((string) $staff->two_factor_secret, $kode)) {
            return false;
        }

        $langkah = intdiv(now()->getTimestamp(), Totp::PERIODE);

        /*
         * Refuse a step already used.
         *
         * Without this the same six digits are good for a full window: long
         * enough for a code read over a shoulder, or captured by a phishing
         * page, to be replayed while it is still warm.
         *
         * Compared against the *current* step rather than the matched one, so a
         * code accepted at the edge of the drift window cannot be replayed from
         * the neighbouring step either.
         */
        if ($staff->two_factor_langkah_terakhir !== null
            && $langkah <= (int) $staff->two_factor_langkah_terakhir) {
            return false;
        }

        $staff->forceFill(['two_factor_langkah_terakhir' => $langkah])->save();

        return true;
    }

    private function lolosPemulihan(Staff $staff, string $masukan): bool
    {
        $tersimpan = (array) ($staff->two_factor_recovery ?? []);
        $masukan = strtolower(trim($masukan));

        foreach ($tersimpan as $indeks => $hash) {
            if (!Hash::check($masukan, (string) $hash)) {
                continue;
            }

            // Single use. A recovery code that survives its own use is a
            // password with a friendlier name.
            unset($tersimpan[$indeks]);

            $staff->forceFill(['two_factor_recovery' => array_values($tersimpan)])->save();

            return true;
        }

        return false;
    }

    /** How many recovery codes are left unused. */
    public function sisaPemulihan(Staff $staff): int
    {
        return count((array) ($staff->two_factor_recovery ?? []));
    }

    /**
     * Issues a fresh set, invalidating the old one.
     *
     * @return array<int, string>
     */
    public function perbaruiPemulihan(Staff $staff): array
    {
        $polos = $this->kodePemulihanBaru();

        $staff->forceFill([
            'two_factor_recovery' => array_map(fn (string $k): string => Hash::make($k), $polos),
        ])->save();

        return $polos;
    }

    /* ---------------------------------------------------------------------
     | Internals
     |-------------------------------------------------------------------- */

    /** @return array<int, string> */
    private function kodePemulihanBaru(): array
    {
        return collect(range(1, self::JUMLAH_PEMULIHAN))
            // Lower case and hyphenated: these get written on paper and typed
            // back by someone already having a bad day.
            ->map(fn (): string => strtolower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    private function uri(Staff $staff, string $rahasia): string
    {
        return $this->totp->uri(
            $rahasia,
            (string) ($staff->email ?: $staff->nip ?: $staff->uuid),
            $this->brand->institutionName(),
        );
    }
}
