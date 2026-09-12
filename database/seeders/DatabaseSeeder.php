<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\PsgcBarangay;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PsgcGeographySeeder::class);

        // Idempotent: containers/local runs may re-seed on restart, so use
        // firstOrCreate to avoid duplicate-key crashes instead of failing.
        User::query()->firstOrCreate(
            ['email' => 'admin@farsell.test'],
            [
                'name' => 'FarSell Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
            ]
        );

        $buyer = User::query()->firstOrCreate(
            ['email' => 'buyer@farsell.test'],
            User::factory()->raw(['name' => 'Guest Buyer'])
        );

        $seller = User::query()->firstOrCreate(
            ['email' => 'seller@farsell.test'],
            User::factory()->seller()->raw(['name' => 'Demo Seller'])
        );

        User::query()->firstOrCreate(
            ['email' => 'rider@farsell.test'],
            User::factory()->rider()->raw(['name' => 'Demo Rider'])
        );

        $categories = collect([
            ['name' => "Women's", 'icon' => 'W', 'sort_order' => 1],
            ['name' => "Men's", 'icon' => 'M', 'sort_order' => 2],
            ['name' => 'Kids', 'icon' => 'K', 'sort_order' => 3],
            ['name' => 'Home', 'icon' => 'H', 'sort_order' => 4],
            ['name' => 'Beauty', 'icon' => 'B', 'sort_order' => 5],
            ['name' => 'Sports', 'icon' => 'S', 'sort_order' => 6],
            ['name' => 'Gadgets', 'icon' => 'G', 'sort_order' => 7],
            ['name' => 'Surplus', 'icon' => 'X', 'sort_order' => 8],
        ])->map(fn (array $row) => Category::query()->firstOrCreate(
            ['slug' => \Illuminate\Support\Str::slug($row['name'])],
            $row
        ));

        $shop = Shop::query()->firstOrCreate(
            ['slug' => 'metro-surplus'],
            [
                'user_id' => $seller->id,
                'name' => 'Metro Surplus Co.',
                'tagline' => 'Auction lots, priced for PH.',
                'city' => 'Quezon City',
                'is_active' => true,
            ]
        );

        if ($shop->products()->count() === 0) {
            $products = Product::factory()
                ->count(24)
                ->create([
                    'shop_id' => $shop->id,
                    'category_id' => $categories->random()->id,
                ]);

            // Give roughly half the products a size-variant spread, and every
            // product at least one gallery image — staff-seeded dev data only.
            $products->each(function (Product $product) {
                if (fake()->boolean(50)) {
                    \App\Models\ProductVariant::factory()
                        ->count(fake()->numberBetween(2, 4))
                        ->create(['product_id' => $product->id]);
                }

                \App\Models\ProductImage::factory()
                    ->count(fake()->numberBetween(1, 3))
                    ->sequence(fn ($sequence) => ['sort_order' => $sequence->index])
                    ->create(['product_id' => $product->id]);
            });
        }

        if ($buyer->addresses()->count() === 0) {
            $barangay = PsgcBarangay::query()
                ->with('cityMunicipality.province.region')
                ->where('name', 'Central')
                ->whereHas('cityMunicipality', fn ($query) => $query->where('name', 'Quezon City'))
                ->first();

            $buyer->addresses()->create([
                'label' => 'Home',
                'line1' => '123 Sample Street',
                'city' => 'Quezon City',
                'region' => 'NCR',
                'psgc_region_id' => $barangay?->cityMunicipality->province->region->id,
                'psgc_province_id' => $barangay?->cityMunicipality->province->id,
                'psgc_city_municipality_id' => $barangay?->cityMunicipality->id,
                'psgc_barangay_id' => $barangay?->id,
                'postal_code' => '1100',
                'phone' => '09171234567',
                'is_default' => true,
            ]);
        }
    }
}
