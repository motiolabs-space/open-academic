<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\SsoGuard;
use App\Auth\SsoUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

/**
 * Wires Open Academic's three-guard identity model into Passport.
 *
 * Everything OAuth-specific that is not a route or a controller lives here, so
 * a campus that never turns SSO on carries one provider it does not use rather
 * than assumptions scattered through the application.
 */
class SsoServiceProvider extends ServiceProvider
{
    /**
     * Suppressing Passport's routes has to happen here, not in boot().
     *
     * Every provider's register() runs before any provider's boot(), and
     * Passport registers its routes in boot(). Calling ignoreRoutes() from our
     * own boot() would be too late — the routes would already exist, and a
     * campus with SSO switched off would still be serving /oauth/token.
     */
    public function register(): void
    {
        if (!config('sso.enabled')) {
            Passport::ignoreRoutes();
        }
    }

    public function boot(): void
    {
        $this->daftarkanGuard();

        if (!config('sso.enabled')) {
            return;
        }

        $this->konfigurasiPassport();
    }

    /**
     * The guard and provider are registered even when SSO is disabled.
     *
     * They are cheap, and a half-registered auth stack fails in confusing ways:
     * a campus flipping SSO_ENABLED on should get a working flow, not a
     * "driver [sso-session] not supported" error on the next request.
     */
    private function daftarkanGuard(): void
    {
        Auth::provider('akademik', fn (): SsoUserProvider => new SsoUserProvider);

        Auth::extend('sso-session', fn (): SsoGuard => new SsoGuard);
    }

    private function konfigurasiPassport(): void
    {
        // The password grant stays off (Passport's default). It would require
        // this application to accept a raw password on behalf of a consumer,
        // which defeats the point of having an authorisation screen at all.

        Passport::tokensExpireIn(now()->addMinutes((int) config('sso.lifetimes.access_token')));
        Passport::refreshTokensExpireIn(now()->addMinutes((int) config('sso.lifetimes.refresh_token')));
        Passport::personalAccessTokensExpireIn(now()->addMinutes((int) config('sso.lifetimes.personal_token')));

        Passport::tokensCan(config('sso.scopes'));
        Passport::setDefaultScope(...config('sso.default_scopes'));

        // The consent screen is the only place a student can refuse, so it is
        // rendered in the campus's own design rather than Passport's default.
        Passport::authorizationView('sso.persetujuan');
    }
}
