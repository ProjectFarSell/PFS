<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SellerDocument;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayoutHandoffEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function listing(): array
    {
        $seller = User::factory()->seller()->create();
        $shop = Shop::factory()->create(['user_id' => $seller->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'image_path' => null, 'stock' => 5, 'has_variants' => false]);

        return compact('seller', 'shop', 'product');
    }

    public function test_gallery_supports_five_photos_primary_selection_removal_and_guest_display(): void
    {
        Storage::fake('r2');
        config(['filesystems.disks.r2.url' => 'https://images.example.test']);
        $s = $this->listing();
        $this->actingAs($s['seller'])->post(route('seller.products.images.store', $s['product']), ['images' => array_map(fn ($i) => UploadedFile::fake()->image('photo-'.$i.'.jpg', 10, 10), range(1, 5))])->assertSessionHasNoErrors();
        $product = $s['product']->fresh();
        $this->assertSame(5, $product->images()->count());
        $this->assertCount(5, Storage::disk('r2')->allFiles('products'));
        $this->post(route('seller.products.images.store', $product), ['images' => [UploadedFile::fake()->image('overflow.jpg', 10, 10)]])->assertSessionHasErrors('images');
        $this->assertCount(5, Storage::disk('r2')->allFiles('products'));
        $primary = $product->images()->latest('id')->first();
        $this->put(route('seller.products.images.primary', [$product, $primary]))->assertSessionHasNoErrors();
        $this->assertSame($primary->path, $product->fresh()->image_path);
        $this->get(route('seller.products.edit', $product))->assertOk()->assertSee('Product photos');
        $this->post(route('logout'));
        $this->get(route('products.show', $product))->assertOk()->assertSee('Product photos')->assertSee('https://images.example.test/products/', false);
        $this->actingAs($s['seller'])->delete(route('seller.products.images.destroy', [$product, $primary]))->assertSessionHasNoErrors();
        $this->assertSame(4, $product->images()->count());
        $this->assertNotSame($primary->path, $product->fresh()->image_path);
    }

    public function test_gallery_rejects_foreign_owner_foreign_image_and_wrong_role(): void
    {
        Storage::fake('r2');
        $s = $this->listing();
        $other = $this->listing();
        $foreign = $other['product']->images()->create(['path' => 'products/foreign.jpg', 'sort_order' => 0]);
        $this->actingAs($s['seller'])->put(route('seller.products.images.primary', [$s['product'], $foreign]))->assertNotFound();
        $this->delete(route('seller.products.images.destroy', [$s['product'], $foreign]))->assertNotFound();
        $this->post(route('seller.products.images.store', $other['product']), ['images' => [UploadedFile::fake()->image('photo.jpg')]])->assertNotFound();
        foreach (['buyer', 'rider', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->post(route('seller.products.images.store', $s['product']), [])->assertForbidden();
            $this->put(route('seller.products.images.primary', [$s['product'], $foreign]))->assertForbidden();
            $this->delete(route('seller.products.images.destroy', [$s['product'], $foreign]))->assertForbidden();
            $this->delete(route('seller.products.images.destroy-primary', $s['product']))->assertForbidden();
        }
    }

    public function test_legacy_primary_counts_toward_limit_and_can_be_removed(): void
    {
        Storage::fake('r2');
        $s = $this->listing();
        $s['product']->update(['image_path' => 'products/legacy.jpg']);
        Storage::disk('r2')->put('products/legacy.jpg', 'legacy');
        $this->actingAs($s['seller'])->post(route('seller.products.images.store', $s['product']), ['images' => array_map(fn ($i) => UploadedFile::fake()->image('photo-'.$i.'.jpg', 10, 10), range(1, 5))])->assertSessionHasErrors('images');
        $this->assertSame('products/legacy.jpg', $s['product']->fresh()->image_path);
        $this->delete(route('seller.products.images.destroy-primary', $s['product']))->assertSessionHasNoErrors();
        $this->assertNull($s['product']->fresh()->image_path);
    }

    public function test_hidden_product_is_unavailable_and_seller_cannot_republish_it(): void
    {
        $s = $this->listing();
        $s['product']->update(['is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.products.hide', $s['product']), ['reason' => 'Listing requires verification.'])->assertSessionHasNoErrors();
        $product = $s['product']->fresh();
        $this->assertFalse($product->is_active);
        $this->get(route('admin.products.index', ['visibility' => 'hidden']))->assertOk()->assertSee($product->name);
        $this->post(route('logout'));
        $this->get(route('products.show', $product))->assertNotFound();
        $this->getJson('/api/v1/products/'.$product->public_id)->assertNotFound();
        $this->actingAs($s['seller'])->put(route('seller.products.update', $product), ['name' => $product->name, 'description' => 'Still the same listing.', 'category_id' => $product->category_id,
            'price' => '100.00', 'stock' => 5, 'has_variants' => 0, 'is_active' => 1, 'version' => $product->editVersion()])->assertSessionHasErrors('is_active');
        $this->assertFalse($product->fresh()->is_active);
        $this->post(route('admin.products.restore', $product), ['reason' => 'Attempted by seller'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.products.restore', $product), ['reason' => 'Verification completed.'])->assertSessionHasNoErrors();
        $this->assertTrue($product->fresh()->is_active);
        $this->post(route('logout'));
        $this->get(route('products.show', $product))->assertOk();
        $this->assertDatabaseHas('security_audit_events', ['action' => 'product.hidden']);
        $this->assertDatabaseHas('security_audit_events', ['action' => 'product.restored']);
    }

    public function test_restore_does_not_publish_a_product_that_was_already_a_draft(): void
    {
        $s = $this->listing();
        $s['product']->update(['is_active' => false]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('admin.products.hide', $s['product']), [])->assertSessionHasErrors('reason');
        $this->post(route('admin.products.hide', $s['product']), ['reason' => 'Review draft.'])->assertSessionHasNoErrors();
        $this->post(route('admin.products.restore', $s['product']), ['reason' => 'Review complete.'])->assertSessionHasNoErrors();
        $this->assertFalse($s['product']->fresh()->is_active);
    }

    public function test_business_permit_uploads_are_private_owner_scoped_and_invalidate_stale_review_revision(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->create();
        $application = $buyer->sellerApplication()->create(['shop_name' => 'Permit QA Shop', 'city' => 'Quezon City', 'description' => 'Application details.', 'status' => 'pending', 'revision' => (string) Str::uuid(), 'submitted_at' => now()]);
        $oldRevision = $application->revision;
        $this->actingAs($buyer)->post(route('seller.documents.store'), ['business_permit' => UploadedFile::fake()->createWithContent('permit.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF")])->assertSessionHasNoErrors();
        $document = SellerDocument::sole();
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertArrayNotHasKey('file_path', $document->toArray());
        $this->assertNotSame($oldRevision, $application->fresh()->revision);
        $this->get(route('seller.documents.show', $document))->assertOk()->assertDownload('business-permit.pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs(User::factory()->create())->get(route('seller.documents.show', $document))->assertNotFound();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.sellers.documents.show', [$application, $document]))->assertOk();
        $this->post(route('admin.sellers.review', $application), ['decision' => 'approve', 'revision' => $oldRevision, 'confirmed' => 1])->assertSessionHasErrors('decision');
        $this->get(route('admin.sellers.show', $application))->assertOk()->assertSee('Download business permit');
        $this->actingAs(User::factory()->create(['role' => 'rider']))->get(route('seller.documents.show', $document))->assertForbidden();
        $this->get(route('admin.sellers.documents.show', [$application, $document]))->assertForbidden();
    }
}
