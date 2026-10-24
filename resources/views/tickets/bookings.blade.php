@extends('tickets.layout', ['page' => 'bookings'])

@section('nav', true)
@section('tt-title', 'My Bookings')

@section('tickets')
<main class="container py-4" style="max-width: 920px">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">My Bookings</h1>
            <p class="text-secondary mb-0">View and manage all your ticket bookings &middot; <span id="bookings-total" data-testid="bookings-total">0</span> total</p>
        </div>
        <div class="text-end">
            <button class="btn btn-link btn-sm text-danger p-0" type="button" id="clear-bookings-btn" data-testid="clear-bookings-btn">Clear all bookings</button>
            <p class="small text-secondary mb-0">Do this often for clean test data.</p>
        </div>
    </div>

    <div id="bookings-list" data-testid="bookings-list" class="d-grid gap-3" aria-busy="true"></div>

    <div class="text-center py-5" id="bookings-empty" data-testid="bookings-empty" hidden>
        <div class="fs-1" aria-hidden="true">🎫</div>
        <h2 class="h5">No bookings yet</h2>
        <p class="text-secondary">Book an event and it will show up here.</p>
        <a class="btn btn-tt" href="{{ url('/tickets/events') }}">Browse Events</a>
    </div>
    <div class="text-center py-5" id="bookings-error" hidden>
        <h2 class="h5">Couldn't load bookings</h2>
        <button class="btn btn-outline-secondary" type="button" data-retry>Retry</button>
    </div>

    <nav class="tt-pager mt-4" id="bookings-pager" aria-label="Bookings pages" hidden>
        <button class="btn btn-outline-secondary btn-sm" type="button" data-page-prev data-testid="prev-page-btn">&larr; Prev</button>
        <span class="small text-secondary" data-page-label data-testid="page-label"></span>
        <button class="btn btn-outline-secondary btn-sm" type="button" data-page-next data-testid="next-page-btn">Next &rarr;</button>
    </nav>
</main>
@endsection
