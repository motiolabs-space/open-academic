<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

/**
 * Takes an SSO application's access away — all of it.
 *
 * Revoking only the access tokens looks complete and is not: Passport's
 * refresh grant checks the refresh token's own row and never the access token
 * it was issued with, so the application could trade its 30-day refresh token
 * for a fresh, unrevoked access token the moment after a student pressed
 * "Cabut". Both rows go together.
 */
class PencabutanTokenSso
{
    /**
     * @param string|null $userId the OAuth subject, or null for every user of the client
     * @return int the number of access tokens revoked
     */
    public function cabut(string $clientId, ?string $userId = null): int
    {
        return DB::transaction(function () use ($clientId, $userId): int {
            $ids = Passport::tokenModel()::query()
                ->where('client_id', $clientId)
                ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
                ->where('revoked', false)
                ->pluck('id');

            if ($ids->isEmpty()) {
                return 0;
            }

            Passport::refreshTokenModel()::query()
                ->whereIn('access_token_id', $ids)
                ->update(['revoked' => true]);

            return Passport::tokenModel()::query()
                ->whereIn('id', $ids)
                ->update(['revoked' => true]);
        });
    }
}
