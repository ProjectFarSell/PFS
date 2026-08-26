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
        User::query()->create([
            'name' => 'FarSell Admin',
            'email' => 'admin@farsell.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
        ]);

        $buyer = User::factory()->create([
            'name' => 'Guest Buyer',
            'email' => 'buyer@farsell.test',
        ]);

        $seller = User::factory()->seller()->create([
            'name' => 'Demo Seller',
            'email' => 'seller@farsell.test',
        ]);

        User::factory()->rider()->create([
            'name' => 'Demo Rider',
            'email' => 'rider@farsell.test',
        ]);

        $categories = collect([
            ['name' => "Women's", 'icon' => 'W', 'sort_order' => 1],
            ['name' => "Men's", 'icon' => 'M', 'sort_order' => 2],
            ['name' => 'Kids', 'icon' => 'K', 'sort_order' => 3],
            ['name' => 'Home', 'icon' => 'H', 'sort_order' => 4],
            ['name' => 'Beauty', 'icon' => 'B', 'sort_order' => 5],
            ['name' => 'Sports', 'icon' => 'S', 'sort_order' => 6],
            ['name' => 'Gadgets', 'icon' => 'G', 'sort_order' => 7],
            ['name' => 'Surplus', 'icon' => 'X', 'sort_order' => 8],
        ])->map(fn (array $row) => Category::query()->create([
            ...$row,
            'slug' => \Illuminate\Support\Str::slug($row['name']),
        ]));

        $shop = Shop::query()->create([
            'user_id' => $seller->id,
            'name' => 'Tokyo Surplus Co.',
            'slug' => 'tokyo-surplus',
            'tagline' => 'Japan auction lots, priced for PH.',
            'city' => 'Osaka / Manila',
            'is_active' => true,
        ]);

        Product::factory()
            ->count(24)
            ->create([
                'shop_id' => $shop->id,
                'category_id' => $categories->random()->id,
            ]);

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
