<?php

namespace Tests\Feature;

use App\Mail\MobileOperationsAlert;
use App\Models\MobileDevice;
use App\Models\User;
use App\Services\Mobile\GoogleAccessToken;
use App\Services\Mobile\MobileAuthentication;
use App\Services\Mobile\MobileEvents;
use App\Services\Mobile\MobileMessaging;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\ChecksMobileContract;
use Tests\TestCase;

class MobileMessagingTest extends TestCase
{
    use ChecksMobileContract, RefreshDatabase;

    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer '.app(MobileAuthentication::class)->issue($user, $user->role->value)['access_token']];
    }

    private function bind(User $user): MobileDevice
    {
        $id = (string) Str::uuid();
        $this->putJson('/api/v1/devices/'.$id, ['installation_id' => $id, 'platform' => 'android', 'app' => 'buyer', 'fcm_token' => 'test-token-'.$id, 'enabled_events' => ['order.placed']], $this->headers($user))->assertOk();

        return MobileDevice::where('installation_id', $id)->firstOrFail();
    }

    public function test_push_is_generic_owner_scoped_and_deduplicated_after_success(): void
    {
        Bus::fake();
        config(['mobile.push_enabled' => true, 'mobile.realtime_enabled' => false, 'mobile.fcm_project' => 'farsell-test-project']);
        $this->mock(GoogleAccessToken::class)->shouldReceive('get')->andReturn('fake-oauth-token');
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'test-accepted'], 200)]);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownerDevice = $this->bind($owner);
        $this->bind($other);
        $event = app(MobileEvents::class)->record('push-test', 'order.placed', [$owner->id], ['order_id' => (string) Str::uuid(), 'delivery_pin' => '123456', 'email' => $owner->email, 'address' => 'Private street']);
        app(MobileMessaging::class)->deliver($event->id);
        app(MobileMessaging::class)->deliver($event->id);
        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($ownerDevice) {
            $body = $request->data();
            $this->assertSame($ownerDevice->fcm_token, $body['message']['token']);
            $this->assertSame(['event_id', 'type', 'occurred_at', 'order_id'], array_keys($body['message']['data']));
            $this->assertStringNotContainsString('Private street', json_encode($body));
            $this->assertStringNotContainsString('123456', json_encode($body));

            return true;
        });
        $this->assertDatabaseCount('mobile_push_deliveries', 1);
    }

    public function test_unregistered_tokens_disable_only_the_current_binding_and_stale_events_do_not_send(): void
    {
        Bus::fake();
        config(['mobile.push_enabled' => true, 'mobile.realtime_enabled' => false, 'mobile.fcm_project' => 'farsell-test-project']);
        $this->mock(GoogleAccessToken::class)->shouldReceive('get')->andReturn('fake-oauth-token');
        Http::fake(['fcm.googleapis.com/*' => Http::response(['error' => ['details' => [['errorCode' => 'UNREGISTERED']]]], 404)]);
        $user = User::factory()->create();
        $device = $this->bind($user);
        $event = app(MobileEvents::class)->record('dead-token', 'order.placed', [$user->id], []);
        app(MobileMessaging::class)->deliver($event->id);
        $this->assertFalse($device->fresh()->enabled);
        $expired = app(MobileEvents::class)->record('expired-offer', 'offer.created', [$user->id], ['offer_id' => (string) Str::uuid()], expires: now()->subSecond());
        app(MobileMessaging::class)->deliver($expired->id);
        Http::assertSentCount(1);
        $this->assertNotNull($expired->fresh()->completed_at);
    }

    public function test_rebinding_does_not_send_the_previous_accounts_event_to_the_new_user(): void
    {
        Bus::fake();
        config(['mobile.push_enabled' => true]);
        Http::fake();
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $device = $this->bind($owner);
        $event = app(MobileEvents::class)->record('previous-owner', 'order.placed', [$owner->id], []);
        $this->putJson('/api/v1/devices/'.$device->installation_id, ['installation_id' => $device->installation_id, 'platform' => 'android', 'app' => 'buyer', 'fcm_token' => 'new-test-token', 'enabled_events' => ['order.placed']], $this->headers($newOwner))->assertOk();
        app(MobileMessaging::class)->deliver($event->id);
        Http::assertNothingSent();
    }

    public function test_private_channel_auth_is_scoped_and_events_broadcast_only_generic_payloads(): void
    {
        Bus::fake();
        config(['mobile.push_enabled' => false, 'mobile.realtime_enabled' => true, 'broadcasting.connections.reverb.key' => 'test-app-key', 'broadcasting.connections.reverb.secret' => 'test-secret']);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $headers = $this->headers($owner);
        $channel = 'private-user.'.$owner->public_id;
        $auth = $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => $channel], $headers)->assertOk()->json('auth');
        $this->assertSame('test-app-key:'.hash_hmac('sha256', '123.456:'.$channel, 'test-secret'), $auth);
        $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-user.'.$other->public_id], $headers)->assertForbidden();
        $broadcaster = \Mockery::mock(Broadcaster::class);
        Broadcast::shouldReceive('connection')->with('reverb')->once()->andReturn($broadcaster);
        $broadcaster->shouldReceive('broadcast')->once()->withArgs(fn ($channels, $type, $payload) => $channels === [$channel] && $type === 'order.placed' && ! isset($payload['delivery_pin']));
        $event = app(MobileEvents::class)->record('realtime-test', 'order.placed', [$owner->id], []);
        app(MobileMessaging::class)->deliver($event->id);
    }

    public function test_rollback_does_not_publish_an_event_and_domain_key_deduplicates(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        DB::beginTransaction();
        app(MobileEvents::class)->record('rolled-back', 'order.placed', [$user->id], []);
        DB::rollBack();
        $this->assertDatabaseCount('mobile_events', 0);
        Bus::assertNothingDispatched();
        app(MobileEvents::class)->record('one-domain-event', 'order.placed', [$user->id], []);
        app(MobileEvents::class)->record('one-domain-event', 'order.placed', [$user->id], []);
        $this->assertDatabaseCount('mobile_events', 1);
    }

    public function test_operational_failures_alert_the_configured_operator_with_cooldown(): void
    {
        Mail::fake();
        Bus::fake();
        config(['mobile.push_enabled' => true, 'mobile.alert_email' => 'operator@example.test']);
        $user = User::factory()->create();
        $event = app(MobileEvents::class)->record('failed-provider', 'order.placed', [$user->id], []);
        $event->update(['last_error' => 'Provider unavailable.']);
        $messaging = app(MobileMessaging::class);
        $this->assertFalse($messaging->health()['healthy']);
        $this->assertTrue($messaging->alert());
        $this->assertFalse($messaging->alert());
        Mail::assertSent(MobileOperationsAlert::class, 1);
    }
}
