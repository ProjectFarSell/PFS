<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResponseSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production')) {
            $canonical = config('app.url');
            $hosts = array_merge([(string) parse_url($canonical, PHP_URL_HOST)], config('security.allowed_hosts', []));
            abort_unless(in_array(strtolower($request->getHost()), array_map('strtolower', $hosts), true), 400, 'Unrecognized host.');
            if (! $request->isSecure() && ! $request->is('up')) {
                abort_unless(parse_url($canonical, PHP_URL_SCHEME) === 'https', 503, 'Secure site configuration required.');

                return redirect()->away(rtrim($canonical, '/').$request->getRequestUri(), 308);
            }
        }

        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', $request->routeIs('password.reset', 'password.store') ? 'no-referrer' : 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'self'");
        // Blade pages include sessions/CSRF tokens and may contain account-specific state.
        $response->headers->set('Cache-Control', 'private, no-store');
        if ($request->routeIs('mobile.links.*') && $response->isSuccessful()) {
            $response->headers->set('Cache-Control', 'public, max-age=3600');
        }
        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.max(0, (int) config('security.hsts_seconds')));
        }

        return $response;
    }
}
