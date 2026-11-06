<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ReplacementRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ShipmentScenario;
use Tests\TestCase;

class ReplacementOrderTest extends TestCase
{
    use RefreshDatabase, ShipmentScenario;

    private function requested(): array
    {
        $s = $this->shipmentScenario(true);
        $this->returnShipment($s);
        $item = $s['part']->items()->sole();
        $variant = $s['product']->variants()->create(['sku' => 'SCEN-BLM2', 'options' => ['Size' => 'M', 'Color' => 'Blue'], 'stock' => 3, 'is_active' => true]);
        $this->actingAs($s['buyer'])->get(route('replacements.create', $item))->assertOk()->assertSee('not a free exchange');
        $this->post(route('replacements.store', $item), ['variant_id' => $variant->id, 'quantity' => 1, 'note' => 'Please replace with size M in blue.'])->assertSessionHasNoErrors();

        return [...$s, 'item' => $item, 'desired' => $variant, 'case' => ReplacementRequest::sole()];
    }

    public function test_approved_request_creates_linked_new_cod_order_only_after_buyer_checkout(): void
    {
        $s = $this->requested();
        $this->actingAs($s['seller'])->post(route('replacements.review', $s['case']), ['decision' => 'approved', 'review_note' => 'Original goods received; blue medium is available.'])->assertSessionHasNoErrors();
        $this->assertSame(3, $s['desired']->fresh()->stock);
        $this->actingAs($s['buyer'])->post(route('replacements.checkout', $s['case']))->assertRedirect('/checkout');
        $this->get('/checkout')->assertOk()->assertSee('Replacement order')->assertSee('SCEN-BLM2');
        $this->post('/checkout', ['address_id' => $s['address']->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
        $case = $s['case']->fresh();
        $this->assertSame('ordered', $case->status);
        $this->assertSame(2, Order::count());
        $this->assertSame(2, $s['desired']->fresh()->stock);
        $this->assertSame('returned', $s['order']->fresh()->status->value);
        $this->get(route('orders.show', $case->new_order_id))->assertOk()->assertSee($s['order']->number)->assertSee('SCEN-BLM2');
        $this->post(route('replacements.checkout', $case))->assertUnprocessable();
    }

    public function test_unreturned_foreign_and_duplicate_requests_are_blocked(): void
    {
        $s = $this->shipmentScenario(true);
        $item = $s['part']->items()->sole();
        $payload = ['variant_id' => $s['option']->id, 'quantity' => 1, 'note' => 'Please send the correct size and color.'];
        $this->actingAs($s['buyer'])->post(route('replacements.store', $item), $payload)->assertSessionHasErrors('replacement');
        $this->returnShipment($s);
        $this->actingAs(User::factory()->create())->post(route('replacements.store', $item), $payload)->assertForbidden();
        $this->actingAs($s['buyer'])->post(route('replacements.store', $item), $payload)->assertSessionHasNoErrors();
        $this->post(route('replacements.store', $item), $payload)->assertSessionHasErrors('replacement');
        $this->assertDatabaseCount('replacement_requests', 1);
        $case = ReplacementRequest::sole();
        $this->actingAs(User::factory()->seller()->create())->post(route('replacements.review', $case), ['decision' => 'approved', 'review_note' => 'Not my shop, must not be accepted.'])->assertForbidden();
        $this->get('/replacements')->assertOk()->assertDontSee($s['order']->number);
    }

    public function test_sold_out_approved_replacement_cannot_create_an_order_or_reserve_other_stock(): void
    {
        $s = $this->requested();
        $this->actingAs($s['admin'])->post(route('replacements.review', $s['case']), ['decision' => 'approved', 'review_note' => 'Proceed with current price at checkout.'])->assertSessionHasNoErrors();
        $this->actingAs($s['buyer'])->post(route('replacements.checkout', $s['case']))->assertRedirect();
        $s['desired']->update(['stock' => 0]);
        $this->post('/checkout', ['address_id' => $s['address']->id, 'payment_method' => 'cod'])->assertSessionHasErrors('replacement');
        $this->assertSame(1, Order::count());
        $this->assertSame('approved', $s['case']->fresh()->status);
    }
}
