<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\PsgcBarangay;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PsgcGeographySeeder::class);

        $this->call(DemoAccountsSeeder::class);

        $buyer = User::query()->where('email', 'buyer@farsell.test')->firstOrFail();
        $seller = User::query()->where('email', 'seller@farsell.test')->firstOrFail();

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

        $this->call(SellerDemoProductsSeeder::class);

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
