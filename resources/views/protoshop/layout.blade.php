@extends('layouts.base')

@section('title', 'ProtoShop')

@section('body')
<nav class="navbar navbar-expand-md navbar-dark proto-nav">
    <div class="container">
        <a class="navbar-brand" href="{{ url('/protoshop') }}">ProtoShop</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#protoNav" aria-controls="protoNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="protoNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link {{ request()->is('protoshop') ? 'active' : '' }}" href="{{ url('/protoshop') }}">Home</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->is('protoshop/shop') ? 'active' : '' }}" href="{{ url('/protoshop/shop') }}">Shop</a></li>
            </ul>
            @yield('nav-right')
        </div>
    </div>
</nav>

<main class="container py-4">
    @yield('content')
</main>

<footer class="proto-footer">
    <p class="m-0 text-center text-white small">ProtoShop &mdash; a QA Practice Lab module</p>
</footer>
@endsection

@push('scripts')
<script>window.PROTOSHOP_PRODUCTS = @json($products);</script>
<script src="{{ \App\Support\VersionedAsset::url('assets/js/protoshop.js') }}"></script>
@endpush
