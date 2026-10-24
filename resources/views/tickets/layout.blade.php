@extends('layouts.base')

@section('title')
@yield('tt-title', 'TicketTrail') · TicketTrail
@endsection
@section('body-class', 'tt-body')

@section('body')
<div id="toast-container" aria-live="polite"></div>

@hasSection('nav')
<nav class="navbar navbar-expand-lg navbar-light tt-nav sticky-top">
    <div class="container">
        <a class="navbar-brand tt-brand" href="{{ url('/tickets') }}"><span class="tt-logo" aria-hidden="true">🎟</span> TicketTrail</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ttNav" aria-controls="ttNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="ttNav">
            <ul class="navbar-nav me-auto ms-lg-4">
                <li class="nav-item"><a class="nav-link" href="{{ url('/tickets') }}" data-testid="nav-home" id="nav-home" data-nav="home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ url('/tickets/events') }}" data-testid="nav-events" id="nav-events" data-nav="events">Events</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ url('/tickets/bookings') }}" data-testid="nav-bookings" id="nav-bookings" data-nav="bookings">My Bookings</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ url('/tickets/api-docs') }}" target="_blank" rel="noopener" data-testid="nav-api-docs">API Docs</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-testid="nav-manage" id="nav-manage" data-nav="admin">Manage</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ url('/tickets/admin/events') }}" data-testid="nav-manage-events">Manage Events</a></li>
                        <li><a class="dropdown-item" href="{{ url('/tickets/admin/bookings') }}" data-testid="nav-manage-bookings">Manage Bookings</a></li>
                    </ul>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="small text-secondary text-truncate" style="max-width: 220px" data-testid="user-email-display" id="user-email-display"></span>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-testid="logout-btn" id="logout-btn">Logout</button>
            </div>
        </div>
    </div>
</nav>
@endif

@yield('tickets')

<div class="modal fade" id="confirm-dialog" tabindex="-1" aria-labelledby="confirm-dialog-title" aria-hidden="true" data-testid="confirm-dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5" id="confirm-dialog-title"></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-secondary" id="confirm-dialog-description"></div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" data-testid="confirm-dialog-no" id="confirm-dialog-no">Keep it</button>
                <button type="button" class="btn btn-danger" data-testid="confirm-dialog-yes" id="confirm-dialog-yes">Yes</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.TT = {
        api: @json(url('/api/tickets')),
        base: @json(url('/tickets')),
        page: @json($page ?? ''),
        images: @json(collect(\App\Models\Event::CATEGORIES)->mapWithKeys(fn ($c) => [$c => asset('images/events/'.strtolower($c).'.svg')])),
        accountDays: {{ config('practice.account_lifetime_days') }},
        limits: { events: {{ config('practice.tickets_max_events') }}, bookings: {{ config('practice.tickets_max_bookings') }}, perBooking: {{ config('practice.tickets_max_per_booking') }} }
    };
</script>
<script src="{{ asset('assets/js/tickets.js') }}"></script>
@endpush
