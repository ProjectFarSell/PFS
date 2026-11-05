<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductReviewMedia;
use App\Models\RiderDeliveryRating;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function shipment(): array
    {
        $buyer = User::factory()->create(['name' => 'Private Reviewer Name']);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $shop = Shop::factory()->create(['user_id' => $seller->id]);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'name' => 'Delivered demo item']);
        $rider = RiderProfile::query()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Rider])->id,
            'status' => 'approved', 'vehicle_type' => 'motorcycle', 'license_no' => 'TEST123', 'city' => 'Quezon City',
        ]);
        $order = Order::query()->create([
            'user_id' => $buyer->id, 'number' => 'FS'.Str::upper(Str::random(10)),
            'status' => 'completed', 'payment_method' => 'cod', 'guest_name' => $buyer->name,
            'ship_to' => 'Test delivery address', 'subtotal' => 100, 'shipping_fee' => 49, 'total' => 149,
        ]);
        $part = $order->fulfillments()->create([
            'shop_id' => $shop->id, 'shop_name' => $shop->name, 'rider_id' => $rider->id,
            'status' => 'completed', 'subtotal' => 100, 'shipping_fee' => 49,
        ]);
        $item = $order->items()->create([
            'fulfillment_id' => $part->id, 'product_id' => $product->id, 'name' => $product->name,
            'product_sku' => 'DEMO-BKS', 'variant_options' => ['Size' => 'S', 'Color' => 'Black'],
            'qty' => 1, 'unit_price' => 100, 'line_total' => 100,
        ]);

        return compact('buyer', 'seller', 'product', 'rider', 'order', 'part', 'item');
    }

    private function payload(array $shipment): array
    {
        return [
            'reviews' => [$shipment['item']->id => ['rating' => 5, 'feedback' => 'Exactly as described and well packed.', 'is_anonymous' => true]],
            'seller_service_rating' => 4, 'delivery_speed_rating' => 5,
            'rider_rating' => 4, 'rider_feedback' => 'Clear communication.', 'rider_tags' => ['Friendly', 'Reliable'],
        ];
    }

    public function test_completed_order_opens_review_form_and_saves_product_service_and_rider_feedback(): void
    {
        $s = $this->shipment();
        $this->actingAs($s['buyer'])->get(route('orders.show', $s['order']))->assertOk()->assertSee('Rate products &amp; delivery', false);
        $this->get(route('orders.feedback.create', $s['part']))->assertOk()
            ->assertSee('Delivered demo item')->assertSee('Seller service')->assertSee('Review anonymously');
        $this->post(route('orders.feedback.store', $s['part']), $this->payload($s))
            ->assertSessionHasNoErrors()->assertRedirect(route('orders.show', $s['order']));

        $this->assertDatabaseHas('product_reviews', ['order_item_id' => $s['item']->id, 'rating' => 5, 'is_anonymous' => true]);
        $this->assertDatabaseHas('shipment_feedback', ['fulfillment_id' => $s['part']->id, 'seller_service_rating' => 4, 'delivery_speed_rating' => 5]);
        $rating = RiderDeliveryRating::query()->sole();
        $this->assertSame(['Friendly', 'Reliable'], $rating->tags);
        $this->assertSame(4, $rating->rating);
        $this->get(route('orders.feedback.create', $s['part']))->assertOk()->assertSee('You have reviewed all items');
        $this->get(route('orders.show', $s['order']))->assertOk()->assertSee('View your feedback');
    }

    public function test_only_the_purchasing_buyer_can_review_after_confirming_receipt(): void
    {
        $s = $this->shipment();
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('orders.feedback.create', $s['part']))->assertNotFound();
        $this->post(route('orders.feedback.store', $s['part']), $this->payload($s))->assertNotFound();
        $this->actingAs($s['seller'])->get(route('orders.feedback.create', $s['part']))->assertForbidden();
        $s['part']->update(['status' => 'delivered']);
        $this->actingAs($s['buyer'])->get(route('orders.feedback.create', $s['part']))->assertStatus(422);
        $this->post(route('orders.feedback.store', $s['part']), $this->payload($s))->assertStatus(422);
        $this->assertDatabaseCount('product_reviews', 0);
    }

    public function test_foreign_items_invalid_scores_and_unknown_rider_tags_are_rejected(): void
    {
        $s = $this->shipment();
        $payload = $this->payload($s);
        $payload['reviews'][999999] = ['rating' => 5];
        $this->actingAs($s['buyer'])->post(route('orders.feedback.store', $s['part']), $payload)->assertSessionHasErrors('reviews');
        $payload = $this->payload($s);
        $payload['reviews'][$s['item']->id]['rating'] = 6;
        $payload['rider_tags'] = ['Not a supported tag'];
        $this->post(route('orders.feedback.store', $s['part']), $payload)
            ->assertSessionHasErrors(['reviews.'.$s['item']->id.'.rating', 'rider_tags.0']);
        $this->assertDatabaseCount('product_reviews', 0);
        $this->assertDatabaseCount('shipment_feedback', 0);
    }

    public function test_photos_and_video_are_saved_and_duplicate_posts_do_not_add_reviews_or_files(): void
    {
        Storage::fake('local');
        $s = $this->shipment();
        $payload = $this->payload($s);
        $payload['reviews'][$s['item']->id]['media'] = [
            UploadedFile::fake()->image('review.jpg'),
            UploadedFile::fake()->create('review.mp4', 100, 'video/mp4'),
        ];
        $this->actingAs($s['buyer'])->post(route('orders.feedback.store', $s['part']), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_review_media', 2);
        foreach (ProductReviewMedia::all() as $media) Storage::disk('local')->assertExists($media->path);

        $payload = $this->payload($s);
        $payload['reviews'][$s['item']->id]['rating'] = 1;
        $payload['reviews'][$s['item']->id]['media'] = [UploadedFile::fake()->image('another.jpg')];
        $this->post(route('orders.feedback.store', $s['part']), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_reviews', 1);
        $this->assertDatabaseCount('shipment_feedback', 1);
        $this->assertDatabaseCount('rider_delivery_ratings', 1);
        $this->assertDatabaseCount('product_review_media', 2);
        $this->assertSame(5, ProductReview::query()->sole()->rating);
        $this->assertCount(2, Storage::disk('local')->allFiles('product-review-media'));
    }

    public function test_unsupported_attachments_and_more_than_three_photos_are_rejected(): void
    {
        Storage::fake('local');
        $s = $this->shipment();
        $payload = $this->payload($s);
        $payload['reviews'][$s['item']->id]['media'] = [UploadedFile::fake()->create('document.pdf', 10, 'application/pdf')];
        $this->actingAs($s['buyer'])->post(route('orders.feedback.store', $s['part']), $payload)
            ->assertSessionHasErrors('reviews.'.$s['item']->id.'.media.0');
        $payload['reviews'][$s['item']->id]['media'] = array_map(fn ($n) => UploadedFile::fake()->image('photo'.$n.'.jpg'), range(1, 4));
        $this->post(route('orders.feedback.store', $s['part']), $payload)->assertSessionHasErrors('reviews.'.$s['item']->id.'.media');
        $this->assertDatabaseCount('product_reviews', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('product-review-media'));
    }

    public function test_existing_rider_rating_is_preserved_when_product_reviews_are_added_later(): void
    {
        $s = $this->shipment();
        RiderDeliveryRating::query()->create([
            'fulfillment_id' => $s['part']->id, 'buyer_id' => $s['buyer']->id,
            'rider_profile_id' => $s['rider']->id, 'rating' => 3, 'feedback' => 'Earlier delivery review',
        ]);
        $payload = $this->payload($s);
        unset($payload['rider_rating'], $payload['rider_feedback'], $payload['rider_tags']);
        $this->actingAs($s['buyer'])->post(route('orders.feedback.store', $s['part']), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_reviews', 1);
        $this->assertDatabaseHas('rider_delivery_ratings', ['rating' => 3, 'feedback' => 'Earlier delivery review']);
    }

    public function test_public_reviews_respect_anonymity_and_media_access_tracks_product_visibility(): void
    {
        Storage::fake('local');
        $s = $this->shipment();
        $payload = $this->payload($s);
        $payload['reviews'][$s['item']->id]['media'] = [UploadedFile::fake()->image('review.jpg')];
        $this->actingAs($s['buyer'])->post(route('orders.feedback.store', $s['part']), $payload)->assertSessionHasNoErrors();
        $this->post(route('logout'));
        $this->get(route('products.show', $s['product']))->assertOk()->assertSee('Anonymous buyer')
            ->assertSee('Exactly as described')->assertDontSee('Private Reviewer Name');
        $media = ProductReviewMedia::query()->sole();
        $this->get(route('reviews.media', $media))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $s['product']->update(['is_active' => false]);
        $this->get(route('reviews.media', $media))->assertNotFound();
        $this->actingAs($s['buyer'])->get(route('reviews.media', $media))->assertOk();
    }

    public function test_admin_can_review_products_and_service_scores_without_exposing_review_pages_to_other_roles(): void
    {
        $s = $this->shipment();
        $this->actingAs($s['buyer'])->post(route('orders.feedback.store', $s['part']), $this->payload($s))->assertSessionHasNoErrors();
        $this->get(route('admin.reviews.index'))->assertForbidden();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->get(route('admin.reviews.index'))->assertOk()->assertSee('Private Reviewer Name')->assertSee('Publicly anonymous');
        $this->get(route('admin.reviews.index', ['type' => 'service']))->assertOk()->assertSee('Seller service');
        $this->actingAs($s['rider']->user)->get(route('rider.dashboard'))->assertOk()->assertSee('Friendly');
    }
}
