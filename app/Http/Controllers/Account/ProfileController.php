<?php

namespace App\Http\Controllers\Account;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $isSellerProfile = $user->role === UserRole::Seller;
        $isRiderProfile = $user->role === UserRole::Rider;
        if ($isSellerProfile) {
            $user->loadMissing('shop');
        }

        return view('account.profile', compact('user', 'isSellerProfile', 'isRiderProfile'));
    }

    public function edit(Request $request): View
    {
        return view('account.profile-edit', [
            'user' => $request->user(),
            'deletionBlocked' => $this->deletionBlocked($request->user()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:180', function ($attribute, $value, $fail) use ($user) {
                if (User::whereRaw('LOWER(email) = ?', [Str::lower($value)])->where('id', '!=', $user->id)->exists()) {
                    $fail('This email address is already in use.');
                }
            }],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s().-]{5,29}$/'],
            'current_password' => ['required', 'string', 'current_password'],
        ]);

        DB::transaction(function () use ($user, $data) {
            $account = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->checkPassword($account, $data['current_password'], 'profile');
            if ($account->email !== $data['email']) {
                $this->clearPasswordResetTokens($account);
                $account->email_verified_at = null;
            }
            // Explicit allowlist: never accept role, approval fields or another user's ID.
            $account->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ])->save();
        });

        return to_route('account.profile')->with('status', 'Profile updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed', 'different:current_password'],
        ]);

        DB::transaction(function () use ($request, $data) {
            $account = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $this->checkPassword($account, $data['current_password'], 'password');
            $account->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
            $this->clearPasswordResetTokens($account);
        });

        $this->clearDatabaseSessions($request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('status', 'Password changed. Sign in again with your new password.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('deletion', [
            'current_password' => ['required', 'string', 'current_password'],
            'confirmation' => ['required', Rule::in(['DELETE'])],
        ]);
        $user = $request->user();

        DB::transaction(function () use ($user, $data) {
            $account = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->checkPassword($account, $data['current_password'], 'deletion');
            if ($this->deletionBlocked($account)) {
                throw ValidationException::withMessages([
                    'account' => 'Self-deletion is limited to buyer accounts without orders, a shop, or seller/rider applications. Contact an administrator for help.',
                ])->errorBag('deletion');
            }
            $this->clearPasswordResetTokens($account);
            $account->delete(); // Saved addresses cascade; marketplace history is guarded above.
        });

        $this->clearDatabaseSessions($user);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('status', 'Your account and saved addresses have been permanently deleted.');
    }

    private function deletionBlocked(User $user): bool
    {
        return $user->role !== UserRole::Buyer || $user->orders()->exists()
            || $user->shop()->exists() || $user->riderProfile()->exists() || $user->sellerApplication()->exists();
    }

    private function checkPassword(User $user, string $password, string $bag): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.'])->errorBag($bag);
        }
    }

    private function clearPasswordResetTokens(User $user): void
    {
        $table = config('auth.passwords.users.table', 'password_reset_tokens');
        DB::table($table)->where('email', $user->email)->delete();
    }

    private function clearDatabaseSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)->delete();
        }
    }
}
