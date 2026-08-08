<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Bridge\BridgeApiRequest;
use App\Models\Bridge\BridgeConsumer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records Bridge API traffic for the console's usage chart.
 *
 * Telemetry, not an audit trail: it holds no payloads and is safe to prune.
 * Written after the response so a slow query is measured, not the logging.
 */
class RecordBridgeApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $mulai = microtime(true);

        $response = $next($request);

        $consumer = $request->user();

        BridgeApiRequest::create([
            'bridge_consumer_id' => $consumer instanceof BridgeConsumer ? $consumer->id : null,
            'method' => $request->method(),
            'path' => mb_substr($request->path(), 0, 255),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $mulai) * 1000),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return $response;
    }
}
