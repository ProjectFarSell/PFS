<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        if (in_array($user->role, $allowed, true)) {
            return $next($request);
        }

        abort(403);
    }
}
