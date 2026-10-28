<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CartItem;
use App\Models\Event;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\AccountPurger;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Security boundaries of the 30-day account purge: who can trigger it,
 * which accounts it may delete, and what a learner can do to dodge it.
 */
class PrunePracticeDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function rejectedCronHeaders(): array
    {
        return [
            'no header' => [[]],
            'wrong secret' => [['Authorization' => 'Bearer wrong']],
            'secret without Bearer' => [['Authorization' => 's3cret']],
            'lowercase scheme' => [['Authorization' => 'bearer s3cret']],
            'secret as prefix' => [['Authorization' => 'Bearer s3cret-and-more']],
            'truncated secret' => [['Authorization' => 'Bearer s3cre']],
        ];
    }

    #[DataProvider('rejectedCronHeaders')]
    public function test_prune_returns_403_and_deletes_nothing_without_the_exact_secret(array $headers): void
    {
        config(['practice.cron_secret' => 's3cret']);
        $expired = User::factory()->create(['created_at' => now()->subDays(40)]);

        $response = $this->getJson('/api/cron/prune', $headers);

        $response->assertForbidden()->assertExactJson(['message' => 'Forbidden']);
        $this->assertModelExists($expired);
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public static function missingCronSecrets(): array
    {
        return [
            'null secret, bare scheme' => [null, 'Bearer '],
            'null secret, no space' => [null, 'Bearer'],
            'empty secret, bare scheme' => ['', 'Bearer '],
        ];
    }

    #[DataProvider('missingCronSecrets')]
    public function test_prune_returns_403_when_no_cron_secret_is_configured(?string $secret, string $header): void
    {
        config(['practice.cron_secret' => $secret]);
        $expired = User::factory()->create(['created_at' => now()->subDays(40)]);

        $response = $this->getJson('/api/cron/prune', ['Authorization' => $header]);

        $response->assertForbidden();
        $this->assertModelExists($expired);
    }

    public function test_purger_does_not_delete_the_demo_account_even_when_given_its_id(): void
    {
        $demo = User::where('email', 'demo@practice.test')->firstOrFail();
        $order = Order::factory()->create(['user_id' => $demo->id]);
        $event = Event::factory()->create(['user_id' => $demo->id]);
        $token = $demo->createToken('client')->accessToken;

        $counts = app(AccountPurger::class)->purge([$demo->id]);

        $this->assertSame(['users' => 0, 'bookings' => 0, 'events' => 0, 'orders' => 0, 'cart_items' => 0, 'tokens' => 0], $counts);
        $this->assertModelExists($demo);
        $this->assertModelExists($order);
        $this->assertModelExists($event);
        $this->assertModelExists($token);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function registrationsWithForgedFields(): array
    {
        $forged = ['is_demo' => true, 'created_at' => '2099-01-01 00:00:00', 'id' => 1];

        return [
            'TicketTrail' => ['/api/tickets/auth/register', $forged + [
                'email' => 'sneaky@example.com', 'password' => 'secret1',
            ]],
            'ShopVerse' => ['/api/ecom/auth/register', $forged + [
                'firstName' => 'Sneaky', 'lastName' => 'Learner', 'userEmail' => 'sneaky@example.com',
                'userMobile' => '9123456789', 'userPassword' => 'Strong@123', 'confirmPassword' => 'Strong@123',
            ]],
        ];
    }

    #[DataProvider('registrationsWithForgedFields')]
    public function test_registration_ignores_forged_is_demo_and_created_at_so_the_account_still_expires(string $url, array $payload): void
    {
        $this->travelTo('2026-01-01 12:00:00');
        $this->postJson($url, $payload)->assertCreated();
        $user = User::where('email', 'sneaky@example.com')->firstOrFail();

        $this->assertFalse($user->is_demo);
        $this->assertSame('2026-01-01 12:00:00', $user->created_at->toDateTimeString());
        $this->assertNotSame(1, $user->id);

        $this->travelTo('2026-02-01 12:00:00');
        $this->artisan('practice:prune')->assertSuccessful();

        $this->assertModelMissing($user);
        $this->assertDatabaseHas('users', ['email' => 'demo@practice.test']);
    }

    public function test_open_session_of_a_purged_account_returns_401(): void
    {
        $expired = User::factory()->create(['created_at' => now()->subDays(40)]);
        $token = $this->postJson('/api/tickets/auth/login', ['email' => $expired->email, 'password' => 'password'])
            ->assertOk()->json('token');

        $this->artisan('practice:prune')->assertSuccessful();

        $this->getJson('/api/tickets/auth/me', ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
        $this->postJson('/api/tickets/auth/login', ['email' => $expired->email, 'password' => 'password'])
            ->assertNotFound()->assertJson(['error' => 'User not found']);
    }

    public function test_purging_an_expired_account_does_not_touch_another_learners_data(): void
    {
        $expired = User::factory()->create(['created_at' => now()->subDays(40)]);
        $active = User::factory()->create(['created_at' => now()->subDays(5)]);
        $featured = Event::whereNull('user_id')->firstOrFail();
        Event::factory()->create(['user_id' => $expired->id]);
        Booking::factory()->create(['user_id' => $expired->id, 'event_id' => $featured->id]);

        $activeEvent = Event::factory()->create(['user_id' => $active->id]);
        $activeBooking = Booking::factory()->create(['user_id' => $active->id, 'event_id' => $featured->id]);
        $activeOrder = Order::factory()->create(['user_id' => $active->id]);
        $activeCartItem = CartItem::create(['user_id' => $active->id, 'product_id' => Product::firstOrFail()->id, 'quantity' => 1]);
        $activeToken = $active->createToken('client')->accessToken;

        $this->artisan('practice:prune')->assertSuccessful();

        $this->assertModelMissing($expired);
        foreach ([$active, $activeEvent, $activeBooking, $activeOrder, $activeCartItem, $activeToken] as $model) {
            $this->assertModelExists($model);
        }
    }
}
