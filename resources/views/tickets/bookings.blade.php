@extends('tickets.layout', ['page' => 'bookings'])

@section('nav', true)
@section('tt-title', 'My Bookings')

@section('tickets')
<main class="container" style="max-width: 980px">
    <header class="tt-page-head d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <span class="tt-eyebrow"><i class="bi bi-ticket-perforated"></i> Your tickets</span>
            <h1 class="mt-1">My Bookings</h1>
            <p class="text-secondary mb-0">View and manage all your ticket bookings &middot; <strong class="text-dark" id="bookings-total" data-testid="bookings-total">0</strong> total</p>
        </div>
        <div class="text-end">
            <button class="btn btn-outline-danger btn-sm" type="button" id="clear-bookings-btn" data-testid="clear-bookings-btn"><i class="bi bi-trash3 me-1"></i> Clear all bookings</button>
            <p class="small text-secondary mb-0 mt-1">Do this often for clean test data.</p>
        </div>
    </header>

    <div id="bookings-list" data-testid="bookings-list" class="d-grid gap-3" aria-busy="true"></div>

    <div class="tt-card tt-empty" id="bookings-empty" data-testid="bookings-empty" hidden>
        <div class="tt-empty-icon"><i class="bi bi-ticket-perforated"></i></div>
        <h2 class="h5">No bookings yet</h2>
        <p class="text-secondary">Book an event and it will show up here.</p>
        <a class="btn btn-tt" href="{{ url('/tickets/events') }}">Browse Events</a>
    </div>
    <div class="tt-empty" id="bookings-error" hidden>
        <div class="tt-empty-icon" style="background:#fdecec;color:#e5484d"><i class="bi bi-wifi-off"></i></div>
        <h2 class="h5">Couldn't load bookings</h2>
        <button class="btn btn-outline-secondary" type="button" data-retry><i class="bi bi-arrow-repeat me-1"></i> Retry</button>
    </div>

    <nav class="tt-pager mt-4" id="bookings-pager" aria-label="Bookings pages" hidden>
        <button class="btn btn-outline-secondary" type="button" data-page-prev data-testid="prev-page-btn"><i class="bi bi-arrow-left me-1"></i> Prev</button>
        <span class="small text-secondary" data-page-label data-testid="page-label"></span>
        <button class="btn btn-outline-secondary" type="button" data-page-next data-testid="next-page-btn">Next <i class="bi bi-arrow-right ms-1"></i></button>
    </nav>
</main>
@endsection
