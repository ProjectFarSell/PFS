<?php

namespace App\Services\Logistics;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LalamoveClient
{
    public function configured(): bool
    {
        return config('logistics.lalamove.enabled') && filled(config('logistics.lalamove.key')) && filled(config('logistics.lalamove.secret'))
            && in_array(config('logistics.lalamove.environment'), ['sandbox', 'production'], true);
    }

    public function quote(array $stops, string $service): array
    {
        return $this->request('POST', '/v3/quotations', ['serviceType' => $service, 'language' => 'en_PH', 'stops' => $stops]);
    }

    public function book(array $data): array
    {
        if (config('logistics.lalamove.environment') === 'production' && ! config('logistics.lalamove.allow_live_bookings')) {
            throw ValidationException::withMessages(['logistics' => 'Live courier bookings are disabled.']);
        }

        return $this->request('POST', '/v3/orders', $data);
    }

    public function status(string $reference): array
    {
        if (! preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $reference)) {
            throw ValidationException::withMessages(['logistics' => 'Invalid courier reference.']);
        }

        return $this->request('GET', '/v3/orders/'.$reference);
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        if (! $this->configured()) {
            throw ValidationException::withMessages(['logistics' => 'Lalamove API is not configured. Manual booking records are available.']);
        }
        $body = $payload === null ? '' : json_encode(['data' => $payload], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $time = (string) now()->getTimestampMs();
        $signature = hash_hmac('sha256', $time."\r\n".$method."\r\n".$path."\r\n\r\n".$body, config('logistics.lalamove.secret'));
        $host = config('logistics.lalamove.environment') === 'production' ? 'https://rest.lalamove.com' : 'https://rest.sandbox.lalamove.com';
        // Request-ID is a diagnostic nonce, not a provider idempotency guarantee. Never auto-retry bookings.
        try {
            $response = Http::connectTimeout(4)->timeout(15)->withHeaders([
                'Authorization' => 'hmac '.config('logistics.lalamove.key').':'.$time.':'.$signature,
                'Market' => 'PH', 'Request-ID' => (string) Str::uuid(), 'Accept' => 'application/json',
            ])->withBody($body, 'application/json')->send($method, $host.$path);
            $data = $response->json('data');
            if (! $response->successful() || ! is_array($data)) {
                throw new \RuntimeException('Invalid courier response.');
            }

            return $data;
        } catch (\Throwable $exception) {
            // Do not surface provider bodies or request exceptions containing contacts/auth headers.
            throw ValidationException::withMessages(['logistics' => 'The courier request was not confirmed. Check the provider portal before trying another booking.']);
        }
    }
}
