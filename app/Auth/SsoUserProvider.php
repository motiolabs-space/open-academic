<?php

declare(strict_types=1);

namespace App\Auth;

use App\Enums\UserRole;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use RuntimeException;

/**
 * Resolves an OAuth subject back to a person, across all three identity tables.
 *
 * When Open Campus presents an access token, Passport hands us the `sub` from
 * that token and asks who it is. The subject is a UUID, and the answer could be
 * a student, a lecturer, or a staff member — so this looks in all three.
 *
 * Order matters only for cost, not correctness: UUIDs do not collide, so at
 * most one table can answer. Students are checked first because they are by far
 * the largest population and therefore the most likely match.
 */
class SsoUserProvider implements UserProvider
{
    /** @var array<int, class-string<Authenticatable>> */
    private const MODELS = [
        Mahasiswa::class,
        Dosen::class,
        Staff::class,
    ];

    public function retrieveById($identifier): ?Authenticatable
    {
        // A malformed subject is not an error worth an exception; it simply
        // identifies nobody.
        if (!is_string($identifier) || $identifier === '') {
            return null;
        }

        foreach (self::MODELS as $model) {
            $user = $model::query()
                ->where('uuid', $identifier)
                ->where('is_active', true)
                ->first();

            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Which population a subject belongs to.
     *
     * Consumers need this: "who is this" and "what may they do here" are
     * different questions, and Open Campus renders a different experience for a
     * student than for a lecturer.
     */
    public function roleFor(Authenticatable $user): ?UserRole
    {
        return match (true) {
            $user instanceof Mahasiswa => UserRole::Mahasiswa,
            $user instanceof Dosen => UserRole::Dosen,
            $user instanceof Staff => UserRole::Staff,
            default => null,
        };
    }

    /**
     * Remember-me tokens are a session concern and belong to the three portal
     * guards. An OAuth subject is never resolved this way.
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // Intentionally empty: see retrieveByToken().
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        throw new RuntimeException(self::PESAN_KREDENSIAL);
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        throw new RuntimeException(self::PESAN_KREDENSIAL);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // Passwords are rehashed by the portal guard that verified them.
    }

    private const PESAN_KREDENSIAL =
        'SsoUserProvider tidak memverifikasi kredensial. Password grant tidak '
        .'diaktifkan; gunakan authorization-code lewat halaman masuk.';
}
