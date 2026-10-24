@extends('layouts.base')

@section('title', 'ShopSphere')
@section('body-class', 'client-body')

@section('body')
<div id="toast-container" aria-live="polite"></div>
<div class="loading-overlay" id="loader" hidden>
    <div class="spinner-border text-light" role="status"><span class="visually-hidden">Loading...</span></div>
</div>

@hasSection('nav')
<nav class="navbar navbar-expand-lg navbar-dark client-nav">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand fw-bold" href="{{ url('/client/dashboard/dash') }}">ShopSphere</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#clientNav" aria-controls="clientNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="clientNav">
            <ul class="navbar-nav ms-auto gap-lg-1">
                <li><button class="btn btn-custom" type="button" routerlink="/dashboard/" data-nav="dash">HOME</button></li>
                <li><button class="btn btn-custom" type="button" routerlink="/dashboard/myorders" data-nav="myorders">ORDERS</button></li>
                <li><button class="btn btn-custom" type="button" routerlink="/dashboard/cart" data-nav="cart">Cart <label id="cart-count">0</label></button></li>
                <li><button class="btn btn-custom" type="button" id="sign-out">Sign Out</button></li>
            </ul>
        </div>
    </div>
</nav>
@endif

@yield('client')
@endsection

@push('scripts')
<script>
    window.SHOP = {
        api: @json(url('/api/ecom')),
        base: @json(url('/client')),
        page: @json($page ?? ''),
        countries: @json(\App\Support\Countries::all()),
        demo: { email: @json(config('practice.demo_email')), password: @json(config('practice.demo_password')) }
    };
</script>
<script src="{{ \App\Support\VersionedAsset::url('assets/js/client.js') }}"></script>
@endpush
