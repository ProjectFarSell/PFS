<?php

namespace App\Services\Security;

use App\Models\User;
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
        return hash('sha256', $user->id.'|'.$user->mfa_secret.'|'.$user->security_version);
    }

    public function revokeOtherSessions(Request $request): void
    {
        DB::transaction(function () use ($request) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $verified = $user->mfa_confirmed_at && hash_equals($this->mfaFingerprint($user), (string) $request->session()->get('security.mfa', ''));
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
