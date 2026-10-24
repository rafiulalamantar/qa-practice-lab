@extends('tickets.layout', ['page' => 'booking-details'])

@section('nav', true)
@section('tt-title', 'Booking')

@section('tickets')
<main class="container py-4" style="max-width: 920px">
    <a href="{{ url('/tickets/bookings') }}" class="small" data-testid="back-to-bookings">&larr; Back to My Bookings</a>

    <div class="text-center py-5" id="booking-not-found" data-testid="booking-not-found" hidden>
        <h1 class="h4">Booking not found</h1>
        <p class="text-secondary">It may have been cancelled, or it belongs to another account.</p>
        <a class="btn btn-tt" href="{{ url('/tickets/bookings') }}">View My Bookings</a>
    </div>

    <div id="booking-detail" data-testid="booking-detail" hidden>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 mb-4">
            <div>
                <h1 class="h3 mb-1" id="detail-event-title" data-testid="detail-event-title"></h1>
                <span class="tt-ref" id="detail-ref" data-testid="detail-ref"></span>
                <span class="badge text-bg-success ms-2 text-capitalize" id="detail-status" data-testid="detail-status"></span>
            </div>
            <button class="btn btn-outline-danger" type="button" id="cancel-booking-btn" data-testid="cancel-booking-btn">Cancel Booking</button>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <section class="tt-card p-4 h-100">
                    <h2 class="h6 tt-section-title">Event Details</h2>
                    <dl class="tt-dl">
                        <dt>Date</dt><dd id="detail-event-date"></dd>
                        <dt>Venue</dt><dd id="detail-event-venue"></dd>
                        <dt>City</dt><dd id="detail-event-city"></dd>
                        <dt>Category</dt><dd id="detail-event-category"></dd>
                    </dl>
                </section>
            </div>
            <div class="col-md-6">
                <section class="tt-card p-4 h-100">
                    <h2 class="h6 tt-section-title">Customer Details</h2>
                    <dl class="tt-dl">
                        <dt>Name</dt><dd id="detail-name" data-testid="detail-name"></dd>
                        <dt>Email</dt><dd id="detail-email" data-testid="detail-email"></dd>
                        <dt>Phone</dt><dd id="detail-phone" data-testid="detail-phone"></dd>
                    </dl>
                </section>
            </div>
            <div class="col-md-6">
                <section class="tt-card p-4 h-100">
                    <h2 class="h6 tt-section-title">Payment Summary</h2>
                    <dl class="tt-dl">
                        <dt>Tickets</dt><dd id="detail-qty" data-testid="detail-qty"></dd>
                        <dt>Price per ticket</dt><dd id="detail-unit-price"></dd>
                        <dt>Total Paid</dt><dd class="fw-bold text-tt fs-5" id="detail-total" data-testid="detail-total"></dd>
                    </dl>
                </section>
            </div>
            <div class="col-md-6">
                <section class="tt-card p-4 h-100">
                    <h2 class="h6 tt-section-title">Refund</h2>
                    <button class="btn btn-link p-0" type="button" id="check-refund-btn" data-testid="check-refund-btn">Check eligibility for refund?</button>
                    <div class="d-flex align-items-center gap-2 text-secondary small" id="refund-spinner" data-testid="refund-spinner" hidden>
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        <span>Checking your refund eligibility…</span>
                    </div>
                    <div class="alert small mb-0" id="refund-result" data-testid="refund-result" role="status" hidden></div>
                    <h2 class="h6 tt-section-title mt-4">Booking Information</h2>
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
