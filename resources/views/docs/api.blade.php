@extends('layouts.base')

@section('title', 'ShopSphere API Reference')

@php
    $base = url('/api/ecom');
    $endpoints = [
        'Auth' => [
            ['POST', '/auth/register', false, '{"firstName":"Ada","lastName":"Lovelace","userEmail":"ada@example.com","userMobile":"9123456789","occupation":"Engineer","gender":"Female","userPassword":"Strong@123","confirmPassword":"Strong@123"}', '201 {"message":"Registered Successfully"} · 400 duplicate email · 422 validation'],
            ['POST', '/auth/login', false, '{"userEmail":"'.config('practice.demo_email').'","userPassword":"'.config('practice.demo_password').'"}', '200 {"token":"…","userId":"1","message":"Login Successfully"} · 401'],
            ['POST', '/auth/password-new', false, '{"userEmail":"…","userMobile":"9123456789","userPassword":"New@1234","confirmPassword":"New@1234"}', '200 · 404 no match · 403 demo account'],
            ['GET', '/auth/me', true, null, '200 {"userId","firstName","lastName","userEmail"}'],
            ['POST', '/auth/logout', true, null, '200 {"message":"Logout Successfully"}'],
        ],
        'Products' => [
            ['POST', '/product/get-all-products', true, '{"productName":"tee","minPrice":0,"maxPrice":5000,"productCategory":["fashion"],"productSubCategory":["t-shirts"],"productFor":["women"]}', '200 {"data":[…],"count":n} — every field optional'],
            ['GET', '/product/get-product-detail/{id}', true, null, '200 {"data":{…}} · 404'],
        ],
        'Cart' => [
            ['POST', '/user/add-to-cart', true, '{"productId":1}', '200 {"message":"Product Added To Cart","count":n}'],
            ['GET', '/user/get-cart-products', true, null, '200 {"products":[{…,"quantity":n}],"count":n}'],
            ['GET', '/user/get-cart-count', true, null, '200 {"count":n}'],
            ['DELETE', '/user/remove-from-cart/{productId}', true, null, '200 · 404 not in cart'],
        ],
        'Orders' => [
            ['POST', '/order/apply-coupon', true, '{"couponCode":"PRACTICE10"}', '200 {"discountPercent":10} · 404 Invalid Coupon'],
            ['POST', '/order/create-order', true, '{"orders":[{"country":"India","productOrderedId":1}],"couponCode":"PRACTICE10"}', '201 {"orders":["<orderId>"],"productOrderId":["1"],"message":"Order Placed Successfully"}'],
            ['GET', '/order/get-orders-for-customer/{userId}', true, null, '200 {"data":[…],"count":n} · 403 another user'],
            ['GET', '/order/get-orders-details?id={orderId}', true, null, '200 · 403 "You are not authorize to view this order" · 404'],
            ['DELETE', '/order/delete-order/{orderId}', true, null, '200 {"message":"Orders Deleted Successfully"} · 403'],
            ['GET', '/order/download-csv', true, null, '200 text/csv attachment'],
        ],
    ];
@endphp

@section('body')
<main class="container py-5" style="max-width: 980px">
    <a href="{{ url('/') }}">&larr; QA Practice Lab</a>
    <h1 class="mt-2">ShopSphere API Reference</h1>
    <p class="lead text-secondary">Base URL <code>{{ $base }}</code>. Send and accept JSON.</p>

    <div class="module-card p-4 mb-4">
        <h2 class="h5">Authentication</h2>
        <p class="mb-2">Call <code>POST /auth/login</code> and send the returned token on every protected request, either as
            <code>Authorization: Bearer &lt;token&gt;</code> or the bare <code>Authorization: &lt;token&gt;</code>. Tokens expire after 24 hours.</p>
        <p class="mb-2">The UI keeps the session in <code>localStorage</code> under <code>token</code>, <code>userId</code> and <code>userEmail</code>:
            set those three keys before opening a dashboard page to skip the login screen.</p>
        <p class="mb-0">Send <code>X-Chaos-Mode: 1</code> to get randomly delayed responses. Limits: 300 requests/min per user, 10 login attempts/min per email.</p>
    </div>

    <div class="module-card p-4 mb-4">
        <h2 class="h5">Test data</h2>
        <ul class="mb-0">
            <li>Demo login: <code>{{ config('practice.demo_email') }}</code> / <code>{{ config('practice.demo_password') }}</code></li>
            <li>Valid coupons: <code>PRACTICE10</code> (10%), <code>QA25</code> (25%), <code>FREESHIP</code> (5%). Inactive: <code>EXPIRED50</code></li>
            <li>Orders are deleted after {{ config('practice.order_retention_days') }} days; accounts unused for {{ config('practice.inactive_user_days') }} days are removed.</li>
        </ul>
    </div>

    @foreach ($endpoints as $group => $rows)
        <h2 class="h4 mt-4">{{ $group }}</h2>
        @foreach ($rows as [$method, $path, $auth, $body, $responses])
            <div class="border rounded p-3 mb-2 bg-white">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge {{ ['GET' => 'text-bg-primary', 'POST' => 'text-bg-success', 'DELETE' => 'text-bg-danger'][$method] }}">{{ $method }}</span>
                    <code class="fs-6">{{ $path }}</code>
                    @if ($auth)<span class="badge text-bg-light border">token</span>@endif
                </div>
                @if ($body)<pre class="bg-light rounded p-2 mt-2 mb-1 small" style="white-space: pre-wrap">{{ $body }}</pre>@endif
                <div class="small text-secondary mt-1">{{ $responses }}</div>
            </div>
        @endforeach
    @endforeach
</main>
@endsection
