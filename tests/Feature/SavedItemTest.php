<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SavedItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedItemTest extends TestCase
{
    use RefreshDatabase;

    // ── helpers ───────────────────────────────────────────────────────────────

    private function buyer(): User
    {
        return User::factory()->create();
    }

    private function activeProduct(): Product
    {
        return Product::factory()->create(['is_active' => true]);
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    public function test_authenticated_buyer_can_save_a_product(): void
    {
        $buyer = $this->buyer();
        $product = $this->activeProduct();

        $response = $this->actingAs($buyer)
            ->postJson(route('account.saved.store', $product));

        $response->assertOk()->assertJson(['saved' => true]);
        $this->assertDatabaseHas('saved_items', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_saving_the_same_product_twice_is_idempotent(): void
    {
        $buyer = $this->buyer();
        $product = $this->activeProduct();

        $this->actingAs($buyer)->postJson(route('account.saved.store', $product));
        // Second POST must not throw a unique constraint error
        $response = $this->actingAs($buyer)->postJson(route('account.saved.store', $product));

        $response->assertOk()->assertJson(['saved' => true]);
        $this->assertSame(
            1,
            SavedItem::where('user_id', $buyer->id)->where('product_id', $product->id)->count()
        );
    }

    public function test_buyer_can_unsave_a_product(): void
    {
        $buyer = $this->buyer();
        $product = $this->activeProduct();

        SavedItem::create(['user_id' => $buyer->id, 'product_id' => $product->id]);

        $response = $this->actingAs($buyer)
            ->deleteJson(route('account.saved.destroy', $product));

        $response->assertOk()->assertJson(['saved' => false]);
        $this->assertDatabaseMissing('saved_items', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_guest_is_redirected_to_login_on_post(): void
    {
        $product = $this->activeProduct();

        $this->post(route('account.saved.store', $product))
            ->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_on_index(): void
    {
        $this->get(route('account.saved.index'))
            ->assertRedirect(route('login'));
    }

    public function test_index_shows_saved_products(): void
    {
        $buyer = $this->buyer();
        $product = $this->activeProduct();

        SavedItem::create(['user_id' => $buyer->id, 'product_id' => $product->id]);

        $this->actingAs($buyer)
            ->get(route('account.saved.index'))
            ->assertOk()
            ->assertSee($product->name);
    }

    public function test_index_is_empty_when_no_saves(): void
    {
        $buyer = $this->buyer();

        $this->actingAs($buyer)
            ->get(route('account.saved.index'))
            ->assertOk()
            ->assertSee('No saved items yet.');
    }
}
