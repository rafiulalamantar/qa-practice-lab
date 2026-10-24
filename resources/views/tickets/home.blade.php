@extends('tickets.layout', ['page' => 'home'])

@section('nav', true)
@section('tt-title', 'Discover & book events')

@php
    $icons = ['Conference' => 'bi-mic', 'Concert' => 'bi-music-note-beamed', 'Sports' => 'bi-trophy', 'Workshop' => 'bi-tools', 'Festival' => 'bi-stars'];
@endphp

@section('tickets')
<section class="tt-hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="tt-hero-pill"><i class="bi bi-geo-alt-fill"></i> Live events across Bangladesh</span>
                <h1 class="mt-4">Discover &amp; book <span class="tt-hero-accent">live experiences</span></h1>
                <p class="lead mt-3">Tech conferences, concerts, marathons, workshops and festivals. Find something you'll love and grab your seats in seconds.</p>
                <form class="tt-search mt-4" action="{{ url('/tickets/events') }}" method="get" role="search" data-testid="hero-search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" name="search" placeholder="Search events, venues or cities…" aria-label="Search events" data-testid="hero-search-input">
                    <button class="btn btn-tt" type="submit" data-testid="hero-search-btn">Search</button>
                </form>
                <div class="tt-hero-cats mt-4">
                    @foreach ($icons as $category => $icon)
                        <a href="{{ url('/tickets/events?category='.$category) }}" data-testid="hero-category-{{ strtolower($category) }}"><i class="bi {{ $icon }}"></i> {{ $category }}</a>
                    @endforeach
                </div>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a class="btn btn-light btn-lg" href="{{ url('/tickets/events') }}" data-testid="hero-browse-btn">Browse Events <i class="bi bi-arrow-right ms-1"></i></a>
                    <a class="btn btn-lg text-white" style="border:1px solid rgba(255,255,255,.3)" href="{{ url('/tickets/bookings') }}" data-testid="hero-bookings-btn"><i class="bi bi-ticket-perforated me-1"></i> My Bookings</a>
                </div>
            </div>
            <div class="col-lg-4 d-none d-lg-block" aria-hidden="true">
                <div class="tt-hero-art">
                    <div class="tt-mock-ticket">
                        <div class="tt-mock-main">
                            <small><i class="bi bi-calendar-event me-1"></i> Sun, 24 Jan &middot; 11:30 AM</small>
                            <strong>Capital City Half Marathon</strong>
                            <small><i class="bi bi-geo-alt me-1"></i> Hatirjheel, Dhaka</small>
                        </div>
                        <div class="tt-mock-side is-alt"><i class="bi bi-trophy"></i></div>
                    </div>
                    <div class="tt-mock-ticket">
                        <div class="tt-mock-main">
                            <small><i class="bi bi-calendar-event me-1"></i> Sat, 6 Mar &middot; 7:00 PM</small>
                            <strong>Monsoon Beats Live</strong>
                            <small><i class="bi bi-geo-alt me-1"></i> Patenga, Chattogram</small>
                            <div class="mt-2"><span class="tt-ref">TKT-7Q2M9X</span></div>
                        </div>
                        <div class="tt-mock-side"><i class="bi bi-qr-code"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container tt-stats">
    <div class="tt-card">
        <div class="row g-0">
            <div class="col-6 col-lg-3"><div class="tt-stat"><div class="tt-stat-icon"><i class="bi bi-calendar-event"></i></div><div><strong id="stat-events" data-testid="stat-events">–</strong><span>Upcoming events</span></div></div></div>
            <div class="col-6 col-lg-3"><div class="tt-stat"><div class="tt-stat-icon" style="background:#e3f6ee;color:#0f9d6b"><i class="bi bi-geo-alt"></i></div><div><strong id="stat-cities" data-testid="stat-cities">–</strong><span>Cities</span></div></div></div>
            <div class="col-6 col-lg-3"><div class="tt-stat"><div class="tt-stat-icon" style="background:#fdebf3;color:#e5397f"><i class="bi bi-people"></i></div><div><strong id="stat-seats" data-testid="stat-seats">–</strong><span>Seats available</span></div></div></div>
            <div class="col-6 col-lg-3"><div class="tt-stat"><div class="tt-stat-icon" style="background:#fff1de;color:#e07a00"><i class="bi bi-ticket-perforated"></i></div><div><strong id="stat-bookings" data-testid="stat-bookings">–</strong><span>Your bookings</span></div></div></div>
        </div>
    </div>
</div>

<main class="container">
    <section class="tt-section">
        <div class="tt-section-head">
            <div>
                <span class="tt-eyebrow"><i class="bi bi-star-fill"></i> Featured</span>
                <h2>Featured Events</h2>
                <p class="text-secondary mb-0">Hand-picked upcoming events for everyone</p>
            </div>
            <a href="{{ url('/tickets/events') }}" class="tt-link-arrow" data-testid="view-all-link">View all <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="row g-4" id="featured-events" data-testid="featured-events" aria-busy="true"></div>
    </section>

    <section class="tt-section">
        <div class="tt-section-head">
            <div>
                <span class="tt-eyebrow"><i class="bi bi-grid"></i> Categories</span>
                <h2>Browse by category</h2>
            </div>
        </div>
        <div class="row g-3" id="category-tiles" data-testid="category-tiles">
            @foreach ($icons as $category => $icon)
                <div class="col-6 col-md-4 col-xl">
                    <a class="tt-cat-tile tt-cat tt-cat-{{ $category }}" href="{{ url('/tickets/events?category='.$category) }}" data-testid="category-tile" data-category="{{ $category }}">
                        <span class="tt-cat-icon"><i class="bi {{ $icon }}"></i></span>
                        <span><strong>{{ $category }}</strong><span data-category-count>Explore</span></span>
                    </a>
                </div>
            @endforeach
        </div>
    </section>

    <section class="tt-section">
        <div class="tt-section-head">
            <div>
                <span class="tt-eyebrow"><i class="bi bi-lightning-charge"></i> How it works</span>
                <h2>Three steps to your seat</h2>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-4"><div class="tt-card tt-step"><div class="tt-step-num">1</div><h3>Find an event</h3><p>Search by name or venue, filter by category and city, and page through results.</p></div></div>
            <div class="col-md-4"><div class="tt-card tt-step"><div class="tt-step-num">2</div><h3>Pick your tickets</h3><p>Choose up to {{ config('practice.tickets_max_per_booking') }} seats, add your details and confirm. Seats are checked in real time.</p></div></div>
            <div class="col-md-4"><div class="tt-card tt-step"><div class="tt-step-num">3</div><h3>Manage bookings</h3><p>View your tickets, check refund eligibility or cancel to release the seats.</p></div></div>
        </div>
    </section>

    <section class="tt-section">
        <div class="tt-cta">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <h2 class="h3 mb-2">Host your own event</h2>
                    <p class="mb-0">Create up to {{ config('practice.tickets_max_events') }} events of your own under Manage, then book them like any customer would. Perfect for CRUD and end-to-end tests.</p>
                </div>
                <div class="col-lg-4 text-lg-end d-flex flex-wrap gap-2 justify-content-lg-end">
                    <a class="btn btn-light btn-lg" href="{{ url('/tickets/admin/events') }}"><i class="bi bi-plus-lg me-1"></i> Create event</a>
                    <a class="btn btn-lg text-white" style="border:1px solid rgba(255,255,255,.35)" href="{{ url('/tickets/events') }}">Explore All Events</a>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection
