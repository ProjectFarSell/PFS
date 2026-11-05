<?php

use App\Http\Middleware\ConfiguredProxies;
use App\Http\Middleware\EnforceAccountSecurity;
use App\Http\Middleware\RequestTelemetry;
use App\Http\Middleware\RequireRecentAuthentication;
use App\Http\Middleware\ResponseSecurity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Invalidate authenticated sessions whose password has since changed.
        $middleware->web(append: [AuthenticateSession::class, EnforceAccountSecurity::class, RequireRecentAuthentication::class]);
        $middleware->replace(TrustProxies::class, ConfiguredProxies::class);
        $middleware->prepend(RequestTelemetry::class);
        $middleware->append(ResponseSecurity::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['code', 'delivery_pin', 'mfa_secret', 'recovery_code']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
