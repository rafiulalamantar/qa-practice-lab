@extends('tickets.layout', ['page' => 'admin-bookings'])

@section('nav', true)
@section('tt-title', 'Manage Bookings')

@section('tickets')
<main class="container-xxl px-3 px-lg-4">
    <header class="tt-page-head d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <span class="tt-eyebrow"><i class="bi bi-sliders"></i> Manage</span>
            <h1 class="mt-1">Manage Bookings</h1>
            <p class="text-secondary mb-0">Every booking in your sandbox, newest first.</p>
        </div>
        <div style="min-width: 280px">
            <label for="event-filter" class="form-label small">Filter by event</label>
            <div class="tt-input-icon">
                <i class="bi bi-funnel"></i>
                <select id="event-filter" class="form-select" data-testid="booking-event-filter">
                    <option value="">All events</option>
                </select>
            </div>
        </div>
    </header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="tt-card tt-kpi"><small><i class="bi bi-receipt"></i> Bookings</small><strong id="kpi-bookings" data-testid="kpi-bookings">–</strong><span>max {{ config('practice.tickets_max_bookings') }}</span></div></div>
        <div class="col-6 col-lg-3"><div class="tt-card tt-kpi"><small><i class="bi bi-ticket-perforated" style="background:#fdebf3;color:#e5397f"></i> Tickets</small><strong id="kpi-tickets" data-testid="kpi-tickets">–</strong></div></div>
        <div class="col-6 col-lg-3"><div class="tt-card tt-kpi"><small><i class="bi bi-cash-coin" style="background:#e3f6ee;color:#0f9d6b"></i> Total spent</small><strong id="kpi-revenue" data-testid="kpi-revenue">–</strong></div></div>
        <div class="col-6 col-lg-3"><div class="tt-card tt-kpi"><small><i class="bi bi-calendar-check" style="background:#fff1de;color:#e07a00"></i> Events booked</small><strong id="kpi-events" data-testid="kpi-events">–</strong></div></div>
    </div>

    <div class="tt-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0 tt-table" id="admin-bookings-table" data-testid="admin-bookings-table">
                <thead><tr><th scope="col">Reference</th><th scope="col">Event</th><th scope="col">Customer</th><th scope="col" class="text-center">Tickets</th><th scope="col">Total</th><th scope="col">Booked on</th><th scope="col" class="text-end">Actions</th></tr></thead>
                <tbody id="admin-bookings-body"></tbody>
            </table>
        </div>
        <div class="tt-empty" id="admin-bookings-empty" hidden><div class="tt-empty-icon"><i class="bi bi-receipt"></i></div><h3 class="h6">No bookings found</h3></div>
        <nav class="tt-pager p-3 border-top" id="admin-bookings-pager" aria-label="Bookings pages" hidden>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-page-prev data-testid="prev-page-btn"><i class="bi bi-arrow-left me-1"></i> Prev</button>
            <span class="small text-secondary" data-page-label data-testid="page-label"></span>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-page-next data-testid="next-page-btn">Next <i class="bi bi-arrow-right ms-1"></i></button>
        </nav>
    </div>
</main>

<div class="modal fade" id="booking-modal" tabindex="-1" aria-labelledby="booking-modal-title" aria-hidden="true" data-testid="booking-modal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h2 class="modal-title h5" id="booking-modal-title"></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pb-4"><dl class="tt-dl mb-0" id="booking-modal-body"></dl></div>
        </div>
    </div>
</div>
@endsection
