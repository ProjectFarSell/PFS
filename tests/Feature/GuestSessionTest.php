<?php

namespace Tests\Feature;

use App\Support\GuestSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_guest_session_sets_session_id(): void
    {
        $response = $this->post('/guest');

        $response->assertRedirect();
        $this->assertNotNull(session(GuestSession::KEY));
    }

    public function test_guest_session_active_only_when_unauthenticated(): void
    {
        $this->post('/guest');

        $this->assertTrue(GuestSession::active());
    }

    public function test_guest_session_not_active_for_authenticated_users(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user);
        session([GuestSession::KEY => 'some-uuid']);

        $this->assertFalse(GuestSession::active());
    }
}
