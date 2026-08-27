<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder is idempotent: containers re-run `migrate --seed` on every
        // restart since the DB volume persists. firstOrCreate/firstOrCreateMany
        // avoid duplicate-key crashes on restart instead of failing the boot.
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
            ['slug' => 'tokyo-surplus'],
            [
                'user_id' => $seller->id,
                'name' => 'Tokyo Surplus Co.',
                'tagline' => 'Japan auction lots, priced for PH.',
                'city' => 'Osaka / Manila',
                'is_active' => true,
            ]
        );

        if ($shop->products()->count() === 0) {
            Product::factory()
                ->count(24)
                ->create([
                    'shop_id' => $shop->id,
                    'category_id' => $categories->random()->id,
                ]);
        }

        if ($buyer->addresses()->count() === 0) {
            $buyer->addresses()->create([
                'label' => 'Home',
                'line1' => '123 Sample Street',
                'city' => 'Quezon City',
                'region' => 'NCR',
                'postal_code' => '1100',
                'phone' => '09171234567',
                'is_default' => true,
            ]);
        }
    }
}
