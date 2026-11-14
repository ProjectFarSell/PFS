<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Mobile\ApiFailure;
use App\Services\Mobile\MobileAuthentication;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class MobileAccess
{
    public function handle(Request $request, Closure $next, ?string $role = null, string $approved = 'yes')
    {
        // Resolve the presented token itself, not a cached web/Sanctum user.
        // This also prevents cross-request guard state leaking under long-lived workers.
        $token = $request->bearerToken() ? PersonalAccessToken::findToken($request->bearerToken()) : null;
        $user = $token && $token->tokenable_type === (new User)->getMorphClass() ? User::find($token->tokenable_id) : null;
        if (! $request->bearerToken() || ! $token instanceof PersonalAccessToken || ! $user || ! $token->expires_at || $token->expires_at->lte(now()) || ! hash_equals((string) $token->identity_hash, app(MobileAuthentication::class)->fingerprint($user))) {
            throw new ApiFailure('unauthenticated', 'Sign in again to continue.', 401);
        }
        if ($user->suspended_at || ! in_array($user->role->value, ['buyer', 'rider'], true) || $token->mobile_app !== $user->role->value || ! in_array($user->role->value.':*', $token->abilities ?? [], true) || ($role && $role !== $user->role->value)) {
            throw new ApiFailure('forbidden', 'This account cannot perform this action.', 403);
        }
        if ($role === 'rider' && $approved === 'yes' && ! $user->riderProfile?->isApproved()) {
            throw new ApiFailure('forbidden', 'An approved rider profile is required.', 403);
        }
        $user->withAccessToken($token);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
