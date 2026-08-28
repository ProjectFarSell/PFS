<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        $required = UserRole::from($role);

        if ($user->role === UserRole::Admin || $user->role === $required) {
            return $next($request);
        }

        abort(403);
    }
}
