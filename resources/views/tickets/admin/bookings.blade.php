@extends('tickets.layout', ['page' => 'admin-bookings', 'sidebar' => true])

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
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-4 py-3 border-bottom">
            <h2 class="h5 mb-0">Bookings</h2>
            <div class="d-flex align-items-center gap-2">
                <label class="small text-secondary text-nowrap" for="bookings-page-size">Rows</label>
                <select class="form-select form-select-sm tt-page-size" id="bookings-page-size" data-testid="page-size-select">
                    <option value="5">5</option><option value="10">10</option><option value="25">25</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary text-nowrap" type="button" id="refresh-admin-bookings-btn" data-testid="refresh-btn" title="Refresh (r)"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
                <button class="btn btn-sm btn-outline-secondary text-nowrap" type="button" id="export-csv-btn" data-testid="export-csv-btn" data-pro-feature="CSV export"><i class="bi bi-download me-1"></i> Export CSV <span class="tt-pro-tag" data-pro-tag><i class="bi bi-lock-fill"></i> PRO</span></button>
            </div>
        </div>
        <div class="tt-select-all-note" id="select-all-note" data-testid="select-all-note" hidden></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 tt-table" id="admin-bookings-table" data-testid="admin-bookings-table">
                <thead><tr><th scope="col" data-sort="ref">Reference</th><th scope="col" data-sort="event">Event</th><th scope="col" data-sort="customer">Customer</th><th scope="col" class="text-center" data-sort="quantity">Tickets</th><th scope="col" data-sort="total">Total</th><th scope="col" data-sort="created">Booked on</th><th scope="col" class="text-end">Actions</th></tr></thead>
                <tbody id="admin-bookings-body"></tbody>
            </table>
        </div>
        <div class="tt-empty" id="admin-bookings-empty" hidden><div class="tt-empty-icon"><i class="bi bi-receipt"></i></div><h3 class="h6">No bookings found</h3><p class="small text-secondary" data-empty-hint>Book an event and it will show up here.</p><button class="btn btn-sm btn-outline-secondary" type="button" id="clear-event-filter-btn" data-testid="clear-event-filter-btn" hidden>Show all events</button></div>
        <div class="tt-empty" id="admin-bookings-error" data-testid="table-error" hidden><div class="tt-empty-icon" style="background:#fdecec;color:#e5484d"><i class="bi bi-wifi-off"></i></div><h3 class="h6">Couldn't load bookings</h3><button class="btn btn-sm btn-outline-secondary" type="button" data-retry><i class="bi bi-arrow-repeat me-1"></i> Retry</button></div>
        <nav class="tt-pager p-3 border-top" id="admin-bookings-pager" aria-label="Bookings pages" hidden>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-page-prev data-testid="prev-page-btn"><i class="bi bi-arrow-left me-1"></i> Prev</button>
            <span class="small text-secondary" data-page-label data-testid="page-label"></span>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-page-next data-testid="next-page-btn">Next <i class="bi bi-arrow-right ms-1"></i></button>
        </nav>
    </div>
</main>

<div class="tt-bulkbar" id="bulk-bar" data-testid="bulk-bar" role="region" aria-label="Bulk actions" hidden>
    <span><strong id="bulk-count" data-testid="bulk-count">0</strong> selected</span>
    <button class="btn btn-sm btn-danger" type="button" id="bulk-cancel-btn" data-testid="bulk-cancel-btn"><i class="bi bi-x-circle me-1"></i> Cancel selected</button>
    <button class="btn btn-sm btn-link text-white" type="button" id="bulk-clear-btn" data-testid="bulk-clear-btn">Clear selection</button>
</div>

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
