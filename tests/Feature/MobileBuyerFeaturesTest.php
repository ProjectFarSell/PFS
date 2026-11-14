<?php

namespace Tests\Feature;

use App\Enums\FulfillmentStatus;
use App\Enums\UserRole;
use App\Models\BuyerNotification;
use App\Models\MobileEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\RiderProfile;
use App\Models\User;
use App\Services\Mobile\ApiFailure;
use App\Services\Mobile\GoogleIdentity;
use App\Services\Mobile\MobileAuthentication;
use App\Support\PrivacyNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\ChecksMobileContract;
use Tests\TestCase;

class MobileBuyerFeaturesTest extends TestCase
{
    use ChecksMobileContract, RefreshDatabase;

    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer '.app(MobileAuthentication::class)->issue($user, $user->role->value)['access_token'], 'Idempotency-Key' => (string) Str::uuid()];
    }

    private function shipment(User $user, string $status = 'completed'): array
    {
        $order = Order::create(['user_id' => $user->id, 'number' => 'TEST-'.Str::random(10), 'status' => in_array($status, ['completed', 'delivered'], true) ? $status : 'confirmed', 'payment_method' => 'cod', 'guest_name' => $user->name, 'ship_to' => 'Test address', 'subtotal' => 200, 'shipping_fee' => 49, 'total' => 249]);
        $product = Product::factory()->create(['price' => 100, 'stock' => 5]);
        $part = $order->fulfillments()->create(['shop_id' => $product->shop_id, 'shop_name' => $product->shop->name, 'status' => FulfillmentStatus::from($status), 'subtotal' => 200, 'shipping_fee' => 49]);
        $item = $order->items()->create(['fulfillment_id' => $part->id, 'product_id' => $product->id, 'name' => 'Test shirt', 'qty' => 2, 'unit_price' => 100, 'line_total' => 200]);

        return [$order, $part, $item, $product];
    }

    public function test_chat_is_shared_scoped_and_message_retry_is_safe(): void
    {
        config(['mobile.push_enabled' => false, 'mobile.realtime_enabled' => false]);
        $buyer = User::factory()->create();
        $headers = $this->headers($buyer);
        $product = Product::factory()->create();
        $thread = $this->postJson('/api/v1/buyer/products/'.$product->public_id.'/conversation', [], $headers)->assertCreated()->json('data.id');
        $url = '/api/v1/buyer/conversations/'.$thread.'/messages';
        $message = $this->postJson($url, ['body' => 'Hello seller'], $headers)->assertCreated()->json('data');
        $this->postJson($url, ['body' => 'Hello seller'], $headers)->assertCreated()->assertJsonPath('data.id', $message['id']);
        $this->postJson($url, ['body' => 'Changed payload'], $headers)->assertConflict();
        $this->assertDatabaseCount('messages', 1);
        $this->getJson('/api/v1/buyer/conversations', $headers)->assertOk()->assertJsonPath('data.0.shop_name', $product->shop->name);
        $this->getJson($url, $headers)->assertOk()->assertJsonPath('data.0.mine', true);
        $foreign = $this->headers(User::factory()->create());
        $this->getJson($url, $foreign)->assertNotFound();
        $this->postJson($url, ['body' => 'Not mine'], $foreign)->assertNotFound();
        $event = MobileEvent::where('type', 'chat.message_created')->sole();
        $this->assertStringNotContainsString('Hello seller', json_encode($event->payload));
        $this->assertSame($thread, $event->payload['conversation_id']);
    }

    public function test_feedback_uses_web_service_and_public_reviews_keep_anonymity(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        [$order, $part, $item, $product] = $this->shipment($user);
        $headers = $this->headers($user);
        $url = '/api/v1/buyer/shipments/'.$part->public_id.'/feedback';
        $this->getJson($url, $headers)->assertOk()->assertJsonPath('data.items.0.review', null);
        $payload = ['reviews' => [$item->public_id => ['rating' => 5, 'feedback' => 'Good quality', 'is_anonymous' => true]], 'seller_service_rating' => 4, 'delivery_speed_rating' => 5];
        $this->postJson($url, $payload, $headers)->assertOk()->assertJsonPath('data.items.0.review.author', 'Anonymous buyer');
        $this->postJson($url, $payload, $headers)->assertOk();
        $this->assertDatabaseCount('product_reviews', 1);
        $this->getJson('/api/v1/products/'.$product->public_id.'/reviews')->assertOk()->assertJsonPath('data.0.author', 'Anonymous buyer');
        $this->getJson($url, $this->headers(User::factory()->create()))->assertNotFound();
        $product->update(['is_active' => false]);
        $this->getJson('/api/v1/products/'.$product->public_id.'/reviews')->assertNotFound();
    }

    public function test_feedback_media_is_private_no_store_and_unknown_items_rejected(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        [$order, $part, $item] = $this->shipment($user);
        $headers = $this->headers($user);
        $url = '/api/v1/buyer/shipments/'.$part->public_id.'/feedback';
        $bad = ['reviews' => [(string) Str::uuid() => ['rating' => 4]], 'seller_service_rating' => 4, 'delivery_speed_rating' => 4];
        $this->postJson($url, $bad, $headers)->assertUnprocessable();
        $response = $this->withHeaders($headers)->post($url, ['reviews' => [$item->public_id => ['rating' => 4, 'media' => [UploadedFile::fake()->image('proof.jpg')]]], 'seller_service_rating' => 4, 'delivery_speed_rating' => 4]);
        $this->assertMobileResponse($response, 'POST', $url)->assertOk();
        $media = ProductReview::sole()->media()->sole();
        $this->get('/api/v1/review-media/'.$media->public_id)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertCount(1, Storage::disk('local')->allFiles('product-review-media'));
    }

    public function test_return_request_is_owned_after_delivery_and_replay_safe(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        [$order, $part, $item] = $this->shipment($user, 'delivered');
        $headers = $this->headers($user);
        $url = '/api/v1/buyer/order-items/'.$item->public_id.'/returns';
        $payload = ['quantity' => 1, 'reason' => 'damaged', 'details' => 'The item arrived damaged.'];
        $id = $this->postJson($url, $payload, $headers)->assertCreated()->assertJsonPath('data.requested_amount', 10000)->json('data.id');
        $this->postJson($url, $payload, $headers)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('return_requests', 1);
        $this->getJson('/api/v1/buyer/returns', $headers)->assertOk()->assertJsonPath('data.0.status', 'pending');
        $foreign = $this->headers(User::factory()->create());
        $this->postJson($url, $payload, $foreign)->assertNotFound();
        $this->getJson('/api/v1/buyer/returns/'.$id.'/evidence', $foreign)->assertNotFound();
        [$order2, $part2, $item2] = $this->shipment($user, 'assigned');
        $this->postJson('/api/v1/buyer/order-items/'.$item2->public_id.'/returns', $payload, $this->headers($user))->assertUnprocessable();
    }

    public function test_cancel_obeys_existing_order_restrictions_and_owner_scope(): void
    {
        config(['notifications.enabled' => false]);
        $user = User::factory()->create();
        [$order, $part] = $this->shipment($user, 'pending');
        $headers = $this->headers($user);
        $url = '/api/v1/buyer/orders/'.$order->public_id.'/cancel';
        $this->postJson($url, ['reason' => 'Changed my mind'], $this->headers(User::factory()->create()))->assertNotFound();
        $this->postJson($url, ['reason' => 'Changed my mind'], $headers)->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson($url, ['reason' => 'Changed my mind'], $headers)->assertOk();
        [$order2, $part2] = $this->shipment($user, 'assigned');
        $part2->update(['rider_id' => RiderProfile::create(['user_id' => User::factory()->create(['role' => UserRole::Rider])->id, 'status' => 'approved', 'vehicle_type' => 'motorcycle', 'license_no' => 'TEST123', 'city' => 'Quezon City'])->id]);
        $this->postJson('/api/v1/buyer/orders/'.$order2->public_id.'/cancel', ['reason' => 'Too late now'], $this->headers($user))->assertUnprocessable();
    }

    public function test_inbox_is_owned_read_state_and_rider_boundary(): void
    {
        $user = User::factory()->create();
        [$order] = $this->shipment($user);
        $entry = BuyerNotification::create(['user_id' => $user->id, 'order_id' => $order->id, 'event_key' => 'test', 'title' => 'Test update', 'message' => 'View order details.']);
        $headers = $this->headers($user);
        $this->getJson('/api/v1/buyer/notifications', $headers)->assertOk()->assertJsonPath('data.0.is_read', false);
        $url = '/api/v1/buyer/notifications/'.$entry->public_id.'/read';
        $this->postJson($url, [], $headers)->assertOk()->assertJsonPath('data.is_read', true);
        $this->postJson($url, [], $this->headers(User::factory()->create()))->assertNotFound();
        $rider = $this->headers(User::factory()->create(['role' => UserRole::Rider]));
        foreach (['notifications', 'returns', 'conversations'] as $path) {
            $this->getJson('/api/v1/buyer/'.$path, $rider)->assertForbidden();
        }
    }

    public function test_google_requires_valid_identity_consent_and_existing_otp_rules(): void
    {
        config(['security.login_otp_required' => true]);
        $user = User::factory()->create(['email' => 'google-buyer@example.test']);
        $this->mock(GoogleIdentity::class, fn ($mock) => $mock->shouldReceive('verify')->andReturn(['sub' => 'google-subject', 'email' => $user->email, 'name' => 'Google buyer']));
        $data = ['id_token' => str_repeat('a', 64), 'privacy_consent' => true, 'privacy_notice_version' => PrivacyNotice::VERSION];
        $this->postJson('/api/v1/auth/google', [...$data, 'privacy_consent' => false])->assertUnprocessable();
        $this->postJson('/api/v1/auth/google', $data)->assertAccepted()->assertJsonStructure(['data' => ['challenge_token']])->assertJsonMissingPath('data.access_token');
        $this->assertSame('google-subject', $user->fresh()->google_id);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_google_jwt_signature_audience_expiry_and_verified_email(): void
    {
        config(['mobile.google_client_id' => 'test-client']);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => __DIR__.'/../Fixtures/openssl.cnf']);
        $public = openssl_pkey_get_details($key)['key'];
        Cache::put('mobile.google.certificates', ['test-key' => $public], 3600);
        $encode = fn ($value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $claims = ['sub' => 'subject', 'email' => 'buyer@example.test', 'email_verified' => true, 'name' => 'Buyer', 'aud' => 'test-client', 'iss' => 'https://accounts.google.com', 'iat' => now()->timestamp, 'exp' => now()->addHour()->timestamp];
        $token = function ($claims) use ($encode, $key) {
            $body = $encode(json_encode(['alg' => 'RS256', 'kid' => 'test-key'])).'.'.$encode(json_encode($claims));
            openssl_sign($body, $signature, $key, OPENSSL_ALGO_SHA256);

            return $body.'.'.$encode($signature);
        };
        $this->assertSame('subject', app(GoogleIdentity::class)->verify($token($claims))['sub']);
        foreach ([['aud' => 'wrong'], ['email_verified' => false], ['exp' => now()->subMinute()->timestamp], ['iss' => 'https://attacker.example']] as $invalid) {
            try {
                app(GoogleIdentity::class)->verify($token([...$claims, ...$invalid]));
                $this->fail('Invalid Google proof accepted.');
            } catch (ApiFailure $error) {
                $this->assertSame(401, $error->status);
            }
        }
    }
}
