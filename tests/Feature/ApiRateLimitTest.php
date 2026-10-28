<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "api" limiter allows 300 requests a minute, per user when signed in
 * and per IP otherwise.
 */
class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_gets_429_after_300_requests_a_minute_from_one_ip(): void
    {
        $this->freezeTime();
        $guest = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7']);
        for ($request = 0; $request < 300; $request++) {
            $guest->getJson('/api/tickets/health')->assertOk();
        }

        $response = $guest->getJson('/api/tickets/health');

        $response->assertTooManyRequests()->assertExactJson(['success' => false, 'error' => 'Too many requests']);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->getJson('/api/tickets/health')->assertOk();
    }

    public function test_signed_in_learners_on_one_ip_each_get_their_own_300_requests(): void
    {
        $this->freezeTime();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7']);
        $busy = ['Authorization' => 'Bearer '.User::factory()->create()->createToken('client')->plainTextToken];
        $neighbourUser = User::factory()->create();
        $neighbourId = $neighbourUser->id;
        $neighbour = ['Authorization' => 'Bearer '.$neighbourUser->createToken('client')->plainTextToken];
        for ($request = 0; $request < 300; $request++) {
            $this->getJson('/api/tickets/auth/me', $busy)->assertOk();
        }

        $response = $this->getJson('/api/tickets/auth/me', $busy);

        $response->assertTooManyRequests();
        // Test requests share one app, so drop the busy learner cached on the guard.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/tickets/auth/me', $neighbour)->assertOk()->assertJsonPath('user.userId', $neighbourId);
    }
}
