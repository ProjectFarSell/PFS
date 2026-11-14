<?php

namespace Database\Seeders;

use App\Services\Mobile\MobileFixtures;
use Illuminate\Database\Seeder;

class MobileApiSeeder extends Seeder
{
    public function run(): void
    {
        app(MobileFixtures::class)->seed();
    }
}
