<?php

namespace App\Services\Mobile;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleIdentity
{
    public function verify(string $token, int $maxAgeSeconds = 3600): array
    {
        $audience = config('mobile.google_client_id') ?: config('services.google.client_id');
        if (! is_string($audience) || $audience === '') {
            throw new ApiFailure('service_unavailable', 'Google sign-in is not configured for the API.', 503);
        }
        try {
            $pieces = explode('.', $token);
            if (count($pieces) !== 3) {
                throw new \RuntimeException;
            }
            $decode = fn ($value) => base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
            $header = json_decode($decode($pieces[0]), true, 16, JSON_THROW_ON_ERROR);
            $claims = json_decode($decode($pieces[1]), true, 16, JSON_THROW_ON_ERROR);
            if (($header['alg'] ?? '') !== 'RS256' || ! is_string($header['kid'] ?? null)) {
                throw new \RuntimeException;
            }
            $certs = Cache::remember('mobile.google.certificates', 3600, function () {
                $response = Http::connectTimeout(3)->timeout(8)->get('https://www.googleapis.com/oauth2/v1/certs');
                if (! $response->successful() || ! is_array($response->json())) {
                    throw new ApiFailure('service_unavailable', 'Google verification is temporarily unavailable.', 503);
                }

                return $response->json();
            });
            $cert = $certs[$header['kid']] ?? null;
            if (! is_string($cert) || openssl_verify($pieces[0].'.'.$pieces[1], $decode($pieces[2]), $cert, OPENSSL_ALGO_SHA256) !== 1) {
                throw new \RuntimeException;
            }
            $now = now()->timestamp;
            if (($claims['aud'] ?? null) !== $audience || ! in_array($claims['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
                || ! is_int($claims['exp'] ?? null) || $claims['exp'] <= $now || ! is_int($claims['iat'] ?? null) || $claims['iat'] > $now + 60 || $claims['iat'] < $now - $maxAgeSeconds
                || ! is_string($claims['sub'] ?? null) || strlen($claims['sub']) < 1 || strlen($claims['sub']) > 255
                || ($claims['email_verified'] ?? null) !== true || ! filter_var($claims['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException;
            }

            return ['sub' => $claims['sub'], 'email' => mb_strtolower($claims['email']), 'name' => mb_substr((string) ($claims['name'] ?? 'FarSell buyer'), 0, 120)];
        } catch (ApiFailure $exception) {
            throw $exception;
        } catch (ConnectionException) {
            throw new ApiFailure('service_unavailable', 'Google verification is temporarily unavailable.', 503);
        } catch (\Throwable) {
            throw new ApiFailure('unauthenticated', 'Google could not verify this sign-in. Please try again.', 401);
        }
    }
}
