<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SignInChallenge;
use App\Models\User;
use App\Services\Security\AccountSecurity;
use App\Services\Security\AuditTrail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token)
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:180']]);

        return response()->view('auth.reset-password', ['token' => $token, 'email' => $data['email']])
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, AccountSecurity $security)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'], 'email' => ['required', 'string', 'email', 'max:180'],
            'password' => ['required', 'string', 'confirmed', 'max:1024', PasswordRule::defaults()],
        ]);
        $data['email'] = User::whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->value('email') ?? $data['email'];
        $status = Password::reset($data, function (User $user, string $password) use ($data, $security) {
            DB::transaction(function () use ($user, $password, $data, $security) {
                $account = User::lockForUpdate()->findOrFail($user->id);
                // Recheck and consume under the account lock, including concurrent submissions.
                if (! Password::tokenExists($account, $data['token'])) {
                    throw ValidationException::withMessages(['email' => 'This reset link is invalid or expired. Request a new one.']);
                }
                $account->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60),
                    'security_version' => $account->security_version + 1])->save();
                Password::deleteToken($account);
                $security->deleteSessions($account);
                SignInChallenge::where('user_id', $account->id)->delete();
                app(AuditTrail::class)->record('password.reset', $account);
                DB::afterCommit(fn () => event(new PasswordReset($account)));
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This reset link is invalid or expired. Request a new one.']);
        }

        // Password recovery never signs in the user or clears their second-factor setup.
        return to_route('login')->with('status', 'Password reset. Sign in with your new password, then complete your usual verification step.');
    }
}
