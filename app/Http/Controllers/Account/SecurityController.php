<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\AccountSecurity;
use App\Services\Security\AdminTwoFactor;
use App\Services\Security\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SecurityController extends Controller
{
    public function index(Request $request, AccountSecurity $security, AdminTwoFactor $mfa)
    {
        $user = $request->user();
        if ($security->requiresOtp($user) && ! $security->otpVerified($request)) {
            return to_route('security.otp.show');
        }
        $pending = $request->session()->get('security.pending_mfa');
        $secret = $pending && $pending['expires'] > now()->timestamp ? Crypt::decryptString($pending['secret']) : null;
        $setupUpdated = $secret !== null && strlen($secret) !== 32 && ! $user->mfa_confirmed_at;
        if ($setupUpdated) {
            $request->session()->forget('security.pending_mfa');
            $secret = null;
        }
        $qrCode = $secret && ! $user->mfa_confirmed_at ? $mfa->qrDataUri($secret, $user->email) : null;
        $display = $request->session()->pull('security.recovery_display');
        $recoveryCodes = $display ? json_decode(Crypt::decryptString($display), true, 512, JSON_THROW_ON_ERROR) : [];
        $sessions = config('session.driver') === 'database'
            ? DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)
                ->where('last_activity', '>=', now()->subMinutes(config('session.lifetime'))->timestamp)
                ->orderByDesc('last_activity')->limit(30)->get(['id', 'user_agent', 'last_activity'])
            : collect();
        $mfaVerified = $user->mfa_confirmed_at && hash_equals($security->mfaFingerprint($user), (string) $request->session()->get('security.mfa', ''));
        $confirmed = $security->recentlyConfirmed($request);

        return view('account.security', compact('user', 'secret', 'qrCode', 'setupUpdated', 'recoveryCodes', 'sessions', 'mfaVerified', 'confirmed'));
    }

    public function confirm(Request $request, AccountSecurity $security, AdminTwoFactor $mfa)
    {
        $data = $request->validate(['password' => ['nullable', 'string', 'max:1024'], 'code' => ['nullable', 'string', 'max:80']]);
        $user = $request->user();
        if ($security->requiresOtp($user) && ! $security->otpVerified($request)) {
            throw ValidationException::withMessages(['identity' => 'Complete your email or phone sign-in code first.']);
        }
        $valid = $user->usesAuthenticator() && $user->mfa_confirmed_at
            ? $mfa->verify($user, $data['code'] ?? '')
            : Hash::check($data['password'] ?? '', $user->password);
        if (! $valid) {
            throw ValidationException::withMessages(['identity' => 'Your confirmation was not accepted. Use a current code, an unused recovery code, or the required password.']);
        }
        if ($user->usesAuthenticator() && $user->mfa_confirmed_at) {
            $request->session()->put('security.mfa', $security->mfaFingerprint($user));
        }
        $security->confirm($request);
        app(AuditTrail::class)->record('identity.confirmed', $user);
        $intended = $request->session()->pull('security.intended');
        if (is_string($intended) && str_starts_with($intended, '/') && ! str_starts_with($intended, '//') && ! str_contains($intended, '\\')) {
            return redirect($intended);
        }

        return to_route('security.index')->with('status', 'Identity confirmed for five minutes. Return to your previous page and submit the action again.');
    }

    public function beginMfa(Request $request, AccountSecurity $security, AdminTwoFactor $mfa)
    {
        abort_unless($request->user()->usesAuthenticator() && ! $request->user()->mfa_confirmed_at, 403);
        $this->requireConfirmation($request, $security);
        $pending = $request->session()->get('security.pending_mfa');
        if ($pending && $pending['expires'] > now()->timestamp && strlen(Crypt::decryptString($pending['secret'])) === 32) {
            return to_route('security.index')->with('status', 'Your existing setup key is still active. Keep using the same authenticator entry.');
        }
        $request->session()->put('security.pending_mfa', ['secret' => Crypt::encryptString($mfa->generate()), 'expires' => now()->addMinutes(10)->timestamp]);

        return to_route('security.index');
    }

    public function enableMfa(Request $request, AccountSecurity $security, AdminTwoFactor $mfa)
    {
        abort_unless($request->user()->usesAuthenticator(), 403);
        $this->requireConfirmation($request, $security);
        $data = $request->validate(['code' => ['required', 'digits:6'], 'setup_id' => ['required', 'string', 'size:32']]);
        $pending = $request->session()->get('security.pending_mfa');
        if (! $pending || $pending['expires'] <= now()->timestamp) {
            throw ValidationException::withMessages(['code' => 'Setup expired. Start again.']);
        }
        $secret = Crypt::decryptString($pending['secret']);
        if (strlen($secret) !== 32 || ! hash_equals(substr(hash('sha256', $secret), 0, 32), $data['setup_id'])) {
            throw ValidationException::withMessages(['code' => 'This setup page is out of date. Refresh it, scan the current QR code, and enter a new code.']);
        }
        $step = $mfa->step($secret, $data['code']);
        if ($step === null) {
            throw ValidationException::withMessages(['code' => 'The code did not match the current setup key. Check that your authenticator uses this key, Time-based (TOTP), SHA-1, six digits and 30 seconds, and that your phone time is automatic. Wait for a new code and try again.']);
        }
        [$plain, $hashes] = $mfa->recoveryCodes();
        DB::transaction(function () use ($request, $secret, $step, $hashes) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_if($user->mfa_confirmed_at !== null, 409, 'Two-factor authentication is already enabled.');
            $user->forceFill(['mfa_secret' => $secret, 'mfa_confirmed_at' => now(), 'mfa_last_step' => $step, 'mfa_recovery_codes' => $hashes])->save();
            app(AuditTrail::class)->record('mfa.enabled', $user);
        }, 3);
        $request->session()->forget('security.pending_mfa');
        $request->session()->put('security.recovery_display', Crypt::encryptString(json_encode($plain, JSON_THROW_ON_ERROR)));
        $request->user()->refresh();
        $request->session()->put('security.mfa', $security->mfaFingerprint($request->user()));
        $security->revokeOtherSessions($request);
        $security->confirm($request);

        return to_route('security.index')->with('status', 'Two-factor authentication enabled. Save your recovery codes now.');
    }

    public function regenerateCodes(Request $request, AccountSecurity $security, AdminTwoFactor $mfa)
    {
        abort_unless($request->user()->usesAuthenticator() && $request->user()->mfa_confirmed_at, 403);
        $this->requireConfirmation($request, $security);
        // A recently confirmed session is still required to have passed this account's MFA gate.
        abort_unless(hash_equals($security->mfaFingerprint($request->user()), (string) $request->session()->get('security.mfa', '')), 403);
        [$plain, $hashes] = $mfa->recoveryCodes();
        $request->user()->forceFill(['mfa_recovery_codes' => $hashes])->save();
        app(AuditTrail::class)->record('mfa.recovery_codes_regenerated', $request->user());
        $request->session()->put('security.recovery_display', Crypt::encryptString(json_encode($plain, JSON_THROW_ON_ERROR)));

        return to_route('security.index');
    }

    public function revokeOthers(Request $request, AccountSecurity $security)
    {
        $this->requireConfirmation($request, $security);
        $security->revokeOtherSessions($request);

        return to_route('security.index')->with('status', 'Other devices were signed out, including remembered logins.');
    }

    private function requireConfirmation(Request $request, AccountSecurity $security): void
    {
        if ($security->requiresOtp($request->user()) && ! $security->otpVerified($request)) {
            throw ValidationException::withMessages(['identity' => 'Complete your email or phone sign-in code first.']);
        }
        if (! $security->recentlyConfirmed($request)) {
            throw ValidationException::withMessages(['identity' => 'Confirm your identity first.']);
        }
    }
}
