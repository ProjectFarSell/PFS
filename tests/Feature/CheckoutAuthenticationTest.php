<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_checkout(): void
    {
        $this->get('/checkout')->assertRedirect(route('login'));
    }

    public function test_guest_cannot_submit_checkout(): void
    {
        $this->post('/checkout')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_checkout(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/checkout')
            ->assertRedirect(route('cart.index'));
    }
}
