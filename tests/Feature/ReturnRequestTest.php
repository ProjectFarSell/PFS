<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReturnRequestTest extends TestCase
{
    use RefreshDatabase;

    private function delivered(): array
    {
        config(['notifications.enabled' => false]);
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'stock' => 3]);
        $order = Order::query()->create(['user_id' => $buyer->id, 'number' => 'FS-RETURNS', 'status' => 'delivered', 'payment_method' => 'cod', 'ship_to' => 'Private address', 'subtotal' => 200, 'shipping_fee' => 49, 'total' => 249]);
        $part = $order->fulfillments()->create(['shop_id' => $shop->id, 'shop_name' => $shop->name, 'status' => 'delivered', 'subtotal' => 200, 'shipping_fee' => 49, 'cod_collected_at' => now()]);
        $item = $order->items()->create(['fulfillment_id' => $part->id, 'product_id' => $product->id, 'name' => 'Returnable shirt', 'product_sku' => 'TEST-BKS', 'qty' => 2, 'unit_price' => 100, 'line_total' => 200]);

        return compact('buyer', 'seller', 'order', 'part', 'item', 'product');
    }

    private function submit(array $s, array $extra = [])
    {
        return $this->actingAs($s['buyer'])->post(route('returns.store', $s['item']), [...['quantity' => 1, 'reason' => 'damaged', 'details' => 'The shirt arrived torn.'], ...$extra]);
    }

    public function test_buyer_seller_admin_review_and_cod_reimbursement_history(): void
    {
        $s = $this->delivered();
        $this->submit($s)->assertRedirect(route('returns.index'));
        $case = ReturnRequest::query()->sole();
        $this->assertEquals(100, $case->requested_amount);
        $this->actingAs($s['seller'])->get(route('returns.index'))->assertOk()->assertSee('Returnable shirt')->assertDontSee('Private address');
        $this->post(route('returns.review', $case), ['action' => 'seller_approved', 'note' => 'We accept the reported damage.'])->assertSessionHasNoErrors();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->post(route('returns.review', $case), ['action' => 'approved', 'approved_amount' => 100, 'return_required' => 1, 'note' => 'Return the shirt to the shop.'])->assertSessionHasNoErrors();
        $refund = ['action' => 'refunded', 'note' => 'Reimbursed using cash at the shop.', 'refund_reference' => 'CASH-001', 'reimbursement_paid' => 1];
        $this->post(route('returns.review', $case), $refund)->assertSessionHasErrors('return_received');
        $this->post(route('returns.review', $case), [...$refund, 'return_received' => 1])->assertSessionHasNoErrors();
        $this->assertSame('refunded', $case->fresh()->status);
        $this->assertNotNull($case->fresh()->return_received_at);
        $this->assertSame(3, $s['product']->fresh()->stock);
        $this->assertSame(4, $case->events()->count());
        $this->post(route('returns.review', $case), [...$refund, 'return_received' => 1])->assertSessionHasErrors('return');
        $this->actingAs($s['buyer'])->get(route('returns.index'))->assertSee('CASH-001')->assertSee('Reimbursement recorded');
    }

    public function test_ownership_private_evidence_and_role_boundaries(): void
    {
        Storage::fake('local');
        $s = $this->delivered();
        $this->submit($s, ['evidence' => UploadedFile::fake()->image('damage.png')])->assertSessionHasNoErrors();
        $case = ReturnRequest::query()->sole();
        $this->get(route('returns.evidence', $case))->assertOk();
        $this->actingAs($s['seller'])->get(route('returns.evidence', $case))->assertOk();
        $this->post(route('returns.review', $case), ['action' => 'approved', 'approved_amount' => 100, 'note' => 'Try bypassing admin'])->assertSessionHasErrors('return');
        $this->actingAs(User::factory()->create())->get(route('returns.evidence', $case))->assertNotFound();
        $this->post(route('returns.store', $s['item']), ['quantity' => 1])->assertForbidden();
        $this->actingAs(User::factory()->seller()->create())->get(route('returns.index'))->assertDontSee('Returnable shirt');
        $this->post(route('returns.review', $case), ['action' => 'seller_declined', 'note' => 'Not my shipment'])->assertNotFound();
        $this->actingAs($s['buyer'])->post(route('returns.review', $case), ['action' => 'approved'])->assertForbidden();
    }

    public function test_invalid_quantities_duplicate_requests_and_excess_refunds_are_rejected(): void
    {
        $s = $this->delivered();
        $this->submit($s, ['quantity' => 3])->assertSessionHasErrors('quantity');
        $s['part']->update(['status' => 'in_transit']);
        $this->submit($s)->assertSessionHasErrors('return');
        $s['part']->update(['status' => 'delivered']);
        $this->submit($s)->assertSessionHasNoErrors();
        $this->submit($s)->assertSessionHasErrors('return');
        $case = ReturnRequest::query()->sole();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->post(route('returns.review', $case), ['action' => 'approved', 'approved_amount' => 101, 'note' => 'Too much refund'])->assertSessionHasErrors('approved_amount');
        $this->post(route('returns.review', $case), ['action' => 'refunded', 'refund_reference' => 'TEST-001', 'note' => 'Not actually paid'])->assertSessionHasErrors('reimbursement_paid');
        $this->post(route('returns.review', $case), ['action' => 'rejected', 'note' => 'Evidence does not support the claim.'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $case->fresh()->status);
    }

    public function test_demo_or_uncollected_payments_cannot_be_marked_reimbursed(): void
    {
        $s = $this->delivered();
        $this->submit($s);
        $case = ReturnRequest::query()->sole();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->post(route('returns.review', $case), ['action' => 'approved', 'approved_amount' => 100, 'note' => 'Review approved'])->assertSessionHasNoErrors();
        $s['part']->update(['cod_collected_at' => null]);
        $this->post(route('returns.review', $case), ['action' => 'refunded', 'refund_reference' => 'TEST-001', 'note' => 'Not actually collected', 'reimbursement_paid' => 1])->assertSessionHasErrors('refund');
        $this->assertNull($case->fresh()->refunded_at);
    }
}
