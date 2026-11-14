<?php

namespace App\Support;

use App\Models\User;

class DemoAccount
{
    public const ROLES = [
        'admin@farsell.test' => 'admin',
        'buyer@farsell.test' => 'buyer',
        'seller@farsell.test' => 'seller',
        'rider@farsell.test' => 'rider',
        'mobile-admin@farsell.test' => 'admin',
        'mobile-buyer@farsell.test' => 'buyer',
        'mobile-seller@farsell.test' => 'seller',
        'mobile-rider@farsell.test' => 'rider',
        'mobile-pending-rider@farsell.test' => 'rider',
    ];

    public static function mark(User $user): void
    {
        if (app()->environment(['local', 'testing']) && (self::ROLES[$user->email] ?? null) === $user->role->value && ! $user->is_demo_account) {
            // Trusted seeding only. Never accept this field through registration/profile input.
            $user->forceFill(['is_demo_account' => true])->save();
        }
    }

    public static function exempt(User $user): bool
    {
        return app()->environment(['local', 'testing']) && $user->is_demo_account
            && (self::ROLES[$user->email] ?? null) === $user->role->value;
    }
}
