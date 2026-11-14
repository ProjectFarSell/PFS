<?php

namespace App\Services\Mobile;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleAccessToken
{
    public function get(string $app = 'buyer'): string
    {
        [$project, $path] = FirebaseProjects::push($app);

        return Cache::remember('mobile-fcm-oauth:'.hash('sha256', $path.'|'.$project), 3300, function () use ($path, $project) {
            if (! $path || ! is_readable($path)) {
                throw new RuntimeException('FCM service account is unavailable.');
            }
            $account = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if (($account['type'] ?? null) !== 'service_account' || ($account['project_id'] ?? null) !== $project || empty($account['client_email']) || empty($account['private_key'])) {
                throw new RuntimeException('FCM service account configuration is invalid.');
            }
            $encode = fn ($value) => rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
            $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode(['iss' => $account['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => now()->timestamp, 'exp' => now()->addHour()->timestamp]);
            if (! openssl_sign($unsigned, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('FCM signing failed.');
            }
            $jwt = $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
            $response = Http::connectTimeout(3)->timeout(10)->asForm()->post('https://oauth2.googleapis.com/token', ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]);
            $token = $response->json('access_token');
            if (! $response->successful() || ! is_string($token) || $token === '') {
                throw new RuntimeException('FCM authentication failed.');
            }

            return $token;
        });
    }
}
