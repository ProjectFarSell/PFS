<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Product;
use App\Models\PsgcBarangay;
use App\Models\PsgcCityMunicipality;
use App\Models\PsgcProvince;
use App\Models\PsgcRegion;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_own_address(): void
    {
        $user = User::factory()->create();
        [$region, $province, $city, $barangay] = $this->location();

        $response = $this->actingAs($user)->post('/account/addresses', [
            'line1' => '123 Test Street',
            'psgc_region_id' => $region->id,
            'psgc_province_id' => $province->id,
            'psgc_city_municipality_id' => $city->id,
            'psgc_barangay_id' => $barangay->id,
        ]);

        $response->assertRedirect(route('account.addresses.index'));
        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'line1' => '123 Test Street',
        ]);
    }

    public function test_user_cannot_edit_another_users_address(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $address = Address::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->get("/account/addresses/{$address->id}/edit");

        $response->assertForbidden();
    }

    public function test_saving_an_address_resumes_checkout_only_when_requested_and_cart_is_present(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        [$region, $province, $city, $barangay] = $this->location();
        $this->actingAs($user)->withSession([Cart::SESSION_KEY => [$product->id => 1]])
            ->get(route('checkout.create'))->assertRedirect(route('account.addresses.create'));
        $this->post(route('account.addresses.store'), [
            'line1' => '123 Test Street',
            'psgc_region_id' => $region->id, 'psgc_province_id' => $province->id,
            'psgc_city_municipality_id' => $city->id, 'psgc_barangay_id' => $barangay->id,
        ])->assertRedirect(route('checkout.create'))->assertSessionMissing('checkout.needs_address');
        $this->get(route('checkout.create'))->assertOk()->assertSee('123 Test Street')->assertSee('Place order');
        $this->assertSame([$product->id => 1], session(Cart::SESSION_KEY));
    }

    public function test_user_cannot_delete_another_users_address(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $address = Address::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->delete("/account/addresses/{$address->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('addresses', ['id' => $address->id]);
    }

    public function test_guest_cannot_access_address_routes(): void
    {
        $response = $this->get('/account/addresses');

        $response->assertRedirect(route('login'));
    }

    public function test_location_endpoints_return_each_level_for_the_selected_parent(): void
    {
        [$region, $province, $city, $barangay] = $this->location();
        $this->actingAs(User::factory()->create());
        $this->getJson(route('account.addresses.locations.provinces', ['region_id' => $region->id]))
            ->assertOk()->assertExactJson([['id' => $province->id, 'name' => $province->name]]);
        $this->getJson(route('account.addresses.locations.cities-municipalities', ['province_id' => $province->id]))
            ->assertOk()->assertExactJson([['id' => $city->id, 'name' => $city->name]]);
        $this->getJson(route('account.addresses.locations.barangays', ['city_municipality_id' => $city->id]))
            ->assertOk()->assertExactJson([['id' => $barangay->id, 'name' => $barangay->name]]);
        $this->getJson(route('account.addresses.locations.provinces', ['region_id' => 99999]))
            ->assertUnprocessable();
        $this->get(route('account.addresses.create'))->assertOk()
            ->assertSee('data-address-location-form', false)
            ->assertSee('/js/address-location.js', false);
    }

    /** @return array{PsgcRegion, PsgcProvince, PsgcCityMunicipality, PsgcBarangay} */
    private function location(): array
    {
        $region = PsgcRegion::query()->create(['psgc_code' => '1300000000', 'region_code' => '13', 'name' => 'NCR']);
        $province = PsgcProvince::query()->create(['psgc_region_id' => $region->id, 'psgc_code' => '1381700000', 'province_code' => '817', 'name' => 'Pateros']);
        $city = PsgcCityMunicipality::query()->create(['psgc_province_id' => $province->id, 'psgc_code' => '1381701000', 'city_municipality_code' => '81701', 'name' => 'Pateros']);
        $barangay = PsgcBarangay::query()->create(['psgc_city_municipality_id' => $city->id, 'psgc_code' => '1381701001', 'barangay_code' => '81701001', 'name' => 'Aguho']);

        return [$region, $province, $city, $barangay];
    }
}
