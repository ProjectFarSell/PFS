<?php

namespace App\Services\Mobile;

use App\Mail\MobileOperationsAlert;
use App\Models\Fulfillment;
use App\Models\MobileDevice;
use App\Models\MobileEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;

class MobileMessaging
{
    public function deliver(int $id): void
    {
        Cache::put('mobile.worker.last_seen', now()->timestamp, 600);
        $event = MobileEvent::find($id);
        if (! $event || $event->completed_at) {
            return;
        }
        if ($event->expires_at->lte(now()) || ! $this->actionable($event)) {
            $event->update(['completed_at' => now(), 'last_error' => null]);

            return;
        }
        if (! config('mobile.push_enabled') && ! config('mobile.realtime_enabled')) {
            return;
        }
        $event->increment('attempts');
        $users = User::whereIn('id', $event->audience)->whereNull('suspended_at')->with('riderProfile')->get()->filter(fn ($user) => $user->role->value === 'buyer' || ($user->isRider() && $user->riderProfile?->isApproved()));
        if (config('mobile.push_enabled')) {
            MobileDevice::whereIn('user_id', $users->pluck('id'))->where('enabled', true)->orderBy('id')->chunkById(50, function ($devices) use ($event) {
                foreach ($devices as $device) {
                    $this->sendDevice($event, $device);
                }
            });
        }
        if (config('mobile.realtime_enabled') && ! $event->broadcast_at) {
            $channels = $users->map(fn ($user) => 'private-user.'.PublicIds::id($user))->all();
            if (isset($event->payload['order_id']) && ! str_starts_with($event->type, 'offer.')) {
                $channels[] = 'private-order.'.$event->payload['order_id'];
            }
            if ($channels) {
                Broadcast::connection('reverb')->broadcast($channels, $event->type, $event->payload);
            }
            $event->update(['broadcast_at' => now()]);
        }
        $event->update(['completed_at' => now(), 'last_error' => null]);
    }

    private function actionable(MobileEvent $event): bool
    {
        if ($event->type !== 'offer.created') {
            return true;
        }
        $offer = DB::table('rider_delivery_requests')->where('public_id', $event->payload['offer_id'])->whereNull('declined_at')->where('expires_at', '>', now())->first();
        $part = $offer ? Fulfillment::find($offer->fulfillment_id) : null;

        return $part && $part->status->value === 'ready' && ! $part->rider_id && ! $part->usesExternalCourier();
    }

    private function sendDevice(MobileEvent $event, MobileDevice $original): void
    {
        // Serialize against logout/rebinding, including while the provider request is in flight.
        DB::transaction(function () use ($event, $original) {
            $device = MobileDevice::whereKey($original->id)->lockForUpdate()->first();
            if (! $device || ! $device->enabled || ! in_array($device->user_id, $event->audience, true) || ! in_array($event->type, $device->enabled_events, true)) {
                return;
            }
            $token = PersonalAccessToken::find($device->token_id);
            $user = User::find($device->user_id);
            if (! $user || $user->suspended_at || ! $token || $token->tokenable_id !== $user->id || $token->mobile_app !== $device->app || $user->role->value !== $device->app || ! $token->expires_at || $token->expires_at->lte(now()) || ! hash_equals((string) $token->identity_hash, app(MobileAuthentication::class)->fingerprint($user))) {
                return;
            }
            $where = ['mobile_event_id' => $event->id, 'mobile_device_id' => $device->id, 'binding_version' => $device->binding_version];
            $delivery = DB::table('mobile_push_deliveries')->where($where)->first();
            if ($delivery?->sent_at || $delivery?->skipped_at) {
                return;
            }
            if ($event->expires_at->lte(now()) || ! $this->actionable($event)) {
                return;
            }
            $status = app(FcmClient::class)->send($device, $event);
            DB::table('mobile_push_deliveries')->updateOrInsert($where, ['sent_at' => $status === 'sent' ? now() : null, 'skipped_at' => $status !== 'sent' ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
            if ($status === 'unregistered') {
                $device->update(['enabled' => false]);
            }
        });
    }

    public function health(): array
    {
        $pending = MobileEvent::whereNull('completed_at')->where('expires_at', '>', now());
        $oldest = (clone $pending)->min('created_at');
        $seen = Cache::get('mobile.worker.last_seen');
        $enabled = config('mobile.push_enabled') || config('mobile.realtime_enabled');
        $failed = (clone $pending)->whereNotNull('last_error')->count();
        $age = $oldest ? max(0, now()->timestamp - Carbon::parse($oldest)->timestamp) : null;
        $worker = is_numeric($seen) && $seen <= now()->timestamp && $seen >= now()->subMinutes(3)->timestamp;

        return ['push_enabled' => (bool) config('mobile.push_enabled'), 'realtime_enabled' => (bool) config('mobile.realtime_enabled'), 'pending' => (clone $pending)->count(), 'failed' => $failed, 'oldest_pending_at' => $oldest, 'oldest_pending_age_seconds' => $age, 'worker_last_seen' => $seen, 'worker_healthy' => $worker, 'healthy' => ! $enabled || ($worker && $failed === 0 && ($age === null || $age < 60))];
    }

    public function alert(): bool
    {
        $health = $this->health();
        $email = config('mobile.alert_email');
        if ($health['healthy'] || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! Cache::add('mobile.alert.cooldown', true, 900)) {
            return false;
        }
        try {
            Mail::to($email)->send(new MobileOperationsAlert($health));

            return true;
        } catch (\Throwable) {
            Cache::forget('mobile.alert.cooldown');
            Log::warning('Mobile operational alert delivery failed.');

            return false;
        }
    }
}
