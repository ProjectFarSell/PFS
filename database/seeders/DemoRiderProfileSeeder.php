<?php

namespace Database\Seeders;

use App\Enums\RiderStatus;
use App\Enums\UserRole;
use App\Models\RiderProfile;
use App\Models\User;
use App\Support\DevelopmentData;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoRiderProfileSeeder extends Seeder
{
    public function run(): void
    {
        DevelopmentData::assertAllowed();
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('The synthetic demo rider profile is only available in local/testing environments.');
        }

        $rider = User::query()->where('email', 'rider@farsell.test')->first();
        if (! $rider || $rider->role !== UserRole::Rider || $rider->suspended_at) {
            $this->command?->warn('Demo rider profile skipped: rider@farsell.test must be an existing, unsuspended rider account. Existing account settings were preserved.');

            return;
        }

        // Synthetic local fixture only. Real applicants still need documents and admin review.
        // Preserve pending/rejected/suspended profiles, documents and availability on reruns.
        RiderProfile::query()->firstOrCreate(['user_id' => $rider->id], [
            'status' => RiderStatus::Approved,
            'vehicle_type' => 'motorcycle',
            'license_no' => 'DEMO-RIDER-ONLY',
            'city' => 'Quezon City',
            'bio' => 'Synthetic local demo profile for presentation and workflow testing; not a real reviewed rider application.',
        ]);
    }
}
