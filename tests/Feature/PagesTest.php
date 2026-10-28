<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagesTest extends TestCase
{
    public static function pages(): array
    {
        return [
            'home' => ['/', 'QA Practice Lab'],
            'practice' => ['/practice', 'id="autocomplete"'],
            'practice frame' => ['/practice/frame', 'Mentorship'],
            'practice window' => ['/practice/window', 'child window'],
            'protoshop form' => ['/protoshop', 'id="exampleInputPassword1"'],
            'protoshop shop' => ['/protoshop/shop', '<app-card'],
            'protoshop checkout' => ['/protoshop/checkout', 'id="cart-rows"'],
            'protoshop delivery' => ['/protoshop/delivery', 'id="checkbox2"'],
            'login' => ['/client/auth/login', 'id="userPassword"'],
            'register' => ['/client/auth/register', 'id="confirmPassword"'],
            'password' => ['/client/auth/password-new', 'Save New Password'],
            'dash' => ['/client/dashboard/dash', 'id="products"'],
            'cart' => ['/client/dashboard/cart', 'My Cart'],
            'order' => ['/client/dashboard/order', 'Place Order'],
            'thanks' => ['/client/dashboard/thanks', 'Thank you for the order.'],
            'myorders' => ['/client/dashboard/myorders', 'Your Orders'],
            'product details' => ['/client/dashboard/product-details/1', 'id="detail-add"'],
            'order details' => ['/client/dashboard/order-details/abc', 'id="order-summary"'],
            'api docs' => ['/docs/api', '/order/create-order'],
            'tickets home' => ['/tickets', 'id="featured-events"'],
            'tickets login' => ['/tickets/login', 'data-testid="login-btn"'],
            'tickets register' => ['/tickets/register', 'data-testid="register-confirm-password"'],
            'tickets events' => ['/tickets/events', 'data-testid="category-filter"'],
            'tickets event details' => ['/tickets/events/1', 'id="ticket-count"'],
            'tickets bookings' => ['/tickets/bookings', 'data-testid="clear-bookings-btn"'],
            'tickets booking details' => ['/tickets/bookings/1', 'data-testid="check-refund-btn"'],
            'tickets admin events' => ['/tickets/admin/events', 'data-testid="admin-event-form"'],
            'tickets admin bookings' => ['/tickets/admin/bookings', 'data-testid="booking-event-filter"'],
            'tickets api docs' => ['/tickets/api-docs', 'swagger-ui'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_renders(string $uri, string $needle): void
    {
        $this->get($uri)->assertOk()->assertSee($needle, false);
    }

    public function test_every_page_shows_author_footer_but_embedded_pages_do_not(): void
    {
        // An empty link is hidden.
        config(['practice.author.linkedin' => '']);

        foreach (['/', '/practice', '/protoshop', '/client/auth/login', '/tickets', '/tickets/login', '/docs/api', '/tickets/api-docs'] as $uri) {
            $this->get($uri)->assertOk()
                ->assertSee('data-testid="site-footer"', false)
                ->assertSee('Rafiul Alam Antar')
                ->assertSee('https://buymeacoffee.com/rafiulalamantar', false)
                ->assertDontSee('data-testid="footer-linkedin"', false);
        }

        foreach (['/practice/frame', '/practice/window'] as $uri) {
            $this->get($uri)->assertOk()->assertDontSee('data-testid="site-footer"', false);
        }
    }

    public function test_tickets_pages_check_the_session_before_first_paint(): void
    {
        $this->get('/tickets')->assertOk()
            ->assertSeeInOrder(['var isAuthPage = false;', '</head>', 'id="featured-events"'], false)
            ->assertSee('tokenMinutes: '.config('sanctum.expiration'), false);

        $this->get('/tickets/login')->assertOk()
            ->assertSeeInOrder(['var isAuthPage = true;', '</head>'], false);
    }

    public function test_license_is_served_as_plain_text(): void
    {
        $this->get('/license')->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Copyright (c) 2026 Rafiul Alam Antar');
    }

    public function test_client_root_redirects_to_login(): void
    {
        $this->get('/client')->assertRedirect('/client/auth/login');
    }
}
