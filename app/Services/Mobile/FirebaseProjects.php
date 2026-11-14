<?php

namespace App\Services\Mobile;

/** Provider selection is based on a trusted ticket/token role, never request JSON. */
class FirebaseProjects
{
    public static function phone(string $app): ?string
    {
        return ($app === 'rider' ? config('mobile.rider_firebase_project_id') : null)
            ?: config('mobile.firebase_project_id');
    }

    public static function push(string $app): array
    {
        if ($app === 'rider' && filled(config('mobile.rider_fcm_project'))) {
            // A dedicated project must use its own credential. Never fall back to
            // the Buyer's private key when only the Rider project is configured.
            return [(string) config('mobile.rider_fcm_project'), (string) config('mobile.rider_fcm_credentials')];
        }

        return [(string) config('mobile.fcm_project'), (string) config('mobile.fcm_credentials')];
    }
}
