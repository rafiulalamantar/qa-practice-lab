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

Route::view('/docs/api', 'docs.api');
