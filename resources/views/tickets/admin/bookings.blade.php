@extends('tickets.layout', ['page' => 'admin-bookings'])

@section('nav', true)
@section('tt-title', 'Manage Bookings')

@section('tickets')
<main class="container-xxl py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <h1 class="h3 mb-0">Manage Bookings</h1>
        <div style="min-width: 260px">
            <label for="event-filter" class="form-label small text-secondary mb-1">Filter by event</label>
            <select id="event-filter" class="form-select" data-testid="booking-event-filter">
                <option value="">All events</option>
            </select>
        </div>
    </div>

    <div class="tt-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0 tt-table" id="admin-bookings-table" data-testid="admin-bookings-table">
                <thead><tr><th scope="col">Reference</th><th scope="col">Event</th><th scope="col">Customer</th><th scope="col" class="text-center">Tickets</th><th scope="col">Total</th><th scope="col">Booked on</th><th scope="col">Actions</th></tr></thead>
                <tbody id="admin-bookings-body"></tbody>
            </table>
        </div>
        <div class="text-center text-secondary py-5" id="admin-bookings-empty" hidden>No bookings found</div>
        <nav class="tt-pager p-3 border-top" id="admin-bookings-pager" aria-label="Bookings pages" hidden>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-page-prev data-testid="prev-page-btn">&larr; Prev</button>
            <span class="small text-secondary" data-page-label data-testid="page-label"></span>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-page-next data-testid="next-page-btn">Next &rarr;</button>
        </nav>
    </div>
</main>

<div class="modal fade" id="booking-modal" tabindex="-1" aria-labelledby="booking-modal-title" aria-hidden="true" data-testid="booking-modal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="booking-modal-title"></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"><dl class="tt-dl mb-0" id="booking-modal-body"></dl></div>
        </div>
    </div>
</div>
@endsection
