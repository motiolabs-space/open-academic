<?php

declare(strict_types=1);

namespace App\Services\Pmb;

use App\DTOs\Pmb\AkunPendaftarData;
use App\Models\Pmb\PmbAkun;
use Illuminate\Auth\Events\Verified;

/**
 * Applicant accounts: opening one and proving the address behind it.
 *
 * The address is proven before anything else is allowed, because it is the
 * channel the selection result is sent on. An applicant who mistyped it would
 * otherwise complete the whole form and never hear back.
 */
class AkunPendaftarService
{
    public function daftar(AkunPendaftarData $data): PmbAkun
    {
        $akun = PmbAkun::create([
            'nama' => $data->nama,
            'email' => $data->email,
            'password' => $data->password,
            'is_active' => true,
        ]);

        $akun->sendEmailVerificationNotification();

        return $akun;
    }

    /**
     * Marks the address verified when the link's hash matches it.
     *
     * Compared against the address as it is now, so a link sent before the
     * address changed cannot verify the new one.
     */
    public function verifikasi(PmbAkun $akun, string $hash): bool
    {
        if (!hash_equals(sha1($akun->getEmailForVerification()), $hash)) {
            return false;
        }

        if (!$akun->hasVerifiedEmail() && $akun->markEmailAsVerified()) {
            event(new Verified($akun));
        }

        return true;
    }
}
