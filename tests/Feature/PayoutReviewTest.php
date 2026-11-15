<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ShipmentScenario;
use Tests\TestCase;

class PayoutReviewTest extends TestCase
{
    use RefreshDatabase;
    use ShipmentScenario;

    public function test_admin_sees_only_settled_shipments_without_a_delivery_event(): void
    {
        $scenario = $this->shipmentScenario();
        $this->actingAs($scenario['admin'])->get(route('admin.payouts.needs-review'))
            ->assertOk()->assertSee('No settled shipments need review.')->assertDontSee($scenario['order']->number);

        $this->completeShipment($scenario);

        $this->actingAs($scenario['admin'])->get(route('admin.payouts.needs-review'))
            ->assertOk()->assertSee('No settled shipments need review.')->assertDontSee($scenario['order']->number);

        $scenario['part']->events()->where('status', 'delivered')->delete();

        $this->get(route('admin.payouts.needs-review'))->assertOk()
            ->assertSee($scenario['order']->number)->assertSee($scenario['shop']->name)
            ->assertSee('No delivery event recorded');
    }

    public function test_guests_and_non_admin_roles_cannot_access_the_review_list(): void
    {
        $this->get(route('admin.payouts.needs-review'))->assertRedirect(route('login'));

        foreach ([UserRole::Buyer, UserRole::Seller, UserRole::Rider] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.payouts.needs-review'))->assertForbidden();
        }
    }
}
