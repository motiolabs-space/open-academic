<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Auth\Idp\Contracts\IdentityProviderInterface;
use App\Services\Auth\Idp\FakeIdentityProvider;
use App\Services\Auth\Idp\OidcIdentityProvider;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Which identity provider the sign-in page delegates to is configuration,
 * never a branch in the controller.
 *
 * The fake is a singleton so the claims a test registers on it are the claims
 * the controller's instance resolves. An unknown driver fails loudly: a campus
 * that typed IDP_DRIVER=google should find out at the first click, not sign
 * everybody in through a fake.
 */
class IdpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FakeIdentityProvider::class);

        $this->app->singleton(IdentityProviderInterface::class, function (): IdentityProviderInterface {
            $driver = (string) config('idp.driver');

            return match ($driver) {
                'fake' => $this->app->make(FakeIdentityProvider::class),
                'oidc' => $this->app->make(OidcIdentityProvider::class),
                default => throw new InvalidArgumentException(
                    "Driver IdP \"{$driver}\" tidak dikenal. Pilih fake atau oidc.",
                ),
            };
        });
    }
}
