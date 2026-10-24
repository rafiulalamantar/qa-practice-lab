<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function login(User $user): array
    {
        $token = $this->postJson('/api/tickets/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->json('token');

        return ['Authorization' => 'Bearer '.$token];
    }

    private function bookingPayload(Event $event, int $quantity = 2): array
    {
        return [
            'eventId' => $event->id, 'customerName' => 'Nusrat Jahan', 'customerEmail' => 'nusrat@example.com',
            'customerPhone' => '+880 1712-345678', 'quantity' => $quantity,
        ];
    }

    private function eventPayload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Grid Bootcamp', 'category' => 'Workshop', 'venue' => 'Hall B', 'city' => 'Sylhet',
            'eventDate' => now()->addMonth()->toJSON(), 'price' => 799, 'totalSeats' => 5,
        ];
    }

    public function test_register_login_and_me(): void
    {
        $credentials = ['email' => 'ada@example.com', 'password' => 'secret1'];

        $this->postJson('/api/tickets/auth/register', $credentials)->assertCreated()
            ->assertJsonStructure(['success', 'token', 'user' => ['id', 'email']]);
        $this->postJson('/api/tickets/auth/register', $credentials)->assertStatus(400)
            ->assertExactJson(['success' => false, 'error' => 'Email already registered']);

        $token = $this->postJson('/api/tickets/auth/login', $credentials)->assertOk()->json('token');
        $this->getJson('/api/tickets/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()->assertJsonPath('user.email', 'ada@example.com');
    }

    public function test_auth_errors_use_the_tickets_error_shape(): void
    {
        $this->postJson('/api/tickets/auth/register', ['email' => 'nope', 'password' => '123'])
            ->assertStatus(400)
            ->assertJson(['success' => false, 'error' => 'Validation failed'])
            ->assertJsonPath('details.0', ['field' => 'email', 'message' => 'A valid email is required']);

        $this->postJson('/api/tickets/auth/login', ['email' => 'ghost@example.com', 'password' => 'whatever'])
            ->assertNotFound()->assertJson(['error' => 'User not found']);
        $this->postJson('/api/tickets/auth/login', ['email' => 'demo@practice.test', 'password' => 'wrong-one'])
            ->assertStatus(400)->assertJson(['error' => 'Invalid credentials']);

        $this->getJson('/api/tickets/events')->assertUnauthorized()->assertExactJson(['success' => false, 'error' => 'Unauthorized']);
    }

    public function test_events_list_filters_and_paginates(): void
    {
        $headers = $this->login(User::factory()->create());

        $this->getJson('/api/tickets/events?limit=4&page=2', $headers)->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('pagination', ['total' => 6, 'page' => 2, 'limit' => 4, 'totalPages' => 2]);

        $this->getJson('/api/tickets/events?category=Conference', $headers)->assertJsonCount(2, 'data');
        $this->getJson('/api/tickets/events?city=chattogram', $headers)->assertJsonPath('data.0.title', 'Monsoon Beats Live');
        $this->getJson('/api/tickets/events?search=playwright', $headers)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.isStatic', true)->assertJsonPath('data.0.availableSeats', 8);
        $this->getJson('/api/tickets/events?category=Party&limit=500', $headers)->assertStatus(400)
            ->assertJsonPath('details.*.field', ['category', 'limit']);
    }

    public function test_custom_events_are_private_and_featured_events_read_only(): void
    {
        $owner = User::factory()->create();
        $mine = Event::factory()->for($owner)->create();
        $featured = Event::whereNull('user_id')->first();

        $other = $this->login(User::factory()->create());
        $this->getJson("/api/tickets/events/{$mine->id}", $other)->assertNotFound()
            ->assertJson(['error' => "Event with id {$mine->id} not found"]);
        $this->getJson('/api/tickets/events', $other)->assertJsonPath('pagination.total', 6);

        $this->putJson("/api/tickets/events/{$featured->id}", $this->eventPayload(), $other)->assertForbidden();
        // Ownership is checked before the body, so even an empty body gets 403.
        $this->putJson("/api/tickets/events/{$featured->id}", [], $other)->assertForbidden();
        $this->deleteJson("/api/tickets/events/{$featured->id}", [], $other)->assertForbidden();

        $this->app['auth']->forgetGuards();
        $headers = $this->login($owner);
        $this->putJson("/api/tickets/events/{$mine->id}", $this->eventPayload(['title' => 'Renamed']), $headers)
            ->assertOk()->assertJsonPath('data.title', 'Renamed');
        $this->deleteJson("/api/tickets/events/{$mine->id}", [], $headers)->assertOk();
        $this->assertModelMissing($mine);
    }

    public function test_creating_an_event_validates_and_replaces_the_oldest_past_the_cap(): void
    {
        $user = User::factory()->create();
        $headers = $this->login($user);

        $this->postJson('/api/tickets/events', $this->eventPayload(['eventDate' => now()->subDay()->toJSON(), 'totalSeats' => 0]), $headers)
            ->assertStatus(400)->assertJsonPath('details.*.field', ['eventDate', 'totalSeats']);

        $oldest = Event::factory()->for($user)->create(['created_at' => now()->subDay()]);
        Event::factory()->for($user)->count(5)->create();

        $this->postJson('/api/tickets/events', $this->eventPayload(), $headers)->assertCreated()
            ->assertJsonPath('data.availableSeats', 5)->assertJsonPath('data.isStatic', false)
            ->assertJsonPath('message', 'Event created successfully');

        $this->assertModelMissing($oldest);
        $this->assertSame(6, $user->events()->count());
    }

    public function test_booking_checks_seats_and_cancelling_releases_them(): void
    {
        $user = User::factory()->create();
        $headers = $this->login($user);
        $event = Event::where('title', 'Playwright in a Day')->first();

        $booking = $this->postJson('/api/tickets/bookings', $this->bookingPayload($event, 5), $headers)->assertCreated()
            ->assertJsonPath('data.totalPrice', 2495)->assertJsonPath('data.event.availableSeats', 3)
            ->assertJsonPath('message', 'Booking confirmed!')->json('data');
        $this->assertMatchesRegularExpression('/^TKT-[A-Z0-9]{6}$/', $booking['bookingRef']);

        $this->postJson('/api/tickets/bookings', $this->bookingPayload($event, 4), $headers)->assertStatus(400)
            ->assertJson(['error' => 'Only 3 seat(s) available']);

        $this->getJson("/api/tickets/bookings/ref/{$booking['bookingRef']}", $headers)->assertOk()->assertJsonPath('data.id', $booking['id']);
        $this->deleteJson("/api/tickets/bookings/{$booking['id']}", [], $headers)->assertOk()->assertJson(['message' => 'Booking cancelled']);
        $this->getJson("/api/tickets/events/{$event->id}", $headers)->assertJsonPath('data.availableSeats', 8);
    }

    public function test_seats_and_bookings_are_isolated_per_user(): void
    {
        $event = Event::where('title', 'Playwright in a Day')->first();
        $theirs = Booking::factory()->create(['event_id' => $event->id, 'quantity' => 8]);

        $headers = $this->login(User::factory()->create());
        $this->getJson("/api/tickets/events/{$event->id}", $headers)->assertJsonPath('data.availableSeats', 8);
        $this->getJson("/api/tickets/bookings/{$theirs->id}", $headers)->assertNotFound();
        $this->deleteJson("/api/tickets/bookings/{$theirs->id}", [], $headers)->assertNotFound();
        $this->getJson('/api/tickets/bookings', $headers)->assertJsonPath('pagination.total', 0);
        $this->assertModelExists($theirs);
    }

    public function test_booking_validation_and_unknown_event(): void
    {
        $headers = $this->login(User::factory()->create());

        $this->postJson('/api/tickets/bookings', ['eventId' => 1, 'customerName' => 'A', 'customerEmail' => 'x', 'customerPhone' => '123', 'quantity' => 11], $headers)
            ->assertStatus(400)->assertJsonPath('details.*.field', ['customerName', 'customerEmail', 'customerPhone', 'quantity']);

        $this->postJson('/api/tickets/bookings', ['eventId' => 9999] + $this->bookingPayload(Event::first()), $headers)
            ->assertNotFound()->assertJson(['error' => 'Event with id 9999 not found']);
    }

    public function test_bookings_cap_replaces_the_oldest_and_clear_removes_all(): void
    {
        $user = User::factory()->create();
        $headers = $this->login($user);
        $event = Event::where('title', 'Festival of Lanterns')->first();
        $oldest = Booking::factory()->for($user)->create(['event_id' => $event->id, 'created_at' => now()->subDay()]);
        Booking::factory()->for($user)->count(8)->create(['event_id' => $event->id]);

        $this->postJson('/api/tickets/bookings', $this->bookingPayload($event, 1), $headers)->assertCreated();
        $this->assertModelMissing($oldest);

        $this->getJson("/api/tickets/bookings?eventId={$event->id}&limit=5", $headers)
            ->assertJsonCount(5, 'data')->assertJsonPath('pagination.total', 9);

        $this->deleteJson('/api/tickets/bookings', [], $headers)->assertOk()->assertJson(['deleted' => 9]);
    }

    public function test_public_meta_endpoints(): void
    {
        $this->getJson('/api/tickets/health')->assertOk()->assertJson(['status' => 'ok', 'dbStatus' => 'connected']);
        $this->getJson('/api/tickets/config')->assertOk()->assertJson(['maxCustomEvents' => 6, 'maxBookings' => 9]);
        $this->getJson('/api/tickets/openapi.json')->assertOk()->assertJsonPath('info.title', 'TicketTrail API')
            ->assertJsonStructure(['paths' => ['/bookings/{id}', '/events']]);
    }
}
