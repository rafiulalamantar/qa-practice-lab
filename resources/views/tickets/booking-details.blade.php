@extends('tickets.layout', ['page' => 'booking-details'])

@section('nav', true)
@section('tt-title', 'Booking')

@section('tickets')
<main class="container" style="max-width: 980px">
    <div class="pt-4"><a href="{{ url('/tickets/bookings') }}" class="tt-link-arrow" data-testid="back-to-bookings"><i class="bi bi-arrow-left"></i> Back to My Bookings</a></div>

    <div class="tt-card tt-empty mt-4" id="booking-not-found" data-testid="booking-not-found" hidden>
        <div class="tt-empty-icon"><i class="bi bi-ticket-perforated"></i></div>
        <h1 class="h4">Booking not found</h1>
        <p class="text-secondary">It may have been cancelled, or it belongs to another account.</p>
        <a class="btn btn-tt" href="{{ url('/tickets/bookings') }}">View My Bookings</a>
    </div>

    <div id="booking-detail" data-testid="booking-detail" hidden>
        <article class="tt-card tt-pass mt-4">
            <div class="tt-pass-head">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="tt-ref" id="detail-ref" data-testid="detail-ref"></span>
                    <span class="tt-status" id="detail-status" data-testid="detail-status"></span>
                </div>
                <h1 id="detail-event-title" data-testid="detail-event-title"></h1>
                <span class="opacity-75"><i class="bi bi-ticket-perforated me-1"></i> E-ticket &middot; show this reference at the venue</span>
            </div>
            <div class="tt-pass-body">
                <div><small>Date</small><strong id="detail-event-date"></strong></div>
                <div><small>Venue</small><strong id="detail-event-venue"></strong></div>
                <div><small>City</small><strong id="detail-event-city"></strong></div>
                <div><small>Category</small><strong id="detail-event-category"></strong></div>
            </div>
            <div class="tt-pass-foot">
                <div class="tt-barcode" aria-hidden="true"></div>
                <button class="btn btn-outline-danger" type="button" id="cancel-booking-btn" data-testid="cancel-booking-btn"><i class="bi bi-x-circle me-1"></i> Cancel Booking</button>
            </div>
        </article>

        <div class="row g-4 mt-1">
            <div class="col-md-6">
                <section class="tt-card p-4 h-100">
                    <h2 class="tt-section-title"><i class="bi bi-person"></i> Customer Details</h2>
                    <dl class="tt-dl">
                        <dt>Name</dt><dd id="detail-name" data-testid="detail-name"></dd>
                        <dt>Email</dt><dd id="detail-email" data-testid="detail-email"></dd>
                        <dt>Phone</dt><dd id="detail-phone" data-testid="detail-phone"></dd>
                    </dl>
                </section>
            </div>
            <div class="col-md-6">
                <section class="tt-card p-4 h-100">
                    <h2 class="tt-section-title"><i class="bi bi-receipt"></i> Payment Summary</h2>
                    <dl class="tt-dl">
                        <dt>Tickets</dt><dd id="detail-qty" data-testid="detail-qty"></dd>
                        <dt>Price per ticket</dt><dd id="detail-unit-price"></dd>
                        <dt>Total Paid</dt><dd class="text-tt fs-5 fw-bold" id="detail-total" data-testid="detail-total"></dd>
                    </dl>
                </section>
            </div>
            <div class="col-md-6">
                <section class="tt-card p-4 h-100">
                    <h2 class="tt-section-title"><i class="bi bi-arrow-counterclockwise"></i> Refund</h2>
                    <p class="small text-secondary">Find out whether this booking can be refunded. The check takes a few seconds.</p>
                    <button class="btn btn-tt-ghost" type="button" id="check-refund-btn" data-testid="check-refund-btn"><i class="bi bi-search me-1"></i> Check eligibility for refund?</button>
                    <div class="d-flex align-items-center gap-2 text-secondary fw-semibold" id="refund-spinner" data-testid="refund-spinner" hidden>
                        <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                        <span>Checking your refund eligibility…</span>
                    </div>
                    <div class="tt-refund-result" id="refund-result" data-testid="refund-result" role="status" hidden></div>
                </section>
            </div>
            <div class="col-md-6">
                <section class="tt-card p-4 h-100">
                    <h2 class="tt-section-title"><i class="bi bi-info-circle"></i> Booking Information</h2>
                    <dl class="tt-dl mb-0">
                        <dt>Booked on</dt><dd id="detail-created" data-testid="detail-created"></dd>
                        <dt>Booking ID</dt><dd id="detail-id" data-testid="detail-id"></dd>
                    </dl>
                </section>
            </div>
        </div>
    </div>
</main>
@endsection
