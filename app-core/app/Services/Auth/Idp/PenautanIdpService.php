<?php

declare(strict_types=1);

namespace App\Services\Auth\Idp;

use App\DTOs\Auth\IdentitasEksternal;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Turns an identity the IdP vouches for into exactly one local account.
 *
 * This is the policy decision docs/SSO.md said had to be taken before the
 * feature could exist, and it is taken narrowly:
 *
 *  - Only the guards named in `idp.guards`, staff alone by default. Students
 *    are never candidates: a lecturer who also studies here would otherwise be
 *    one email collision away from the student portal.
 *  - A subject that was linked before wins outright. Email is used once, to
 *    link; after that the IdP's stable `sub` is what identifies the person,
 *    because campuses rename mailboxes and reissue addresses.
 *  - An email that matches in more than one table is refused rather than
 *    resolved by precedence. Institutional policy says it cannot happen; the
 *    code does not bet a sign-in on policy.
 *  - Nobody is created unless `idp.provision` is on, and then only a staff row
 *    with no role. The directory holds far more people than BAAK does.
 */
final class PenautanIdpService
{
    /** @var array<string, class-string<Staff|Dosen>> */
    private const MODEL = [
        'staff' => Staff::class,
        'dosen' => Dosen::class,
    ];

    /**
     * @throws IdpException when the identity maps to no account, to a refused
     *                      one, or to more than one
     */
    public function temukan(IdentitasEksternal $identitas): Staff|Dosen
    {
        $guards = $this->guards();

        $tertaut = $this->cari($guards, fn ($query) => $query->where('idp_subject', $identitas->subject));

        if (count($tertaut) > 1) {
            throw new IdpException('ambigu', 'Subject is linked to accounts in more than one table.');
        }

        if ($tertaut !== []) {
            return $this->pastikanAktif($tertaut[0]);
        }

        if ($identitas->email === null) {
            throw new IdpException('tanpa_surel', 'Identity has no email to link by; request the email scope.');
        }

        if (config('idp.require_verified_email') && !$identitas->emailVerified) {
            throw new IdpException('surel_belum_terverifikasi', 'Identity email is not marked verified by the provider.');
        }

        $cocok = $this->cari($guards, fn ($query) => $query->whereRaw('LOWER(email) = ?', [$identitas->email]));

        if (count($cocok) > 1) {
            throw new IdpException('ambigu', 'Email matches accounts in more than one table.');
        }

        if ($cocok === []) {
            return $this->buatBila($identitas, $guards);
        }

        $akun = $cocok[0];

        if ($akun->idp_subject !== null) {
            // Same email, different subject: a reissued mailbox, or an
            // impersonation attempt. Either way it is not this account.
            throw new IdpException('tertaut_identitas_lain', 'Account is already linked to a different subject.');
        }

        $this->pastikanAktif($akun);

        $akun->forceFill(['idp_subject' => $identitas->subject])->save();

        return $akun;
    }

    /**
     * @param array<int, string> $guards
     * @param callable(Builder<Staff|Dosen>): mixed $syarat
     * @return array<int, Staff|Dosen>
     */
    private function cari(array $guards, callable $syarat): array
    {
        $hasil = [];

        foreach ($guards as $guard) {
            $model = self::MODEL[$guard];

            // Capped at two: one match is the answer, two is already "ambiguous".
            foreach ($model::query()->tap($syarat)->limit(2)->get() as $akun) {
                $hasil[] = $akun;
            }
        }

        return $hasil;
    }

    /** @param  array<int, string>  $guards */
    private function buatBila(IdentitasEksternal $identitas, array $guards): Staff
    {
        if (!config('idp.provision') || !in_array('staff', $guards, true)) {
            throw new IdpException('tidak_dikenal', 'No account carries this email.');
        }

        $staff = new Staff;
        $staff->forceFill([
            'nama' => $identitas->name ?? Str::before($identitas->email ?? '', '@'),
            'email' => $identitas->email,
            // Unusable on purpose: this account signs in through the IdP. A
            // password reset by email is the way back if that ever changes.
            'password' => Str::random(64),
            'is_active' => true,
            'idp_subject' => $identitas->subject,
        ])->save();

        return $staff;
    }

    /** @throws IdpException */
    private function pastikanAktif(Staff|Dosen $akun): Staff|Dosen
    {
        if (!$akun->is_active) {
            throw new IdpException('nonaktif', 'Account is deactivated.');
        }

        return $akun;
    }

    /**
     * @return array<int, string>
     *
     * @throws IdpException on a guard this feature does not serve
     */
    private function guards(): array
    {
        $guards = array_values(array_unique(array_map('trim', (array) config('idp.guards'))));

        foreach ($guards as $guard) {
            if (!isset(self::MODEL[$guard])) {
                throw new IdpException('guard_tidak_didukung', sprintf(
                    'IDP_GUARDS names "%s"; only %s can sign in through the IdP.',
                    $guard,
                    implode(', ', array_keys(self::MODEL)),
                ));
            }
        }

        return $guards === [] ? ['staff'] : $guards;
    }

    /** The guard a resolved account signs in on. */
    public static function guardUntuk(Model $akun): string
    {
        return $akun instanceof Staff ? 'staff' : 'dosen';
    }
}
