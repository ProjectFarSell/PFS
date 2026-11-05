<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\RequestTelemetry;
use App\Models\User;
use App\Services\Operations\OperationalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OperationalReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_requires_the_scheduler_and_actual_worker_when_enabled(): void
    {
        config(['operations.require_heartbeats' => true, 'notifications.connection' => 'database']);
        $this->getJson('/ready')->assertServiceUnavailable()->assertExactJson(['status' => 'unavailable']);
        $this->artisan('ops:heartbeat')->assertSuccessful();
        $status = app(OperationalStatus::class)->inspect();
        $this->assertTrue($status['scheduler']);
        $this->assertFalse($status['notification_worker']);
        $this->artisan('notifications:work', ['--once' => true])->assertSuccessful();
        $this->getJson('/ready')->assertOk()->assertExactJson(['status' => 'ready']);
        $this->travel(181)->seconds();
        $this->getJson('/ready')->assertServiceUnavailable();
    }

    public function test_failed_jobs_degrade_readiness_without_exposing_exception_contents(): void
    {
        DB::table('failed_jobs')->insert(['uuid' => 'dummy-uuid', 'connection' => 'database', 'queue' => 'notifications', 'payload' => '{}', 'exception' => 'SECRET-DUMMY-ERROR']);
        $this->getJson('/ready')->assertServiceUnavailable()->assertExactJson(['status' => 'unavailable'])->assertDontSee('SECRET-DUMMY-ERROR');
    }

    public function test_operations_page_is_admin_only_and_does_not_render_secrets(): void
    {
        $this->get(route('admin.operations'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.operations'))->assertForbidden();
        config(['operations.backup_password' => 'private-backup-password-do-not-display']);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->get(route('admin.operations'))->assertOk()->assertSee('Technical readiness')->assertDontSee('private-backup-password-do-not-display');
    }

    public function test_request_diagnostics_do_not_log_body_query_password_pin_or_address(): void
    {
        config(['release.slow_request_ms' => 0]);
        $request = Request::create('/checkout?token=PRIVATE-TOKEN', 'POST', ['password' => 'PRIVATE-PASSWORD', 'delivery_pin' => '123456', 'address' => 'PRIVATE-ADDRESS']);
        $sink = \Mockery::mock();
        Log::shouldReceive('channel')->once()->with('operations')->andReturn($sink);
        $sink->shouldReceive('log')->once()->with('warning', 'marketplace_request', \Mockery::on(function (array $context): bool {
            $this->assertSame(['request_id', 'release', 'route', 'method', 'status', 'duration_ms'], array_keys($context));
            $this->assertStringNotContainsString('PRIVATE', json_encode($context));
            $this->assertStringNotContainsString('123456', json_encode($context));

            return true;
        }));
        app(RequestTelemetry::class)->handle($request, fn () => response('OK'));
    }

    public function test_admin_provisioning_has_no_default_password_and_hashes_the_hidden_input(): void
    {
        $this->artisan('ops:create-admin', ['email' => 'operator@example.test', '--name' => 'Dummy Operator'])
            ->expectsQuestion('New admin password', 'Dummy-Operator-Password-123!')
            ->expectsQuestion('Confirm admin password', 'Dummy-Operator-Password-123!')
            ->assertSuccessful();
        $user = User::where('email', 'operator@example.test')->sole();
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue(Hash::check('Dummy-Operator-Password-123!', $user->password));
        $this->assertNull($user->privacy_accepted_at);
    }
}
