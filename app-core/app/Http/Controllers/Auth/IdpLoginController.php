<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\DTOs\Auth\IdentitasEksternal;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Jobs\RecordActivityLogJob;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Auth\Idp\Contracts\IdentityProviderInterface;
use App\Services\Auth\Idp\IdpException;
use App\Services\Auth\Idp\PenautanIdpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Sign-in through the campus's own identity provider.
 *
 * Two legs. The first remembers a state and a nonce in the session and sends
 * the browser away; the second only trusts what comes back if the state
 * matches, the nonce is inside the token, and the token names this issuer and
 * this client. Everything that fails leaves a row in the activity log naming
 * the reason — and never the code or the token.
 *
 * The password form is untouched. The IdP is an additional door, not a
 * replacement: the day the IdP is down is the day BAAK still has to work.
 */
class IdpLoginController extends Controller
{
    public function __construct(
        private readonly IdentityProviderInterface $idp,
        private readonly PenautanIdpService $penautan,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(config('idp.enabled'), 404);

        $state = Str::random(40);
        $nonce = Str::random(40);

        $request->session()->put('idp', [
            'state' => $state,
            'nonce' => $nonce,
            'sejak' => now()->timestamp,
        ]);

        return redirect()->away($this->idp->authorizationUrl($state, $nonce, route('idp.callback')));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(config('idp.enabled'), 404);

        // Pulled, not read: a callback is answered once. Replaying the same
        // URL must find nothing to match against.
        $menunggu = $request->session()->pull('idp');

        $state = (string) $request->query('state', '');

        if (!is_array($menunggu) || !isset($menunggu['state'], $menunggu['nonce'], $menunggu['sejak'])
            || $state === '' || !hash_equals((string) $menunggu['state'], $state)) {
            return $this->gagal('state_salah');
        }

        if (now()->timestamp - (int) $menunggu['sejak'] > (int) config('idp.state_ttl')) {
            return $this->gagal('state_kedaluwarsa');
        }

        if ($request->filled('error')) {
            // Only the error code: error_description is free text from the
            // provider and has been seen to echo request parameters.
            return $this->gagal('ditolak_idp', null, Str::limit((string) $request->query('error'), 64, ''));
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return $this->gagal('tanpa_kode');
        }

        $identitas = null;

        try {
            $identitas = $this->idp->exchange($code, (string) $menunggu['nonce'], route('idp.callback'));
            $akun = $this->penautan->temukan($identitas);
        } catch (IdpException $e) {
            return $this->gagal($e->alasan, $identitas, $e->getMessage());
        }

        return $this->masukkan($request, $akun, $identitas);
    }

    private function masukkan(Request $request, Staff|Dosen $akun, IdentitasEksternal $identitas): RedirectResponse
    {
        $guard = PenautanIdpService::guardUntuk($akun);

        // One person, one portal, per session — same rule as the password form.
        foreach (UserRole::cases() as $role) {
            if ($role->guard() !== $guard && Auth::guard($role->guard())->check()) {
                Auth::guard($role->guard())->logout();
            }
        }

        /*
         * The IdP proved who this is. Whether that also counts as the second
         * factor is a campus decision (idp.satisfies_2fa, ADR-019), and the
         * default says no: the account is handed to the same challenge the
         * password form uses, with the same session shape, so the two paths
         * cannot drift apart.
         */
        if ($guard === 'staff' && $akun->duaFaktorAktif() && !config('idp.satisfies_2fa')) {
            $request->session()->regenerate();

            $request->session()->put('dua_faktor', [
                'staff_id' => $akun->id,
                'ingat' => false,
                'sejak' => now()->timestamp,
            ]);

            $this->catat('idp_masuk', $identitas, $akun, 'Identitas diterima; menunggu faktor kedua.');

            return redirect()->route('dua-faktor.tantangan');
        }

        Auth::guard($guard)->login($akun);

        $request->session()->regenerate();

        $akun->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->catat('idp_masuk', $identitas, $akun, 'Masuk lewat IdP eksternal.');

        return redirect()->intended(route(UserRole::from($guard)->homeRoute()));
    }

    private function gagal(string $alasan, ?IdentitasEksternal $identitas = null, ?string $keterangan = null): RedirectResponse
    {
        $this->catat('idp_gagal', $identitas, null, trim($alasan.'. '.(string) $keterangan));

        $kunci = 'auth.idp.'.$alasan;

        return redirect()->route('login')->withErrors([
            'identitas' => trans()->has($kunci)
                ? __($kunci, ['idp' => config('idp.name'), 'email' => $identitas?->email ?? '—'])
                : __('auth.idp.gagal', ['idp' => config('idp.name')]),
        ]);
    }

    /**
     * One activity-log row per attempt, successful or not.
     *
     * When no local account is involved yet the row is filed under a synthetic
     * subject, "IdP", so a refused unknown email is still visible on the Log
     * Aktivitas screen next to the sign-ins that succeeded.
     */
    private function catat(string $event, ?IdentitasEksternal $identitas, Staff|Dosen|null $akun, string $keterangan): void
    {
        RecordActivityLogJob::dispatch([
            'subject_type' => $akun?->getMorphClass() ?? 'IdP',
            'subject_id' => $akun?->getKey() ?? 0,
            'subject_label' => $akun?->nama ?? $identitas?->email,
            'event' => $event,
            'description' => Str::limit($keterangan, 255, ''),
            'changes' => array_filter([
                'issuer' => $identitas?->issuer,
                'subject' => $identitas?->subject,
                'email' => $identitas?->email,
            ]),
            'causer_type' => $akun?->getMorphClass(),
            'causer_id' => $akun?->getKey(),
            'causer_name' => $akun?->nama,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);
    }
}
