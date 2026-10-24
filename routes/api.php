<?php

use App\Http\Controllers\Api\CronController;
use App\Http\Controllers\Api\Ecom\AuthController;
use App\Http\Controllers\Api\Ecom\CartController;
use App\Http\Controllers\Api\Ecom\OrderController;
use App\Http\Controllers\Api\Ecom\ProductController;
use App\Http\Controllers\Api\Tickets\AuthController as TicketsAuthController;
use App\Http\Controllers\Api\Tickets\BookingController;
use App\Http\Controllers\Api\Tickets\EventController;
use App\Http\Controllers\Api\Tickets\MetaController;
use App\Http\Middleware\ChaosLatency;
use App\Http\Middleware\NormalizeAuthorizationHeader;
use Illuminate\Support\Facades\Route;

Route::get('/cron/prune', [CronController::class, 'prune'])->withoutMiddleware('throttle:api');
Route::post('/cron/migrate', [CronController::class, 'migrate'])->middleware('throttle:auth');

Route::prefix('ecom')->middleware([NormalizeAuthorizationHeader::class, ChaosLatency::class])->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/password-new', [AuthController::class, 'passwordNew']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::post('/product/get-all-products', [ProductController::class, 'index']);
        Route::get('/product/get-product-detail/{product}', [ProductController::class, 'show']);

        Route::post('/user/add-to-cart', [CartController::class, 'store']);
        Route::get('/user/get-cart-products', [CartController::class, 'index']);
        Route::get('/user/get-cart-count', [CartController::class, 'countJson']);
        Route::delete('/user/remove-from-cart/{product}', [CartController::class, 'destroy']);

        Route::post('/order/apply-coupon', [OrderController::class, 'applyCoupon']);
        Route::post('/order/create-order', [OrderController::class, 'store']);
        Route::get('/order/get-orders-for-customer/{userId}', [OrderController::class, 'forCustomer']);
        Route::get('/order/get-orders-details', [OrderController::class, 'show']);
        Route::delete('/order/delete-order/{order}', [OrderController::class, 'destroy']);
        Route::get('/order/download-csv', [OrderController::class, 'downloadCsv']);
    });
});

// TicketTrail: event booking. Errors use {success:false, error, details?}.
Route::prefix('tickets')->middleware([NormalizeAuthorizationHeader::class, ChaosLatency::class])->group(function () {
    Route::get('/health', [MetaController::class, 'health']);
    Route::get('/config', [MetaController::class, 'config']);
    Route::get('/openapi.json', [MetaController::class, 'openapi']);

    Route::middleware('throttle:auth')->group(function () {
        Route::post('/auth/register', [TicketsAuthController::class, 'register']);
        Route::post('/auth/login', [TicketsAuthController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [TicketsAuthController::class, 'me']);

        Route::get('/events', [EventController::class, 'index']);
        Route::post('/events', [EventController::class, 'store']);
        Route::get('/events/{id}', [EventController::class, 'show']);
        Route::put('/events/{id}', [EventController::class, 'update']);
        Route::delete('/events/{id}', [EventController::class, 'destroy']);

        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::delete('/bookings', [BookingController::class, 'clear']);
        Route::get('/bookings/ref/{ref}', [BookingController::class, 'showByRef']);
        Route::get('/bookings/{id}', [BookingController::class, 'show']);
        Route::delete('/bookings/{id}', [BookingController::class, 'destroy']);
    });
});
