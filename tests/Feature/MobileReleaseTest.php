<?php

namespace Tests\Feature;

use App\Models\Fulfillment;
use App\Services\Mobile\MobileFixtures;
use App\Services\Mobile\MobileMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixtures_are_repeatable_guarded_and_do_not_reset_existing_records(): void
    {
        $result = app(MobileFixtures::class)->seed();
        $this->assertSame(12, $result['shipment_statuses']);
        app(MobileFixtures::class)->seed();
        $this->assertDatabaseCount('orders', 12);
        $this->artisan('mobile:fixtures', ['--reset' => true])->assertFailed();
        $this->assertDatabaseCount('orders', 12);
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        app(MobileFixtures::class)->seed();
    }

    public function test_association_files_require_real_configuration_and_support_is_public(): void
    {
        $this->get('/support')->assertOk()->assertSee('Request account deletion');
        $this->get('/.well-known/assetlinks.json')->assertStatus(503);
        config(['mobile.android_package' => 'com.example.farsell', 'mobile.android_fingerprints' => [implode(':', array_fill(0, 32, 'AB'))], 'mobile.ios_app_ids' => ['ABCDEFGHIJ.com.example.farsell']]);
        $this->getJson('/.well-known/assetlinks.json')->assertOk()->assertJsonPath('0.target.package_name', 'com.example.farsell')->assertHeader('Cache-Control', 'max-age=3600, public');
        $this->getJson('/.well-known/apple-app-site-association')->assertOk()->assertJsonPath('applinks.details.0.appID', 'ABCDEFGHIJ.com.example.farsell');
    }

    public function test_pruning_never_deletes_a_referenced_proof_without_retention_opt_in(): void
    {
        app(MobileFixtures::class)->seed();
        Storage::fake('local');
        Storage::disk('local')->put('delivery-proof/owned.jpg', 'private proof');
        $part = Fulfillment::where('status', 'completed')->firstOrFail();
        $part->update(['delivery_photo_path' => 'delivery-proof/owned.jpg']);
        config(['mobile.proof_retention_days' => 0]);
        app(MobileMaintenance::class)->prune();
        Storage::disk('local')->assertExists('delivery-proof/owned.jpg');
    }
}
