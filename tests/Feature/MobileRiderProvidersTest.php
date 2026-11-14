<?php

namespace Tests\Feature;

use App\Models\MobileDevice;
use App\Models\MobileEvent;
use App\Services\Mobile\FcmClient;
use App\Services\Mobile\FirebaseProjects;
use App\Services\Mobile\GoogleAccessToken;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileRiderProvidersTest extends TestCase
{
    public function test_push_uses_the_project_and_credential_selected_by_the_bound_device_app(): void
    {
        config(['mobile.fcm_project' => 'buyer-project', 'mobile.rider_fcm_project' => 'rider-project']);
        $oauth = $this->mock(GoogleAccessToken::class);
        $oauth->shouldReceive('get')->once()->with('buyer')->andReturn('buyer-oauth');
        $oauth->shouldReceive('get')->once()->with('rider')->andReturn('rider-oauth');
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'accepted'], 200)]);
        $event = new MobileEvent(['type' => 'shipment.status_changed', 'expires_at' => now()->addMinute(), 'payload' => ['event_id' => 'test-event']]);
        foreach (['buyer', 'rider'] as $app) {
            $this->assertSame('sent', app(FcmClient::class)->send(new MobileDevice(['app' => $app, 'fcm_token' => $app.'-device']), $event));
            Http::assertSent(fn ($request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/'.$app.'-project/messages:send'
                && $request->hasHeader('Authorization', 'Bearer '.$app.'-oauth') && $request['message']['token'] === $app.'-device');
        }
        Http::assertSentCount(2);
    }

    public function test_unset_rider_overrides_retain_the_existing_shared_project_behavior(): void
    {
        config(['mobile.firebase_project_id' => 'shared-project', 'mobile.fcm_project' => 'shared-project', 'mobile.fcm_credentials' => '/shared/key.json',
            'mobile.rider_firebase_project_id' => null, 'mobile.rider_fcm_project' => null]);
        $this->assertSame('shared-project', FirebaseProjects::phone('rider'));
        $this->assertSame(['shared-project', '/shared/key.json'], FirebaseProjects::push('rider'));
    }

    public function test_dedicated_rider_project_never_uses_the_buyer_private_key(): void
    {
        Http::fake();
        config(['mobile.fcm_project' => 'buyer-project', 'mobile.fcm_credentials' => '/buyer/key.json',
            'mobile.rider_fcm_project' => 'rider-project', 'mobile.rider_fcm_credentials' => null]);
        $this->assertSame(['rider-project', ''], FirebaseProjects::push('rider'));
        try {
            app(GoogleAccessToken::class)->get('rider');
            $this->fail('Missing Rider credentials must fail closed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('FCM service account is unavailable.', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_android_association_can_list_buyer_and_rider_apps(): void
    {
        $sha = implode(':', array_fill(0, 32, 'AB'));
        config(['mobile.android_package' => 'com.farsell.buyer', 'mobile.android_fingerprints' => [$sha],
            'mobile.rider_android_package' => 'com.farsell.rider', 'mobile.rider_android_fingerprints' => [$sha]]);
        $this->getJson('/.well-known/assetlinks.json')->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.target.package_name', 'com.farsell.buyer')->assertJsonPath('1.target.package_name', 'com.farsell.rider');
        config(['mobile.rider_android_fingerprints' => ['invalid']]);
        $this->getJson('/.well-known/assetlinks.json')->assertStatus(503);
    }
}
