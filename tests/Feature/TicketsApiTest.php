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

    public function test_events_list_filters_by_price_date_and_seats_left_and_sorts(): void
    {
        $user = User::factory()->create();
        $headers = $this->login($user);
        $cheap = Event::factory()->for($user)->create(['title' => 'Cheap Show', 'city' => 'Rangpur', 'price' => 100, 'event_date' => '2030-03-10 18:00:00']);
        $mid = Event::factory()->for($user)->create(['title' => 'Mid Show', 'city' => 'Rangpur', 'price' => 500, 'event_date' => '2030-03-20 18:00:00', 'total_seats' => 2]);
        $dear = Event::factory()->for($user)->create(['title' => 'Dear Show', 'city' => 'Rangpur', 'price' => 900, 'event_date' => '2030-04-05 18:00:00']);
        Booking::factory()->for($user)->create(['event_id' => $mid->id, 'quantity' => 2]);

        $titles = fn (string $query) => $this->getJson("/api/tickets/events?city=Rangpur&{$query}", $headers)->assertOk()->json('data.*.title');

        $this->assertSame(['Cheap Show', 'Mid Show'], $titles('maxPrice=500'));
        $this->assertSame(['Mid Show', 'Dear Show'], $titles('minPrice=200'));
        $this->assertSame(['Mid Show', 'Dear Show'], $titles('from=2030-03-20&to=2030-04-05'));
        $this->assertSame(['Cheap Show', 'Dear Show'], $titles('availableOnly=true'));
        $this->assertSame(['Dear Show', 'Mid Show', 'Cheap Show'], $titles('sort=-price'));
        $this->assertSame(['Dear Show', 'Mid Show', 'Cheap Show'], $titles('sort=-date'));
        $this->assertSame(['Cheap Show', 'Dear Show', 'Mid Show'], $titles('sort=title'));
    }

    public function test_events_date_filter_uses_the_callers_local_day_when_given_an_offset(): void
    {
        $user = User::factory()->create();
        $headers = $this->login($user);
        // 19:00 UTC on 6 March is 01:00 on 7 March in Dhaka (UTC+6).
        Event::factory()->for($user)->create(['title' => 'Late Gig', 'city' => 'Rangpur', 'event_date' => '2030-03-06 19:00:00']);

        $titles = fn (string $query) => $this->getJson("/api/tickets/events?city=Rangpur&{$query}", $headers)->assertOk()->json('data.*.title');

        $this->assertSame(['Late Gig'], $titles('from='.urlencode('2030-03-07T00:00:00+06:00').'&to='.urlencode('2030-03-07T23:59:59+06:00')));
        $this->assertSame([], $titles('from='.urlencode('2030-03-06T00:00:00+06:00').'&to='.urlencode('2030-03-06T23:59:59+06:00')));
        $this->assertSame(['Late Gig'], $titles('from=2030-03-06&to=2030-03-06'));
    }

    public function test_events_list_rejects_contradictory_filters_with_400(): void
    {
        $headers = $this->login(User::factory()->create());

        $this->getJson('/api/tickets/events?minPrice=500&maxPrice=100&from=2030-05-01&to=2030-04-01&availableOnly=maybe&sort=random', $headers)
            ->assertStatus(400)
            ->assertJsonPath('details', [
                ['field' => 'maxPrice', 'message' => 'maxPrice must be greater than or equal to minPrice'],
                ['field' => 'to', 'message' => 'to must be on or after from'],
                ['field' => 'availableOnly', 'message' => 'availableOnly must be true or false'],
                ['field' => 'sort', 'message' => 'sort must be one of: date, -date, price, -price, title'],
            ]);
    }

    public function test_events_list_names_the_parameter_in_malformed_filter_errors(): void
    {
        $headers = $this->login(User::factory()->create());

        $this->getJson('/api/tickets/events?minPrice=cheap&maxPrice=1e309&from=someday&to=later', $headers)
            ->assertStatus(400)
            ->assertJsonPath('details', [
                ['field' => 'minPrice', 'message' => 'minPrice must be a number'],
                ['field' => 'maxPrice', 'message' => 'maxPrice must be 1000000 or less'],
                ['field' => 'from', 'message' => 'from must be a valid date, e.g. 2027-03-07'],
                ['field' => 'to', 'message' => 'to must be a valid date, e.g. 2027-03-07'],
            ]);
        $this->getJson('/api/tickets/events?minPrice=-1', $headers)->assertStatus(400)
            ->assertJsonPath('details', [['field' => 'minPrice', 'message' => 'minPrice must be 0 or more']]);
    }

    public function test_bulk_cancel_removes_own_bookings_and_reports_ids_it_could_not_cancel(): void
    {
        $user = User::factory()->create();
        $headers = $this->login($user);
        [$first, $second, $kept] = Booking::factory()->for($user)->count(3)->create()->all();
        $theirs = Booking::factory()->create();

        $this->postJson('/api/tickets/bookings/bulk-cancel', ['ids' => [$first->id, $second->id, $theirs->id, 99999]], $headers)
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => '2 booking(s) cancelled', 'cancelled' => 2, 'notFound' => [$theirs->id, 99999]]);

        $this->assertModelMissing($first);
        $this->assertModelMissing($second);
        $this->assertModelExists($kept);
        $this->assertModelExists($theirs);
    }

    public function test_bulk_cancel_requires_a_list_of_unique_integer_ids_with_400(): void
    {
        $headers = $this->login(User::factory()->create());

        $this->postJson('/api/tickets/bookings/bulk-cancel', [], $headers)->assertStatus(400)
            ->assertJsonPath('details', [['field' => 'ids', 'message' => 'ids must be a non-empty array of booking ids']]);
        $this->postJson('/api/tickets/bookings/bulk-cancel', ['ids' => ['abc', 5, 5]], $headers)->assertStatus(400)
            ->assertJsonPath('details.*.message', ['Each booking id must be an integer', 'Booking ids must be unique', 'Booking ids must be unique']);
        $this->postJson('/api/tickets/bookings/bulk-cancel', ['ids' => [0, -3]], $headers)->assertStatus(400)
            ->assertJsonPath('details.*.message', ['Each booking id must be a positive integer', 'Each booking id must be a positive integer']);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/tickets/bookings/bulk-cancel', ['ids' => [1]])->assertUnauthorized();
    }

    public function test_bulk_cancel_answers_405_to_other_methods_while_booking_ids_still_resolve(): void
    {
        $user = User::factory()->create();
        $headers = $this->login($user);
        $booking = Booking::factory()->for($user)->create();

        $this->getJson('/api/tickets/bookings/bulk-cancel', $headers)->assertStatus(405);
        $this->deleteJson('/api/tickets/bookings/bulk-cancel', [], $headers)->assertStatus(405);
        $this->getJson('/api/tickets/bookings/'.$booking->id, $headers)->assertOk()->assertJsonPath('data.id', $booking->id);
        $this->getJson('/api/tickets/bookings/abc', $headers)->assertNotFound();
    }

    public function test_public_meta_endpoints(): void
    {
        $this->getJson('/api/tickets/health')->assertOk()->assertJson(['status' => 'ok', 'dbStatus' => 'connected']);
        $this->getJson('/api/tickets/config')->assertOk()->assertJson(['maxCustomEvents' => 6, 'maxBookings' => 9]);
        $this->getJson('/api/tickets/openapi.json')->assertOk()->assertJsonPath('info.title', 'TicketTrail API')
            ->assertJsonStructure(['paths' => ['/bookings/{id}', '/bookings/bulk-cancel', '/events']]);
    }
}
