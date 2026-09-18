<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RiderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\RiderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiderDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_must_sign_in_and_accounts_without_profile_see_onboarding_only(): void
    {
        $this->get(route('rider.dashboard'))->assertRedirect(route('login'));
        foreach ([UserRole::Buyer, UserRole::Rider] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('rider.dashboard'))->assertOk()->assertSee('Complete your rider application')
                ->assertSee(route('rider.register'), false)->assertViewHas('canViewDeliveries', false)
                ->assertViewMissing('deliveries');
        }
    }

    public function test_pending_rejected_and_suspended_profiles_never_load_assignments(): void
    {
        foreach ([RiderStatus::Pending, RiderStatus::Rejected, RiderStatus::Suspended] as $status) {
            $rider = User::factory()->rider()->create();
            $profile = $this->profile($rider, $status);
            $order = $this->order($profile, OrderStatus::Assigned, 'PRIVATE DISABLED ADDRESS');
            $this->actingAs($rider)->get(route('rider.dashboard'))->assertOk()
                ->assertSee('Application: '.ucfirst($status->value))
                ->assertDontSee($order->number)->assertDontSee('PRIVATE DISABLED ADDRESS')
                ->assertViewHas('canViewDeliveries', false)->assertViewMissing('deliveries')->assertViewMissing('stats');
        }
    }

    public function test_approved_profile_without_rider_role_cannot_access_deliveries(): void
    {
        foreach ([UserRole::Buyer, UserRole::Seller] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $profile = $this->profile($user);
            $order = $this->order($profile, OrderStatus::Assigned, 'ROLE BLOCKED ADDRESS');
            $this->actingAs($user)->get(route('rider.dashboard'))->assertOk()
                ->assertSee('Rider account activation needed')->assertDontSee($order->number)
                ->assertDontSee('ROLE BLOCKED ADDRESS')->assertViewMissing('deliveries');
        }
    }

    public function test_approved_rider_sees_only_own_active_assignments_and_minimal_delivery_data(): void
    {
        // Force user and profile IDs to differ, guarding against the wrong foreign key.
        User::factory()->count(4)->create();
        $rider = User::factory()->rider()->create();
        $profile = $this->profile($rider);
        $other = $this->profile(User::factory()->rider()->create());
        $assigned = $this->order($profile, OrderStatus::Assigned, 'OWN ACTIVE ADDRESS');
        $transit = $this->order($profile, OrderStatus::InTransit, 'OWN TRANSIT ADDRESS');
        $finished = $this->order($profile, OrderStatus::Delivered, 'HISTORICAL ADDRESS');
        $cancelled = $this->order($profile, OrderStatus::Cancelled, 'CANCELLED ADDRESS');
        $packed = $this->order($profile, OrderStatus::Packed, 'NOT DISPATCHED ADDRESS');
        $foreign = $this->order($other, OrderStatus::Assigned, 'OTHER RIDER ADDRESS');

        $response = $this->actingAs($rider)->get(route('rider.dashboard', ['rider_id' => $other->id, 'user_id' => $other->user_id]))
            ->assertOk()->assertSee($assigned->number)->assertSee($transit->number)
            ->assertSee('OWN ACTIVE ADDRESS')->assertSee('OWN TRANSIT ADDRESS')
            ->assertDontSee('HISTORICAL ADDRESS')->assertDontSee('CANCELLED ADDRESS')
            ->assertDontSee('NOT DISPATCHED ADDRESS')->assertDontSee('OTHER RIDER ADDRESS')
            ->assertDontSee($finished->number)->assertDontSee($cancelled->number)->assertDontSee($packed->number)->assertDontSee($foreign->number)
            ->assertDontSee('PRIVATE BUYER')->assertDontSee('private@example.test')->assertDontSee('9,876.54')
            ->assertDontSee(route('orders.show', $assigned), false)
            ->assertViewHas('stats', ['Assigned' => 1, 'In transit' => 1, 'Completed' => 1]);
        $this->assertNotEquals($rider->id, $profile->id);
        $this->assertEqualsCanonicalizing(['id', 'number', 'status', 'ship_to', 'created_at'], array_keys($response->viewData('deliveries')->first()->getAttributes()));
        $this->get(route('orders.show', $assigned))->assertForbidden();
        $this->assertSame(OrderStatus::Assigned, $assigned->fresh()->status);
    }

    public function test_filter_and_pagination_are_scoped_and_oldest_first(): void
    {
        $rider = User::factory()->rider()->create();
        $profile = $this->profile($rider);
        $orders = collect(range(1, 11))->map(fn () => $this->order($profile));
        $transit = $this->order($profile, OrderStatus::InTransit);
        $this->actingAs($rider)->get(route('rider.dashboard', ['status' => 'assigned']))->assertOk()
            ->assertSeeInOrder($orders->take(10)->pluck('number')->all())->assertDontSee($orders->last()->number)
            ->assertDontSee($transit->number)
            ->assertViewHas('deliveries', fn ($rows) => $rows->total() === 11 && str_contains($rows->nextPageUrl(), 'status=assigned'));
        $this->get(route('rider.dashboard', ['status' => 'assigned', 'page' => 2]))->assertOk()
            ->assertSee($orders->last()->number)->assertDontSee($orders->first()->number);
        $this->getJson(route('rider.dashboard', ['status' => 'delivered']))->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_rider_profile_and_navigation_have_rider_shortcuts_not_buyer_shortcuts(): void
    {
        $rider = User::factory()->rider()->create();
        $this->profile($rider);
        $this->actingAs($rider)->get(route('account.profile'))->assertOk()
            ->assertSee('Rider Profile')->assertSee('Rider Dashboard')->assertSee('Rider Application')
            ->assertSee(route('rider.dashboard'), false)->assertDontSee('My Orders')->assertDontSee('My Addresses');
        $this->get(route('rider.profile'))->assertOk()->assertSee(route('rider.dashboard'), false);
        $this->get(route('rider.dashboard'))->assertOk()->assertSee('No active deliveries assigned to you yet.');
        $this->post(route('rider.dashboard'), ['status' => 'delivered'])->assertStatus(405);
    }

    public function test_admin_cannot_use_rider_dashboard_to_see_other_riders_assignments(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->profile($admin);
        $other = $this->profile(User::factory()->rider()->create());
        $foreign = $this->order($other, OrderStatus::Assigned, 'FOREIGN DELIVERY');
        $this->actingAs($admin)->get(route('rider.dashboard', ['rider_id' => $other->id]))->assertOk()
            ->assertDontSee($foreign->number)->assertDontSee('FOREIGN DELIVERY')
            ->assertViewHas('stats', ['Assigned' => 0, 'In transit' => 0, 'Completed' => 0]);
    }

    private function profile(User $user, RiderStatus $status = RiderStatus::Approved): RiderProfile
    {
        return $user->riderProfile()->create([
            'status' => $status, 'vehicle_type' => 'motorcycle', 'license_no' => 'DEMO-123', 'city' => 'Quezon City',
        ]);
    }

    private function order(RiderProfile $profile, OrderStatus $status = OrderStatus::Assigned, string $address = 'Demo delivery address'): Order
    {
        return $profile->deliveries()->create([
            'user_id' => User::factory()->create()->id,
            'number' => 'RIDER-'.fake()->unique()->numerify('########'),
            'status' => $status, 'payment_method' => PaymentMethod::Cod,
            'ship_to' => $address, 'guest_name' => 'PRIVATE BUYER', 'guest_email' => 'private@example.test',
            'subtotal' => 9827.54, 'shipping_fee' => 49, 'total' => 9876.54,
        ]);
    }
}
