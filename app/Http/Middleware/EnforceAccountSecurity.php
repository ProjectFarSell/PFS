<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Services\Security\AccountSecurity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnforceAccountSecurity
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }
        // Read current account state even if a guard cached the user earlier in this request.
        $user->refresh();
        if ($user->suspended_at || (int) $request->session()->get('security.version', 0) !== $user->security_version) {
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session is no longer available. Sign in again or contact support.'], 401);
            }

            return to_route('login')->withErrors(['email' => 'Your session is no longer available. Sign in again or contact support.'], 'login');
        }
        if ($request->routeIs('security.*', 'logout', 'auth.google.callback')) {
            return $next($request);
        }
        if ($user->role === UserRole::Admin && config('security.admin_mfa_required')) {
            $security = app(AccountSecurity::class);
            if (! $user->mfa_confirmed_at || ! hash_equals($security->mfaFingerprint($user), (string) $request->session()->get('security.mfa', ''))) {
                if ($request->expectsJson() || ! $request->isMethod('GET')) {
                    return response()->json(['message' => 'Complete admin two-factor authentication.', 'url' => route('security.index')], 423);
                }
                $request->session()->put('security.intended', $request->getRequestUri());

                return to_route('security.index');
            }
        }

        return $next($request);
    }
}
