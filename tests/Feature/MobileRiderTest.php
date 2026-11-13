<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\RiderProfile;
use App\Models\User;
use App\Services\Mobile\MobileAuthentication;
use App\Services\Mobile\MobileFixtures;
use App\Services\Orders\FulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\ChecksMobileContract;
use Tests\TestCase;

class MobileRiderTest extends TestCase
{
    use ChecksMobileContract, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mobile.events_enabled' => false]);
        app(MobileFixtures::class)->seed();
    }

    private function user(string $role): User
    {
        return User::where('email', 'mobile-'.$role.'@farsell.test')->firstOrFail();
    }

    private function headers(string $role): array
    {
        return ['Authorization' => 'Bearer '.app(MobileAuthentication::class)->issue($this->user($role), $role === 'pending-rider' ? 'rider' : $role)['access_token'], 'Accept' => 'application/json', 'Idempotency-Key' => (string) Str::uuid()];
    }

    private function part(string $state): Fulfillment
    {
        return Order::where('number', 'MOBQA-'.$state)->firstOrFail()->fulfillments()->firstOrFail();
    }

    public function test_fixture_states_and_role_scope_and_history(): void
    {
        $this->assertDatabaseCount('orders', 12);
        $headers = $this->headers('rider');
        $this->getJson('/api/v1/rider/status', $headers)->assertOk()->assertJsonPath('data.can_deliver', true);
        $this->getJson('/api/v1/rider/shipments', $headers)->assertOk()->assertJsonCount(4, 'data');
        $this->getJson('/api/v1/rider/shipments?view=history', $headers)->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/rider/earnings', $headers)->assertOk()->assertJsonPath('data.credited_total', 4000)->assertJsonCount(1, 'data.ledger.data');
        $this->getJson('/api/v1/rider/shipments/'.$this->part('pending')->public_id, $headers)->assertNotFound();
        $this->getJson('/api/v1/rider/shipments', $this->headers('pending-rider'))->assertForbidden();
        $this->getJson('/api/v1/rider/shipments', $this->headers('buyer'))->assertForbidden();
    }

    public function test_multipart_proof_replay_does_not_duplicate_files_and_bad_pin_cleans_new_upload(): void
    {
        Storage::fake('local');
        $part = $this->part('in_transit');
        $headers = $this->headers('rider');
        $file = UploadedFile::fake()->image('proof.jpg', 10, 10);
        $data = ['delivery_recipient_name' => 'QA buyer', 'delivery_pin' => $part->delivery_pin, 'delivery_note' => 'QA handover note.', 'cod_collected' => true, 'delivery_photo' => $file];
        $url = '/api/v1/rider/shipments/'.$part->public_id.'/deliver';
        $response = $this->withHeaders($headers)->post($url, $data);
        $this->assertMobileResponse($response, 'POST', $url)->assertOk();
        $this->withHeaders($headers)->post($url, $data)->assertOk();
        $this->assertCount(1, Storage::disk('local')->files('delivery-proof'));
        $this->withHeaders($headers)->post($url, [...$data, 'delivery_note' => 'Different request body'])->assertConflict();
        $next = $this->part('assigned');
        app(FulfillmentService::class)->act($next, $this->user('rider'), 'pickup', []);
        $headers['Idempotency-Key'] = (string) Str::uuid();
        $this->withHeaders($headers)->post('/api/v1/rider/shipments/'.$next->public_id.'/deliver', [...$data, 'delivery_pin' => '000000', 'delivery_photo' => UploadedFile::fake()->image('wrong.jpg', 10, 10)])->assertUnprocessable();
        $this->assertCount(1, Storage::disk('local')->files('delivery-proof'));
    }

    public function test_offer_acceptance_replay_and_unavailable_state_do_not_assign_twice(): void
    {
        $headers = $this->headers('rider');
        $offer = $this->getJson('/api/v1/rider/offers', $headers)->assertOk()->assertJsonCount(1, 'data')->json('data.0.id');
        $this->postJson('/api/v1/rider/offers/'.$offer.'/decision', ['decision' => 'accept'], $headers)->assertOk()->assertJsonPath('data.shipment.status', 'assigned')->assertJsonMissingPath('data.shipment.delivery_pin');
        $this->postJson('/api/v1/rider/offers/'.$offer.'/decision', ['decision' => 'accept'], $headers)->assertOk();
        $this->postJson('/api/v1/rider/offers/'.$offer.'/decision', ['decision' => 'decline'], $headers)->assertConflict()->assertJsonPath('code', 'idempotency_conflict');
        $headers['Idempotency-Key'] = (string) Str::uuid();
        $this->postJson('/api/v1/rider/offers/'.$offer.'/decision', ['decision' => 'accept'], $headers)->assertConflict();
        $this->assertSame(1, $this->part('ready')->events()->where('status', 'assigned')->count());
    }

    public function test_availability_off_keeps_assignments_and_expired_offers_reject(): void
    {
        $headers = $this->headers('rider');
        $offer = DB::table('rider_delivery_requests')->first();
        DB::table('rider_delivery_requests')->where('id', $offer->id)->update(['expires_at' => now()->subSecond()]);
        $this->postJson('/api/v1/rider/offers/'.$offer->public_id.'/decision', ['decision' => 'accept'], $headers)->assertConflict()->assertJsonPath('code', 'offer_expired');
        $this->putJson('/api/v1/rider/availability', ['is_available' => false], $headers)->assertOk()->assertJsonPath('data.is_available', false);
        $this->getJson('/api/v1/rider/offers', $headers)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/rider/shipments/'.$this->part('assigned')->public_id, $headers)->assertOk();
    }

    public function test_pickup_delivery_and_buyer_confirmation_credit_only_once(): void
    {
        $part = $this->part('assigned');
        $rider = $this->headers('rider');
        $buyer = $this->headers('buyer');
        $this->postJson('/api/v1/rider/shipments/'.$part->public_id.'/pickup', [], $rider)->assertOk()->assertJsonPath('data.status', 'in_transit')->assertJsonMissingPath('data.delivery_pin');
        $this->postJson('/api/v1/rider/shipments/'.$part->public_id.'/pickup', [], $rider)->assertOk();
        $order = $this->getJson('/api/v1/buyer/orders/'.$part->order->public_id, $buyer)->assertOk()->json('data');
        $pin = $order['shipments'][0]['delivery_pin'];
        $delivery = ['delivery_recipient_name' => 'QA buyer', 'delivery_pin' => $pin, 'delivery_note' => 'Handed to recipient.', 'cod_collected' => true];
        $rider['Idempotency-Key'] = (string) Str::uuid();
        $this->postJson('/api/v1/rider/shipments/'.$part->public_id.'/deliver', $delivery, $rider)->assertOk()->assertJsonPath('data.status', 'delivered')->assertDontSee($pin);
        $this->assertSame(0, $part->riderEarning()->count());
        $this->postJson('/api/v1/buyer/shipments/'.$part->public_id.'/confirm-receipt', [], $buyer)->assertOk()->assertJsonPath('data.status', 'completed');
        $this->postJson('/api/v1/buyer/shipments/'.$part->public_id.'/confirm-receipt', [], $buyer)->assertOk();
        $this->assertSame(1, $part->riderEarning()->count());
        $this->getJson('/api/v1/buyer/orders/'.$part->order->public_id, $buyer)->assertOk()->assertJsonMissingPath('data.shipments.0.delivery_pin');
    }

    public function test_pin_limits_and_failed_attempts_preserve_dispatch_review(): void
    {
        $part = $this->part('in_transit');
        $headers = $this->headers('rider');
        for ($i = 0; $i < 5; $i++) {
            $headers['Idempotency-Key'] = (string) Str::uuid();
            $this->postJson('/api/v1/rider/shipments/'.$part->public_id.'/deliver', ['delivery_recipient_name' => 'QA buyer', 'delivery_pin' => '000000', 'delivery_note' => 'QA handover note', 'cod_collected' => true], $headers)->assertUnprocessable();
        }
        $headers['Idempotency-Key'] = (string) Str::uuid();
        $this->postJson('/api/v1/rider/shipments/'.$part->public_id.'/deliver', ['delivery_recipient_name' => 'QA buyer', 'delivery_pin' => $part->delivery_pin, 'delivery_note' => 'QA handover note', 'cod_collected' => true], $headers)->assertStatus(429)->assertHeader('Retry-After');
        $this->postJson('/api/v1/rider/shipments/'.$part->public_id.'/failed-attempts', ['failure_reason' => 'unreachable', 'contact_method' => 'phone', 'exception_note' => 'Buyer cannot be reached after contact attempts.'], $headers)->assertOk()->assertJsonPath('data.status', 'delivery_failed');
        $this->assertSame(0, $part->riderEarning()->count());
    }

    public function test_another_rider_cannot_read_shipment_or_offer_and_proof_is_owner_only(): void
    {
        $other = User::factory()->create(['role' => UserRole::Rider]);
        RiderProfile::create(['user_id' => $other->id, 'status' => 'approved', 'vehicle_type' => 'motorcycle', 'license_no' => 'TEST ONLY', 'city' => 'Mobile QA City']);
        $headers = ['Authorization' => 'Bearer '.app(MobileAuthentication::class)->issue($other, 'rider')['access_token'], 'Idempotency-Key' => (string) Str::uuid()];
        $this->getJson('/api/v1/rider/shipments/'.$this->part('assigned')->public_id, $headers)->assertNotFound();
        $this->postJson('/api/v1/rider/offers/'.DB::table('rider_delivery_requests')->value('public_id').'/decision', ['decision' => 'accept'], $headers)->assertNotFound();
        Storage::fake('local');
        Storage::disk('local')->put('delivery-proof/test.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5a0AAAAASUVORK5CYII='));
        $part = $this->part('delivered');
        $part->update(['delivery_photo_path' => 'delivery-proof/test.png']);
        $this->getJson('/api/v1/buyer/shipments/'.$part->public_id.'/proof-photo', $this->headers('buyer'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $buyer = User::factory()->create();
        $otherBuyer = ['Authorization' => 'Bearer '.app(MobileAuthentication::class)->issue($buyer, 'buyer')['access_token']];
        $this->getJson('/api/v1/buyer/shipments/'.$part->public_id.'/proof-photo', $otherBuyer)->assertNotFound();
    }
}
