<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectPrivateRolesFromStorefront
{
    public function handle(Request $request, Closure $next): Response
    {
        $destination = match ($request->user()?->role) {
            UserRole::Admin => 'admin.dashboard',
            UserRole::Rider => 'rider.dashboard',
            default => null,
        };

        if ($destination !== null) {
            return to_route($destination);
        }

        return $next($request);
    }
}
