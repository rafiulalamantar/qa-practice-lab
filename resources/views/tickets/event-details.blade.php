@extends('tickets.layout', ['page' => 'event-details'])

@section('nav', true)
@section('tt-title', 'Event')

@section('tickets')
<div class="container" id="event-not-found" data-testid="event-not-found" hidden>
    <div class="tt-empty">
        <div class="tt-empty-icon"><i class="bi bi-ticket-perforated"></i></div>
        <h1 class="h4">Event not found</h1>
        <p class="text-secondary">It may have been deleted, or it belongs to another account.</p>
        <a class="btn btn-tt" href="{{ url('/tickets/events') }}">Browse Events</a>
    </div>
</div>

<div id="event-detail" data-testid="event-detail" hidden>
    <section class="tt-event-hero">
        <img id="event-image" src="" alt="">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small mb-3">
                    <li class="breadcrumb-item"><a href="{{ url('/tickets/events') }}">Events</a></li>
                    <li class="breadcrumb-item active" aria-current="page" id="crumb-title">…</li>
                </ol>
            </nav>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="tt-cat-badge tt-cat" id="event-category" data-testid="event-category"></span>
                <span class="tt-chip tt-chip-featured" id="event-featured" hidden><i class="bi bi-star-fill"></i> Featured</span>
            </div>
            <div class="row">
                <div class="col-lg-7">
                    <h1 id="event-title" data-testid="event-title"></h1>
                    <div class="tt-hero-meta mt-3">
                        <span><i class="bi bi-calendar-event"></i><span id="event-date" data-testid="event-date"></span></span>
                        <span><i class="bi bi-geo-alt"></i><span id="event-venue" data-testid="event-venue"></span>, <span id="event-city" data-testid="event-city"></span></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container mt-4">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="row g-3">
                    <div class="col-sm-4"><div class="tt-card tt-info-tile"><div class="tt-stat-icon"><i class="bi bi-clock"></i></div><div><small>Starts</small><strong id="tile-time"></strong></div></div></div>
                    <div class="col-sm-4"><div class="tt-card tt-info-tile"><div class="tt-stat-icon" style="background:#e3f6ee;color:#0f9d6b"><i class="bi bi-geo-alt"></i></div><div><small>City</small><strong id="tile-city"></strong></div></div></div>
                    <div class="col-sm-4"><div class="tt-card tt-info-tile"><div class="tt-stat-icon" style="background:#fdebf3;color:#e5397f"><i class="bi bi-people"></i></div><div><small>Availability</small><strong id="event-seats" data-testid="event-seats"></strong></div></div></div>
                </div>

                <section class="tt-card p-4 mt-4">
                    <h2 class="tt-section-title"><i class="bi bi-info-circle"></i> About this event</h2>
                    <p id="event-description" data-testid="event-description" class="mb-0 fs-6" style="line-height:1.7"></p>
                    <div class="tt-seatbar mt-4" id="event-seatbar" aria-hidden="true"><span></span></div>
                    <div class="small text-secondary" id="event-capacity"></div>
                </section>

                <section class="tt-card p-4 mt-4">
                    <h2 class="tt-section-title"><i class="bi bi-patch-check"></i> Good to know</h2>
                    <ul class="tt-goodtoknow">
                        <li><i class="bi bi-check-circle-fill"></i><span>Instant e-ticket with a unique booking reference.</span></li>
                        <li><i class="bi bi-check-circle-fill"></i><span>Book up to {{ config('practice.tickets_max_per_booking') }} tickets per booking.</span></li>
                        <li><i class="bi bi-check-circle-fill"></i><span>Single-ticket bookings qualify for a full refund; group bookings don't.</span></li>
                        <li><i class="bi bi-check-circle-fill"></i><span>Cancelling releases your seats back to the event straight away.</span></li>
                    </ul>
                </section>
            </div>

            <div class="col-lg-5">
                <div class="tt-card tt-book-panel" id="booking-panel">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h2 class="h5 mb-1">Book Tickets</h2>
                            <span class="small text-secondary">Secure your seats now</span>
                        </div>
                        <div class="text-end">
                            <div class="tt-price fs-3 text-tt" id="event-price" data-testid="event-price"></div>
                            <span class="small text-secondary">per ticket</span>
                        </div>
                    </div>

                    <div class="tt-refund-result is-ineligible mb-0" id="sold-out-msg" data-testid="sold-out-msg" hidden><i class="bi bi-x-octagon-fill"></i><span>This event is sold out.</span></div>

                    <form id="booking-form" data-testid="booking-form" novalidate>
                        <div class="mb-3">
                            <span class="form-label d-block">Tickets</span>
                            <div class="d-flex align-items-center gap-3">
                                <div class="tt-stepper">
                                    <button type="button" class="btn" id="qty-minus" data-testid="qty-minus" aria-label="Fewer tickets"><i class="bi bi-dash-lg"></i></button>
                                    <span id="ticket-count" class="ticket-count" data-testid="ticket-count" aria-live="polite">1</span>
                                    <button type="button" class="btn" id="qty-plus" data-testid="qty-plus" aria-label="More tickets"><i class="bi bi-plus-lg"></i></button>
                                </div>
                                <span class="small text-secondary" id="qty-max"></span>
                            </div>
                            <div class="small text-danger mt-1 fw-semibold" data-error-for="quantity"></div>
                        </div>
                        <div class="mb-3">
                            <label for="customerName" class="form-label">Full Name</label>
                            <div class="tt-input-icon">
                                <i class="bi bi-person"></i>
                                <input id="customerName" name="customerName" class="form-control" placeholder="Your full name" autocomplete="name" data-testid="customer-name">
                                <div class="invalid-feedback" data-error-for="customerName"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="customerEmail" class="form-label">Email</label>
                            <div class="tt-input-icon">
                                <i class="bi bi-envelope"></i>
                                <input id="customerEmail" name="customerEmail" type="email" class="form-control" placeholder="you@email.com" autocomplete="email" data-testid="customer-email">
                                <div class="invalid-feedback" data-error-for="customerEmail"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="customerPhone" class="form-label">Phone</label>
                            <div class="tt-input-icon">
                                <i class="bi bi-telephone"></i>
                                <input id="customerPhone" name="customerPhone" type="tel" class="form-control" placeholder="+880 1712-345678" autocomplete="tel" data-testid="customer-phone">
                                <div class="invalid-feedback" data-error-for="customerPhone"></div>
                            </div>
                        </div>
                        <div class="tt-total mb-3">
                            <div class="tt-total-row"><span id="total-breakdown"></span><span id="total-subtotal"></span></div>
                            <div class="tt-total-grand"><span>Total</span><span id="booking-total" data-testid="booking-total"></span></div>
                        </div>
                        <button type="submit" class="btn btn-tt btn-lg w-100" id="confirm-booking-btn" data-testid="confirm-booking-btn">Confirm Booking</button>
                        <div class="tt-secure"><i class="bi bi-shield-lock"></i> Seats are held the moment you confirm</div>
                    </form>

                    <div id="booking-success" data-testid="booking-success" class="text-center" hidden>
                        <div class="tt-success-check"><i class="bi bi-check-lg"></i></div>
                        <h2 class="h4 mb-1">Booking Confirmed!</h2>
                        <p class="text-secondary">Your tickets are reserved.</p>
                        <div class="tt-ref fs-6 my-2" id="booking-ref" data-testid="booking-ref"></div>
                        <div class="tt-total text-start my-3">
                            <dl class="tt-dl">
                                <dt>Customer</dt><dd id="success-name"></dd>
                                <dt>Tickets</dt><dd id="success-qty"></dd>
                                <dt>Total</dt><dd id="success-total"></dd>
                            </dl>
                        </div>
                        <div class="d-grid gap-2">
                            <a class="btn btn-tt" href="{{ url('/tickets/bookings') }}" data-testid="view-bookings-btn"><i class="bi bi-ticket-perforated me-1"></i> View My Bookings</a>
                            <a class="btn btn-outline-secondary" href="{{ url('/tickets/events') }}">Browse More Events</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
