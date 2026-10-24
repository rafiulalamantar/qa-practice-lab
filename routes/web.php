<?php

use App\Support\Countries;
use App\Support\PracticeData;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home');

// Module 1: UI widgets
Route::get('/practice', fn () => view('practice.index', [
    'courses' => PracticeData::COURSES,
    'staff' => PracticeData::STAFF,
    'countries' => Countries::all(),
]));
Route::get('/practice/frame', fn () => view('practice.frame', ['courses' => PracticeData::COURSES]));
Route::view('/practice/window', 'practice.window');

// Module 2: ProtoShop (client-side only)
Route::prefix('protoshop')->group(function () {
    $data = fn () => ['products' => PracticeData::protoshopProducts()];

    Route::get('/', fn () => view('protoshop.form', $data()));
    Route::get('/shop', fn () => view('protoshop.shop', $data()));
    Route::get('/checkout', fn () => view('protoshop.checkout', $data()));
    Route::get('/delivery', fn () => view('protoshop.delivery', $data() + ['countries' => Countries::all()]));
});

// Module 3: ShopSphere. Pages are static shells; auth happens in the API.
Route::prefix('client')->group(function () {
    Route::redirect('/', '/client/auth/login');
    Route::redirect('/dashboard', '/client/dashboard/dash');

    Route::view('/auth/login', 'client.auth.login');
    Route::view('/auth/register', 'client.auth.register');
    Route::view('/auth/password-new', 'client.auth.password-new');

    foreach (['dash', 'cart', 'order', 'thanks', 'myorders'] as $page) {
        Route::view("/dashboard/{$page}", "client.dashboard.{$page}");
    }
    Route::view('/dashboard/product-details/{id}', 'client.dashboard.product-details');
    Route::view('/dashboard/order-details/{id}', 'client.dashboard.order-details');
});

// Module 4: TicketTrail. Static shells; data comes from /api/tickets.
Route::prefix('tickets')->group(function () {
    Route::view('/', 'tickets.home');
    Route::view('/login', 'tickets.login');
    Route::view('/register', 'tickets.register');
    Route::view('/events', 'tickets.events');
    Route::view('/events/{id}', 'tickets.event-details');
    Route::view('/bookings', 'tickets.bookings');
    Route::view('/bookings/{id}', 'tickets.booking-details');
    Route::view('/admin/events', 'tickets.admin.events');
    Route::view('/admin/bookings', 'tickets.admin.bookings');
    Route::view('/api-docs', 'tickets.api-docs');
});

Route::view('/docs/api', 'docs.api');

// The repository's LICENSE file, linked from every page's footer.
Route::get('/license', fn () => response(file_get_contents(base_path('LICENSE')))->header('Content-Type', 'text/plain; charset=UTF-8'));
