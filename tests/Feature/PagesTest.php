<?php

namespace Tests\Feature;

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
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_page_renders(string $uri, string $needle): void
    {
        $this->get($uri)->assertOk()->assertSee($needle, false);
    }

    public function test_client_root_redirects_to_login(): void
    {
        $this->get('/client')->assertRedirect('/client/auth/login');
    }
}
