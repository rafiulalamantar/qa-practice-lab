@extends('client.layout')

@section('client')
<div class="container-fluid">
    <div class="row auth-split">
        <aside class="col-lg-5 auth-aside d-none d-lg-flex flex-column justify-content-center p-5">
            <h3 class="h6 text-uppercase text-white-50">We make your shopping simple</h3>
            <h1 class="title display-6 fw-bold">ShopSphere &mdash; practice e-commerce for automation engineers</h1>
            <p class="mt-3">Every account is private to you: carts and orders you create are only visible to you, and are cleaned up automatically after a week. Your account itself is deleted {{ config('practice.account_lifetime_days') }} days after registration, so register again after that.</p>
            <div class="row text-center mt-4 g-3">
                <div class="col-4"><h2 class="title h3 mb-0">12</h2><small class="text-white-50">Products</small></div>
                <div class="col-4"><h2 class="title h3 mb-0">17</h2><small class="text-white-50">API endpoints</small></div>
                <div class="col-4"><h2 class="title h3 mb-0">24/7</h2><small class="text-white-50">Available</small></div>
            </div>
        </aside>
        <main class="col-lg-7 d-flex align-items-center justify-content-center py-5 px-3">
            <div class="auth-card p-4 p-md-5 w-100" style="max-width: 560px">
                @yield('auth')
            </div>
        </main>
    </div>
</div>
@endsection
