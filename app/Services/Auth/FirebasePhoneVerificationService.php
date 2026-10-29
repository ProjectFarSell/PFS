<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FirebasePhoneVerificationService
{
    private const CERTIFICATES_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

    public function verify(string $idToken): string
    {
        $projectId = (string) config('services.firebase.project_id');
        if ($projectId === '') {
            throw ValidationException::withMessages(['token' => 'Firebase Phone Auth is not configured yet.']);
        }

        [$header, $claims, $signature, $signed] = $this->parseToken($idToken);
        $certificate = $this->certificates()[$header['kid'] ?? ''] ?? null;
        if (($header['alg'] ?? null) !== 'RS256' || ! is_string($certificate)
            || openssl_verify($signed, $signature, $certificate, OPENSSL_ALGO_SHA256) !== 1) {
            throw ValidationException::withMessages(['token' => 'Firebase could not verify this phone confirmation. Please try again.']);
        }

        $now = time();
        if (($claims['aud'] ?? null) !== $projectId
            || ($claims['iss'] ?? null) !== 'https://securetoken.google.com/'.$projectId
            || ! is_numeric($claims['exp'] ?? null) || (int) $claims['exp'] < $now - 60
            || ! is_numeric($claims['iat'] ?? null) || (int) $claims['iat'] > $now + 60
            || ! is_string($claims['phone_number'] ?? null)) {
            throw ValidationException::withMessages(['token' => 'Firebase returned an invalid phone confirmation. Please try again.']);
        }

        return $claims['phone_number'];
    }

    private function certificates(): array
    {
        return Cache::remember('firebase-auth-certificates', now()->addHour(), function (): array {
            $certificates = Http::acceptJson()->get(self::CERTIFICATES_URL)->throw()->json();

            return is_array($certificates) ? $certificates : [];
        });
    }

    private function parseToken(string $token): array
    {
        $segments = explode('.', $token);
        if (count($segments) !== 3) {
            throw ValidationException::withMessages(['token' => 'Firebase returned an invalid phone confirmation. Please try again.']);
        }

        [$header, $claims] = array_map(fn (string $value) => $this->decodeJson($value), array_slice($segments, 0, 2));
        $signature = $this->decode($segments[2]);
        if (! is_array($header) || ! is_array($claims) || $signature === null) {
            throw ValidationException::withMessages(['token' => 'Firebase returned an invalid phone confirmation. Please try again.']);
        }

        return [$header, $claims, $signature, $segments[0].'.'.$segments[1]];
    }

    private function decodeJson(string $value): mixed
    {
        $decoded = $this->decode($value);

        return $decoded === null ? null : json_decode($decoded, true);
    }

    private function decode(string $value): ?string
    {
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
