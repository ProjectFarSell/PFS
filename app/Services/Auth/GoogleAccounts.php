<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleAccounts
{
    /** Call only after a trusted Google exchange or signature verification. */
    public function resolve(array $profile, ?string $role = null): User
    {
        return DB::transaction(function () use ($profile, $role) {
            $user = User::where('google_id', $profile['sub'])->lockForUpdate()->first();
            $user ??= User::whereRaw('LOWER(email) = ?', [mb_strtolower($profile['email'])])->lockForUpdate()->first();
            if ($user) {
                abort_if($role && ($user->role->value !== $role || $user->suspended_at), 403);
                if ($user->google_id && ! hash_equals($user->google_id, $profile['sub'])) {
                    throw ValidationException::withMessages(['google' => 'This email belongs to an account linked to another Google identity.']);
                }
                if (! $user->google_id) {
                    $user->forceFill(['google_id' => $profile['sub'], 'email_verified_at' => $user->email_verified_at ?? now()])->save();
                }

                return $user;
            }

            return User::create(['name' => mb_substr($profile['name'], 0, 120), 'email' => mb_strtolower($profile['email']), 'password' => Str::random(64), 'google_id' => $profile['sub'], 'email_verified_at' => now(), 'role' => UserRole::Buyer]);
        });
    }
}
