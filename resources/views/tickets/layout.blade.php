@extends('layouts.base')

@section('title')
@yield('tt-title', 'TicketTrail') · TicketTrail
@endsection
@section('body-class', 'tt-body')

{{-- Runs before first paint so a guarded page never flashes before the redirect in tickets.js. --}}
@push('head')
@php($isAuthPage = in_array($page ?? '', ['login', 'register'], true))
<script>
    (function () {
        var token = null, expired = false;
        try {
            token = localStorage.getItem('tt_token');
            var expires = Number(localStorage.getItem('tt_token_expires'));
            if (token && expires && Date.now() >= expires) {
                ['tt_token', 'tt_user', 'tt_token_expires'].forEach(function (k) { localStorage.removeItem(k); });
                token = null;
                expired = true;
            }
        } catch (e) { }
        var isAuthPage = @json($isAuthPage);
        var base = @json(url('/tickets'));
        var message = expired ? 'Your session has expired. Please sign in again.' : 'Please sign in to continue';
        if (isAuthPage && token) {
            location.replace(base);
        } else if (!token && (!isAuthPage || expired)) {
            try { sessionStorage.setItem('tt.flash', JSON.stringify({ message: message, type: 'error' })); } catch (e) { }
            if (!isAuthPage) { location.replace(base + '/login'); }
        }
    })();
</script>
@endpush

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="{{ \App\Support\VersionedAsset::url('assets/css/tickets.css') }}">
@endpush

@section('body')
<div id="toast-container" aria-live="polite"></div>

@hasSection('nav')
<nav class="navbar navbar-expand-lg tt-nav sticky-top">
    <div class="container">
        <a class="navbar-brand tt-brand" href="{{ url('/tickets') }}"><span class="tt-logo" aria-hidden="true"><i class="bi bi-ticket-perforated-fill"></i></span> TicketTrail</a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#ttNav" aria-controls="ttNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="ttNav">
            <ul class="navbar-nav me-auto ms-lg-4 gap-lg-1 py-2 py-lg-0">
                <li class="nav-item"><a class="nav-link" href="{{ url('/tickets') }}" data-testid="nav-home" id="nav-home" data-nav="home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ url('/tickets/events') }}" data-testid="nav-events" id="nav-events" data-nav="events">Events</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ url('/tickets/bookings') }}" data-testid="nav-bookings" id="nav-bookings" data-nav="bookings">My Bookings</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" data-testid="nav-manage" id="nav-manage" data-nav="admin">Manage</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ url('/tickets/admin/events') }}" data-testid="nav-manage-events"><i class="bi bi-calendar2-plus"></i> Manage Events</a></li>
                        <li><a class="dropdown-item" href="{{ url('/tickets/admin/bookings') }}" data-testid="nav-manage-bookings"><i class="bi bi-receipt"></i> Manage Bookings</a></li>
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link" href="{{ url('/tickets/api-docs') }}" target="_blank" rel="noopener" data-testid="nav-api-docs">API Docs <i class="bi bi-box-arrow-up-right small"></i></a></li>
            </ul>
            <div class="tt-user pb-2 pb-lg-0">
                <span class="tt-avatar" id="user-avatar" aria-hidden="true"></span>
                <span class="tt-user-email" data-testid="user-email-display" id="user-email-display"></span>
                <button class="btn btn-sm btn-outline-secondary ms-1" type="button" data-testid="logout-btn" id="logout-btn"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
            </div>
        </div>
    </div>
</nav>
@endif

@yield('tickets')

@hasSection('nav')
<div class="tt-footer-gap"></div>
<footer class="tt-footer">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2"><span class="tt-logo" style="width:28px;height:28px;font-size:14px;border-radius:9px" aria-hidden="true"><i class="bi bi-ticket-perforated-fill"></i></span> <span><strong class="text-dark">TicketTrail</strong> &middot; a practice app by QA Practice Lab</span></div>
        <div class="d-flex flex-wrap gap-3">
            <a href="{{ url('/tickets/events') }}">Events</a>
            <a href="{{ url('/tickets/bookings') }}">My Bookings</a>
            <a href="{{ url('/tickets/api-docs') }}" target="_blank" rel="noopener">API Docs</a>
            <a href="{{ url('/') }}">All practice apps</a>
        </div>
    </div>
</footer>
@endif

<div class="modal fade" id="confirm-dialog" tabindex="-1" aria-labelledby="confirm-dialog-title" aria-hidden="true" data-testid="confirm-dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-2 align-items-start">
                <div>
                    <div class="tt-confirm-icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></div>
                    <h2 class="modal-title h5 mb-0" id="confirm-dialog-title"></h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-secondary" id="confirm-dialog-description"></div>
            <div class="modal-footer border-0">
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
        images: @json(collect(\App\Models\Event::CATEGORIES)->mapWithKeys(fn ($c) => [$c => \App\Support\VersionedAsset::url('images/events/'.strtolower($c).'.svg')])),
        accountDays: {{ config('practice.account_lifetime_days') }},
        tokenMinutes: @json(config('sanctum.expiration')),
        limits: { events: {{ config('practice.tickets_max_events') }}, bookings: {{ config('practice.tickets_max_bookings') }}, perBooking: {{ config('practice.tickets_max_per_booking') }} }
    };
</script>
<script src="{{ \App\Support\VersionedAsset::url('assets/js/tickets.js') }}"></script>
@endpush
