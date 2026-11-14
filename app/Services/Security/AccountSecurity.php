<?php

namespace App\Services\Security;

use App\Models\User;
use App\Support\DemoAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountSecurity
{
    public function start(Request $request, User $user): void
    {
        $request->session()->forget(['security', 'password_hash_web']);
        $request->session()->regenerate();
        $request->session()->put('security.version', $user->security_version);
        if (DemoAccount::exempt($user)) {
            // The just-completed primary sign-in counts as password confirmation, not MFA.
            $this->confirm($request);
        }
    }

    public function confirm(Request $request): void
    {
        $request->session()->regenerate();
        $request->session()->put('security.confirmed_user', $request->user()->id);
        $request->session()->put('security.confirmed_at', now()->timestamp);
    }

    public function recentlyConfirmed(Request $request): bool
    {
        $at = (int) $request->session()->get('security.confirmed_at', 0);

        return (int) $request->session()->get('security.confirmed_user') === $request->user()?->id
            && $at <= now()->timestamp && $at > now()->timestamp - config('security.confirmation_seconds', 300);
    }

    public function mfaFingerprint(User $user): string
    {
        return hash('sha256', $user->id.'|'.$user->role->value.'|'.$user->mfa_secret.'|'.$user->security_version);
    }

    public function requiresAuthenticator(User $user): bool
    {
        return ! DemoAccount::exempt($user) && $user->usesAuthenticator() && config('security.'.$user->role->value.'_mfa_required');
    }

    public function requiresOtp(User $user): bool
    {
        return ! DemoAccount::exempt($user) && ! $user->usesAuthenticator() && config('security.login_otp_required');
    }

    public function otpFingerprint(User $user): string
    {
        return hash_hmac('sha256', implode('|', [$user->id, $user->role->value, $user->email, $user->phone, $user->security_version]), config('app.key'));
    }

    public function otpVerified(Request $request): bool
    {
        return hash_equals($this->otpFingerprint($request->user()), (string) $request->session()->get('security.otp', ''));
    }

    public function returnAfterChallenge(Request $request)
    {
        $path = $request->session()->pull('security.intended');
        if (is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')) {
            return redirect($path);
        }

        return to_route(match ($request->user()->role->value) {
            'admin' => 'admin.dashboard', 'seller' => 'seller.dashboard', 'rider' => 'rider.dashboard', default => 'home',
        });
    }

    public function afterPrimarySignIn(Request $request, RedirectResponse $destination)
    {
        $user = $request->user();
        if (DemoAccount::exempt($user) && in_array(parse_url($destination->getTargetUrl(), PHP_URL_PATH), [
            route('security.index', [], false), route('security.otp.show', [], false),
        ], true)) {
            // A remembered verification URL must not reintroduce a demo MFA step.
            return $this->returnAfterChallenge($request);
        }
        $route = $this->requiresAuthenticator($user) ? 'security.index' : ($this->requiresOtp($user) ? 'security.otp.show' : null);
        if (! $route) {
            return $destination;
        }
        $url = parse_url($destination->getTargetUrl());
        $path = ($url['path'] ?? '/').(isset($url['query']) ? '?'.$url['query'] : '');
        if (str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')) {
            $request->session()->put('security.intended', $path);
        }

        return to_route($route);
    }

    public function revokeOtherSessions(Request $request): void
    {
        DB::transaction(function () use ($request) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $verified = $user->mfa_confirmed_at && hash_equals($this->mfaFingerprint($user), (string) $request->session()->get('security.mfa', ''));
            $otpVerified = $this->otpVerified($request);
            $user->forceFill(['security_version' => $user->security_version + 1, 'remember_token' => Str::random(60)])->save();
            $this->deleteSessions($user, $request->session()->getId());
            $request->setUserResolver(fn () => $user);
            auth()->setUser($user);
            $request->session()->put('security.version', $user->security_version);
            if ($verified) {
                $request->session()->put('security.mfa', $this->mfaFingerprint($user));
            } else {
                $request->session()->forget('security.mfa');
            }
            if ($otpVerified) {
                $request->session()->put('security.otp', $this->otpFingerprint($user));
            } else {
                $request->session()->forget('security.otp');
            }
            app(AuditTrail::class)->record('sessions.revoked_others', $user);
        }, 3);
    }

    public function deleteSessions(User $user, ?string $except = null): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)->when($except, fn ($q) => $q->where('id', '!=', $except))->delete();
        }
    }
}
