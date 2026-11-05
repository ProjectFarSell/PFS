<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RequestTelemetry
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = (string) Str::uuid();
        $request->attributes->set('request_id', $id);
        $started = hrtime(true);
        $status = 500;
        try {
            $response = $next($request);
            $status = $response->getStatusCode();
            $response->headers->set('X-Request-ID', $id);

            return $response;
        } finally {
            $elapsed = round((hrtime(true) - $started) / 1_000_000, 2);
            $route = $request->route()?->getName();
            if ($status >= 500 || $elapsed >= config('release.slow_request_ms') ||
                ($request->isMethod('POST') && (str_starts_with((string) $route, 'checkout.') || str_starts_with((string) $route, 'fulfillments.') || str_starts_with((string) $route, 'rider.')))) {
                try {
                    // No query strings, request bodies, IDs, addresses, passwords, or PINs.
                    Log::channel('operations')->log($status >= 500 ? 'error' : ($elapsed >= config('release.slow_request_ms') ? 'warning' : 'info'), 'marketplace_request', [
                        'request_id' => $id, 'release' => config('release.version'), 'route' => $route,
                        'method' => $request->method(), 'status' => $status, 'duration_ms' => $elapsed,
                    ]);
                } catch (Throwable) {
                    // Diagnostics must never turn a completed checkout into a failure.
                }
            }
        }
    }
}
