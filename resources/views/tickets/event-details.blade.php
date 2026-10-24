@extends('tickets.layout', ['page' => 'event-details'])

@section('nav', true)
@section('tt-title', 'Event')

@section('tickets')
<main class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ url('/tickets/events') }}">Events</a></li>
            <li class="breadcrumb-item active" aria-current="page" id="crumb-title">…</li>
        </ol>
    </nav>

    <div class="text-center py-5" id="event-not-found" data-testid="event-not-found" hidden>
        <div class="fs-1" aria-hidden="true">🎫</div>
        <h1 class="h4">Event not found</h1>
        <p class="text-secondary">It may have been deleted, or it belongs to another account.</p>
        <a class="btn btn-tt" href="{{ url('/tickets/events') }}">Browse Events</a>
    </div>

    <div class="row g-4" id="event-detail" data-testid="event-detail" hidden>
        <div class="col-lg-7">
            <div class="tt-detail-image position-relative">
                <img id="event-image" src="" alt="" class="w-100">
                <div class="tt-badges">
                    <span class="badge rounded-pill" id="event-category" data-testid="event-category"></span>
                    <span class="badge rounded-pill bg-success" id="event-featured" hidden>Featured</span>
                </div>
            </div>
            <h1 class="h2 mt-4" id="event-title" data-testid="event-title"></h1>
            <ul class="list-unstyled text-secondary tt-meta">
                <li>📅 <span id="event-date" data-testid="event-date"></span></li>
                <li>📍 <span id="event-venue" data-testid="event-venue"></span>, <span id="event-city" data-testid="event-city"></span></li>
                <li>🎟 <span id="event-seats" data-testid="event-seats"></span></li>
            </ul>
            <h2 class="h5 mt-4">About this event</h2>
            <p id="event-description" data-testid="event-description" class="text-secondary"></p>
        </div>

        <div class="col-lg-5">
            <div class="tt-card p-4 sticky-lg-top" style="top: 80px" id="booking-panel">
                <div class="d-flex justify-content-between align-items-baseline">
                    <h2 class="h5 mb-0">Book Tickets</h2>
                    <div><span class="fs-4 fw-bold text-tt" id="event-price" data-testid="event-price"></span> <span class="small text-secondary">per ticket</span></div>
                </div>
                <hr>
                <div class="alert alert-danger small" id="sold-out-msg" data-testid="sold-out-msg" hidden>This event is sold out.</div>

                <form id="booking-form" data-testid="booking-form" novalidate>
                    <div class="mb-3">
                        <span class="form-label d-block">Tickets</span>
                        <div class="d-flex align-items-center gap-3">
                            <button type="button" class="btn btn-outline-secondary tt-step" id="qty-minus" data-testid="qty-minus" aria-label="Fewer tickets">&minus;</button>
                            <span id="ticket-count" class="ticket-count fw-semibold fs-5" data-testid="ticket-count" aria-live="polite">1</span>
                            <button type="button" class="btn btn-outline-secondary tt-step" id="qty-plus" data-testid="qty-plus" aria-label="More tickets">+</button>
                            <span class="small text-secondary" id="qty-max"></span>
                        </div>
                        <div class="small text-danger mt-1" data-error-for="quantity"></div>
                    </div>
                    <div class="mb-3">
                        <label for="customerName" class="form-label">Full Name</label>
                        <input id="customerName" name="customerName" class="form-control" placeholder="Your full name" autocomplete="name" data-testid="customer-name">
                        <div class="invalid-feedback" data-error-for="customerName"></div>
                    </div>
                    <div class="mb-3">
                        <label for="customerEmail" class="form-label">Email</label>
                        <input id="customerEmail" name="customerEmail" type="email" class="form-control" placeholder="you@email.com" autocomplete="email" data-testid="customer-email">
                        <div class="invalid-feedback" data-error-for="customerEmail"></div>
                    </div>
                    <div class="mb-3">
                        <label for="customerPhone" class="form-label">Phone</label>
                        <input id="customerPhone" name="customerPhone" type="tel" class="form-control" placeholder="+880 1712-345678" autocomplete="tel" data-testid="customer-phone">
                        <div class="invalid-feedback" data-error-for="customerPhone"></div>
                    </div>
                    <div class="tt-total p-3 mb-3">
                        <div class="d-flex justify-content-between small text-secondary"><span id="total-breakdown"></span><span id="total-subtotal"></span></div>
                        <div class="d-flex justify-content-between fw-bold mt-1"><span>Total</span><span id="booking-total" data-testid="booking-total"></span></div>
                    </div>
                    <button type="submit" class="btn btn-tt w-100" id="confirm-booking-btn" data-testid="confirm-booking-btn">Confirm Booking</button>
                </form>

                <div id="booking-success" data-testid="booking-success" class="text-center" hidden>
                    <div class="fs-1" aria-hidden="true">🎉</div>
                    <h2 class="h4">Booking Confirmed!</h2>
                    <p class="text-secondary">Your tickets are reserved.</p>
                    <div class="tt-ref my-3" id="booking-ref" data-testid="booking-ref"></div>
                    <dl class="row small text-start mb-3">
                        <dt class="col-5">Customer</dt><dd class="col-7" id="success-name"></dd>
                        <dt class="col-5">Tickets</dt><dd class="col-7" id="success-qty"></dd>
                        <dt class="col-5">Total</dt><dd class="col-7" id="success-total"></dd>
                    </dl>
                    <div class="d-grid gap-2">
                        <a class="btn btn-tt" href="{{ url('/tickets/bookings') }}" data-testid="view-bookings-btn">View My Bookings</a>
                        <a class="btn btn-outline-secondary" href="{{ url('/tickets/events') }}">Browse More Events</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
