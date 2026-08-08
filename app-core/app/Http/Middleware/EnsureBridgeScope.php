<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Bridge\BridgeConsumer;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces what a consumer application is allowed to read.
 *
 * A Bridge token is not a key to the whole registry. Open Campus needs student
 * and class data to compute indicators; it has no business reading invoices,
 * and a token issued for one purpose must not quietly widen into another. The
 * scope list on the consumer is the authority — the token's abilities merely
 * mirror it, so revoking a scope takes effect without reissuing anything.
 */
class EnsureBridgeScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $consumer = $request->user();

        if (!$consumer instanceof BridgeConsumer) {
            return $this->tolak('Token bukan milik aplikasi konsumen Campus Bridge.', 401);
        }

        // Re-read before deciding. The principal handed over by the token guard
        // can be a cached instance, and an authorisation decision made against
        // a stale scope list would keep honouring access an administrator has
        // already revoked. One extra query per call buys immediate revocation.
        $consumer->refresh();

        if (!$consumer->is_active) {
            return $this->tolak('Akses aplikasi ini sedang dinonaktifkan.', 403);
        }

        if (!$consumer->hasScope($scope)) {
            return $this->tolak(
                "Token tidak memiliki scope \"{$scope}\". Scope yang dimiliki: "
                .implode(', ', $consumer->scopes ?? []),
                403,
            );
        }

        $consumer->forceFill(['last_seen_at' => now()])->saveQuietly();

        return $next($request);
    }

    private function tolak(string $pesan, int $status): JsonResponse
    {
        return response()->json(['message' => $pesan], $status);
    }
}
