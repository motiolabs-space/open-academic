<?php

declare(strict_types=1);

namespace App\Auth;

use App\Enums\UserRole;
use App\Support\Portal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * The single guard Passport sees, standing in front of our three.
 *
 * Passport binds its authorisation flow to one StatefulGuard resolved from
 * `config('passport.guard')` (PassportServiceProvider). Open Academic has three
 * session guards, and which one is live depends on who happens to be signed in.
 *
 * Registering a fourth *session* guard would not work: it would keep its own
 * session key, so a student already signed into the student portal would be a
 * guest here and get asked to log in a second time to authorise an app they
 * are already authenticated for.
 *
 * So this guard owns no session at all. It reads whichever of the three is
 * authenticated and reports that person, which is exactly the question the
 * consent screen needs answered.
 */
class SsoGuard implements StatefulGuard
{
    /**
     * Passport reads this when building the AuthenticationException, which is
     * what redirects an unauthenticated visitor to the sign-in page.
     */
    public string $name = 'sso';

    private ?Authenticatable $userOverride = null;

    public function user(): ?Authenticatable
    {
        return $this->userOverride ?? Portal::user();
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    /** The OAuth subject: a UUID, unique across all three identity tables. */
    public function id(): ?string
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function hasUser(): bool
    {
        return $this->user() !== null;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->userOverride = $user;

        return $this;
    }

    /**
     * Ends every portal session, not just one.
     *
     * Passport calls this for `prompt=login`. Logging out only one guard would
     * leave the browser holding another identity, and the "fresh login" the
     * consumer asked for would silently resolve to the same person.
     */
    public function logout(): void
    {
        $this->userOverride = null;

        foreach (UserRole::cases() as $role) {
            if (Auth::guard($role->guard())->check()) {
                Auth::guard($role->guard())->logout();
            }
        }
    }

    /**
     * Credential handling belongs to LoginController, which knows how to map an
     * identifier to one of three guards. Passport never reaches these on the
     * authorization-code path — it redirects to the sign-in page instead.
     */
    public function validate(array $credentials = []): bool
    {
        throw new RuntimeException(self::PESAN_KREDENSIAL);
    }

    public function attempt(array $credentials = [], $remember = false): bool
    {
        throw new RuntimeException(self::PESAN_KREDENSIAL);
    }

    public function once(array $credentials = []): bool
    {
        throw new RuntimeException(self::PESAN_KREDENSIAL);
    }

    public function login(Authenticatable $user, $remember = false): void
    {
        throw new RuntimeException(self::PESAN_KREDENSIAL);
    }

    public function loginUsingId($id, $remember = false): Authenticatable|false
    {
        throw new RuntimeException(self::PESAN_KREDENSIAL);
    }

    public function onceUsingId($id): Authenticatable|false
    {
        throw new RuntimeException(self::PESAN_KREDENSIAL);
    }

    public function viaRemember(): bool
    {
        return false;
    }

    private const PESAN_KREDENSIAL =
        'Guard "sso" tidak menangani kredensial. Autentikasi terjadi pada guard '
        .'mahasiswa/dosen/staff lewat LoginController; guard ini hanya membaca '
        .'siapa yang sudah masuk.';
}
