<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Services\Security\AccountSecurity;
use Closure;
use Illuminate\Http\Request;

class RequireRecentAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe() || $request->routeIs('security.*', 'logout', 'auth.google.callback')) {
            return $next($request);
        }
        if (config('security.sensitive_confirmation_required') && $request->user()?->role === UserRole::Admin
            && ! app(AccountSecurity::class)->recentlyConfirmed($request)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Confirm your identity before this action.', 'url' => route('security.index')], 423);
            }

            return to_route('security.index')->with('status', 'Confirm your identity, then return and submit the action again. No changes were made.');
        }

        return $next($request);
    }
}
