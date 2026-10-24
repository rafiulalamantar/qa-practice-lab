@extends('tickets.layout', ['page' => 'home'])

@section('nav', true)
@section('tt-title', 'Discover & book events')

@section('tickets')
<section class="tt-hero">
    <div class="container text-center">
        <h1 class="display-5 fw-bold">Discover &amp; book <span class="tt-hero-accent">live experiences</span></h1>
        <p class="lead mx-auto mt-3">Tech conferences, concerts, races, workshops and festivals: find something you'll love and grab your seats in seconds.</p>
        <div class="d-flex flex-wrap gap-2 justify-content-center mt-4">
            <a class="btn btn-light fw-semibold" href="{{ url('/tickets/events') }}" data-testid="hero-browse-btn">Browse Events &rarr;</a>
            <a class="btn btn-outline-light" href="{{ url('/tickets/bookings') }}" data-testid="hero-bookings-btn">My Bookings</a>
        </div>
    </div>
</section>

<main class="container py-5">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h2 class="h3 mb-1">Featured Events</h2>
            <p class="text-secondary mb-0">Hand-picked upcoming events for everyone</p>
        </div>
        <a href="{{ url('/tickets/events') }}" data-testid="view-all-link">View all &rarr;</a>
    </div>
    <div class="row g-4" id="featured-events" data-testid="featured-events" aria-busy="true"></div>

    <div class="tt-cta mt-5 p-4 p-md-5 text-center">
        <h2 class="h4">Ready to experience something new?</h2>
        <p class="text-secondary">Create your own event under Manage, then book it like any customer would.</p>
        <a class="btn btn-tt" href="{{ url('/tickets/events') }}">Explore All Events</a>
    </div>
</main>
@endsection
