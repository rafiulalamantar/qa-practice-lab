<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcomApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function login(User $user): array
    {
        $token = $this->postJson('/api/ecom/auth/login', ['userEmail' => $user->email, 'userPassword' => 'password'])
            ->assertOk()->json('token');

        return ['Authorization' => $token];
    }

    public function test_register_then_login(): void
    {
        $payload = [
            'firstName' => 'Ada', 'lastName' => 'Lovelace', 'userEmail' => 'ada@example.com',
            'userMobile' => '9123456789', 'occupation' => 'Engineer', 'gender' => 'Female',
            'userPassword' => 'Strong@123', 'confirmPassword' => 'Strong@123',
        ];

        $this->postJson('/api/ecom/auth/register', $payload)->assertCreated()->assertJson(['message' => 'Registered Successfully']);
        $this->postJson('/api/ecom/auth/register', $payload)->assertStatus(400);

        $this->postJson('/api/ecom/auth/login', ['userEmail' => 'ada@example.com', 'userPassword' => 'Strong@123'])
            ->assertOk()->assertJsonStructure(['token', 'userId'])->assertJson(['message' => 'Login Successfully']);
    }

    public function test_register_rejects_weak_and_mismatched_passwords(): void
    {
        $this->postJson('/api/ecom/auth/register', [
            'firstName' => 'A', 'lastName' => 'B', 'userEmail' => 'x@example.com', 'userMobile' => '123',
            'userPassword' => 'weak', 'confirmPassword' => 'other',
        ])->assertUnprocessable()->assertJsonValidationErrors(['userMobile', 'userPassword', 'confirmPassword']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->postJson('/api/ecom/auth/login', ['userEmail' => 'demo@practice.test', 'userPassword' => 'nope'])
            ->assertUnauthorized()->assertJson(['message' => 'Incorrect email or password.']);
    }

    public function test_bare_and_bearer_tokens_are_accepted_and_missing_is_rejected(): void
    {
        $headers = $this->login(User::factory()->create());

        $this->getJson('/api/ecom/auth/me', $headers)->assertOk();
        $this->getJson('/api/ecom/auth/me', ['Authorization' => 'Bearer '.$headers['Authorization']])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/ecom/auth/me', ['Authorization' => ''])->assertUnauthorized();
    }

    public function test_password_reset_requires_matching_mobile_and_protects_demo(): void
    {
        $user = User::factory()->create(['mobile' => '9000000001']);
        $body = ['userEmail' => $user->email, 'userPassword' => 'Newpass@1', 'confirmPassword' => 'Newpass@1'];

        $this->postJson('/api/ecom/auth/password-new', $body + ['userMobile' => '9000000002'])->assertNotFound();
        $this->postJson('/api/ecom/auth/password-new', $body + ['userMobile' => '9000000001'])->assertOk();

        $this->postJson('/api/ecom/auth/password-new', [
            'userEmail' => 'demo@practice.test', 'userMobile' => '9876543210',
            'userPassword' => 'Newpass@1', 'confirmPassword' => 'Newpass@1',
        ])->assertForbidden();
    }

    public function test_product_filters(): void
    {
        $headers = $this->login(User::factory()->create());

        $this->postJson('/api/ecom/product/get-all-products', [], $headers)->assertOk()->assertJsonPath('count', 12);
        $this->postJson('/api/ecom/product/get-all-products', ['productName' => 'tee'], $headers)->assertJsonPath('count', 3);
        $this->postJson('/api/ecom/product/get-all-products', ['productCategory' => ['electronics'], 'maxPrice' => 50000], $headers)
            ->assertJsonPath('count', 2);
        $this->postJson('/api/ecom/product/get-all-products', ['productCategory' => ['toys']], $headers)->assertUnprocessable();
    }

    public function test_cart_add_count_and_remove(): void
    {
        $headers = $this->login(User::factory()->create());
        $product = Product::first();

        $this->postJson('/api/ecom/user/add-to-cart', ['productId' => $product->id], $headers)->assertOk()->assertJsonPath('count', 1);
        $this->postJson('/api/ecom/user/add-to-cart', ['productId' => $product->id], $headers)->assertJsonPath('count', 2);
        $this->getJson('/api/ecom/user/get-cart-products', $headers)->assertJsonPath('products.0.quantity', 2);
        $this->deleteJson("/api/ecom/user/remove-from-cart/{$product->id}", [], $headers)->assertOk()->assertJsonPath('count', 0);
        $this->deleteJson("/api/ecom/user/remove-from-cart/{$product->id}", [], $headers)->assertNotFound();
    }

    public function test_place_order_with_coupon_clears_cart(): void
    {
        $user = User::factory()->create();
        $headers = $this->login($user);
        $product = Product::where('price', 599)->first();
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id]);

        $ids = $this->postJson('/api/ecom/order/create-order', [
            'orders' => [['country' => 'Bangladesh', 'productOrderedId' => $product->id]],
            'couponCode' => 'practice10',
        ], $headers)->assertCreated()->assertJson(['message' => 'Order Placed Successfully'])->json('orders');

        $this->assertSame(0, $user->cartItems()->count());
        $this->getJson('/api/ecom/order/get-orders-details?id='.$ids[0], $headers)
            ->assertOk()->assertJsonPath('data.orderPrice', 539.1)->assertJsonPath('data.country', 'Bangladesh');
    }

    public function test_order_rejects_unknown_country_and_inactive_coupon(): void
    {
        $headers = $this->login(User::factory()->create());
        $productId = Product::first()->id;

        $this->postJson('/api/ecom/order/create-order', ['orders' => [['country' => 'Atlantis', 'productOrderedId' => $productId]]], $headers)
            ->assertUnprocessable();
        $this->postJson('/api/ecom/order/apply-coupon', ['couponCode' => 'EXPIRED50'], $headers)->assertNotFound();
    }

    public function test_learners_cannot_see_each_others_orders(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $alice->id]);

        $bobHeaders = $this->login($bob);
        $this->getJson('/api/ecom/order/get-orders-details?id='.$order->order_number, $bobHeaders)
            ->assertForbidden()->assertJson(['message' => 'You are not authorize to view this order']);
        $this->getJson("/api/ecom/order/get-orders-for-customer/{$alice->id}", $bobHeaders)->assertForbidden();
        $this->deleteJson("/api/ecom/order/delete-order/{$order->order_number}", [], $bobHeaders)->assertForbidden();

        $this->app['auth']->forgetGuards();
        $aliceHeaders = $this->login($alice);
        $this->getJson("/api/ecom/order/get-orders-for-customer/{$alice->id}", $aliceHeaders)->assertJsonPath('count', 1);
        $this->deleteJson("/api/ecom/order/delete-order/{$order->order_number}", [], $aliceHeaders)->assertOk();
    }

    public function test_csv_download(): void
    {
        $user = User::factory()->create();
        Order::factory()->create(['user_id' => $user->id]);

        $response = $this->get('/api/ecom/order/download-csv', $this->login($user))->assertOk();
        $this->assertStringContainsString('"Order Id","Product Name"', $response->streamedContent());
    }

    public function test_cron_prune_requires_secret_and_removes_stale_data(): void
    {
        config(['practice.cron_secret' => 's3cret']);
        $old = Order::factory()->create(['created_at' => now()->subDays(10)]);
        $fresh = Order::factory()->create();
        $stale = User::factory()->create(['created_at' => now()->subDays(40), 'last_login_at' => now()->subHour()]);
        $staleToken = $stale->createToken('client');
        $recent = User::factory()->create(['created_at' => now()->subDays(10)]);
        User::where('email', 'demo@practice.test')->update(['created_at' => now()->subDays(90)]);

        $this->getJson('/api/cron/prune')->assertForbidden();
        $this->getJson('/api/cron/prune', ['Authorization' => 'Bearer s3cret'])->assertOk();

        $this->assertModelMissing($old);
        $this->assertModelExists($fresh);
        $this->assertModelMissing($stale);
        $this->assertModelMissing($staleToken->accessToken);
        $this->assertModelExists($recent);
        $this->assertDatabaseHas('users', ['email' => 'demo@practice.test']);
    }

    public function test_migrate_hook_requires_secret_and_is_idempotent(): void
    {
        config(['practice.cron_secret' => 's3cret']);

        $this->postJson('/api/cron/migrate')->assertForbidden();
        $this->postJson('/api/cron/migrate', [], ['Authorization' => 'Bearer wrong'])->assertForbidden();
        $this->postJson('/api/cron/migrate', [], ['Authorization' => 'Bearer s3cret'])->assertOk();
        $this->postJson('/api/cron/migrate', [], ['Authorization' => 'Bearer s3cret'])->assertOk();

        $this->assertSame(12, Product::count());
    }

    public function test_chaos_header_is_echoed(): void
    {
        $this->postJson('/api/ecom/auth/login', ['userEmail' => 'demo@practice.test', 'userPassword' => 'Demo@1234'], ['X-Chaos-Mode' => '1'])
            ->assertOk()->assertHeader('X-Chaos-Mode', '1');
    }
}
