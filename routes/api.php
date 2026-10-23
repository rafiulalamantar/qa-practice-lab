<?php

use App\Http\Controllers\Api\CronController;
use App\Http\Controllers\Api\Ecom\AuthController;
use App\Http\Controllers\Api\Ecom\CartController;
use App\Http\Controllers\Api\Ecom\OrderController;
use App\Http\Controllers\Api\Ecom\ProductController;
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
