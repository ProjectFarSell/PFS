<?php

namespace App\Services\Mobile;

use App\Models\MobileDevice;
use App\Models\MobileEvent;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmClient
{
    public function send(MobileDevice $device, MobileEvent $event): string
    {
        $project = (string) config('mobile.fcm_project');
        if (! preg_match('/^[a-z][a-z0-9-]{4,62}$/', $project)) {
            throw new RuntimeException('FCM project is not configured.');
        }
        $ttl = max(0, min(86400, $event->expires_at->timestamp - now()->timestamp));
        if ($ttl === 0) {
            return 'expired';
        }
        $payload = array_map(fn ($value) => (string) $value, $event->payload);
        $response = Http::connectTimeout(3)->timeout(10)->withToken(app(GoogleAccessToken::class)->get())->post('https://fcm.googleapis.com/v1/projects/'.$project.'/messages:send', ['message' => ['token' => $device->fcm_token, 'notification' => ['title' => $event->type === 'offer.created' ? 'FarSell delivery offer' : 'FarSell update', 'body' => 'Open FarSell to view the latest update.'], 'data' => $payload, 'android' => ['priority' => str_starts_with($event->type, 'offer.') ? 'high' : 'normal', 'ttl' => $ttl.'s', 'collapse_key' => $payload['offer_id'] ?? $payload['shipment_id'] ?? $payload['order_id'] ?? $payload['event_id']], 'apns' => ['headers' => ['apns-expiration' => (string) $event->expires_at->timestamp, 'apns-collapse-id' => $payload['event_id']]]]]);
        if ($response->successful()) {
            return 'sent';
        }
        foreach ((array) $response->json('error.details', []) as $detail) {
            if (($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return 'unregistered';
            }
        }
        // Never throw an HTTP exception containing provider bodies, tokens or credentials.
        throw new RuntimeException('FCM did not accept the message.');
    }
}
