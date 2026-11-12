@extends('layouts.base')

@section('title')
@yield('tt-title', 'TicketTrail') · TicketTrail
@endsection
@section('body-class', 'tt-body')

{{-- Runs before first paint so a guarded page never flashes before the redirect in tickets.js. --}}
@push('head')
@php($isAuthPage = in_array($page ?? '', ['login', 'register'], true))
@php($isPublicPage = ($page ?? '') === 'not-found')
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
        var isPublicPage = @json($isPublicPage);
        var base = @json(url('/tickets'));
        var message = expired ? 'Your session has expired. Please sign in again.' : 'Please sign in to continue';
        if (isPublicPage) {
            return;
        }
        if (isAuthPage && token) {
            location.replace(base);
        } else if (!token && (!isAuthPage || expired)) {
            try { sessionStorage.setItem('tt.flash', JSON.stringify({ message: message, type: 'error' })); } catch (e) { }
            if (!isAuthPage) { location.replace(base + '/login'); }
        }
        try {
            if (localStorage.getItem('tt.sidebar.collapsed') === 'true') { document.documentElement.classList.add('tt-sidebar-collapsed'); }
        } catch (e) { }
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

<div class="tt-offline" id="network-banner" data-testid="offline-banner" role="status" hidden>
    <i class="bi bi-wifi-off" aria-hidden="true"></i>
    <span><strong>You're offline.</strong> Anything you type is kept on this device. We'll reconnect automatically.</span>
</div>

@hasSection('nav')
<nav class="navbar navbar-expand-lg tt-nav sticky-top" id="tt-navbar">
    <div class="container{{ ($sidebar ?? false) ? '-fluid px-lg-4' : '' }}">
        <a class="navbar-brand tt-brand" href="{{ url('/tickets') }}"><span class="tt-logo" aria-hidden="true"><i class="bi bi-ticket-perforated-fill"></i></span> TicketTrail</a>
        <button class="tt-palette-btn d-lg-none ms-auto me-2" type="button" data-open-palette aria-label="Open command palette"><i class="bi bi-search"></i></button>
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
                <li class="nav-item"><a class="nav-link" href="{{ url('/tickets/referrals') }}" data-testid="nav-referrals" id="nav-referrals" data-nav="referrals"><i class="bi bi-gift me-1 text-tt"></i>Refer &amp; Earn</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ url('/tickets/api-docs') }}" target="_blank" rel="noopener" data-testid="nav-api-docs">API Docs <i class="bi bi-box-arrow-up-right small"></i></a></li>
            </ul>
            <button class="tt-palette-btn tt-palette-wide d-none d-lg-inline-flex me-3" type="button" data-open-palette data-testid="command-palette-btn" aria-label="Open command palette">
                <i class="bi bi-search"></i><span>Search…</span><kbd data-mod-key>Ctrl K</kbd>
            </button>
            <div class="tt-user pb-2 pb-lg-0">
                <button class="tt-plan" type="button" id="plan-badge" data-testid="plan-badge" data-plan="free" data-open-upgrade>Free</button>
                <span class="tt-avatar" id="user-avatar" aria-hidden="true"></span>
                <span class="tt-user-email" data-testid="user-email-display" id="user-email-display"></span>
                <button class="btn btn-sm btn-outline-secondary ms-1" type="button" data-testid="logout-btn" id="logout-btn"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
            </div>
        </div>
    </div>
</nav>
@endif

@if ($sidebar ?? false)
<div class="tt-shell">
    <aside class="tt-sidebar" id="tt-sidebar" data-testid="sidebar" aria-label="Workspace">
        <button class="tt-sidebar-toggle" type="button" id="sidebar-toggle" data-testid="sidebar-toggle" aria-controls="tt-sidebar" aria-expanded="true" title="Collapse sidebar ( [ )">
            <i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i><span class="tt-side-label">Collapse</span>
        </button>
        <nav class="tt-side-nav">
            <span class="tt-side-heading">Workspace</span>
            <a href="{{ url('/tickets/bookings') }}" data-side="bookings" data-testid="side-bookings" title="My Bookings"><i class="bi bi-ticket-perforated"></i><span class="tt-side-label">My Bookings</span><span class="tt-side-count" id="side-count-bookings"></span></a>
            <a href="{{ url('/tickets/admin/events') }}" data-side="admin-events" data-testid="side-manage-events" title="Manage Events"><i class="bi bi-calendar2-plus"></i><span class="tt-side-label">Manage Events</span><span class="tt-side-count" id="side-count-events"></span></a>
            <a href="{{ url('/tickets/admin/bookings') }}" data-side="admin-bookings" data-testid="side-manage-bookings" title="Manage Bookings"><i class="bi bi-receipt"></i><span class="tt-side-label">Manage Bookings</span></a>
            <span class="tt-side-heading">Grow</span>
            <a href="{{ url('/tickets/referrals') }}" data-side="referrals" data-testid="side-referrals" title="Refer & Earn"><i class="bi bi-gift"></i><span class="tt-side-label">Refer &amp; Earn</span></a>
            <button type="button" class="tt-side-locked" data-pro-feature="Analytics" data-testid="side-analytics" title="Analytics (Pro)"><i class="bi bi-graph-up-arrow"></i><span class="tt-side-label">Analytics</span><span class="tt-pro-tag" data-pro-tag><i class="bi bi-lock-fill"></i> PRO</span></button>
        </nav>
        <section class="tt-usage" data-testid="usage-panel" aria-label="Plan usage">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="tt-side-heading m-0">Plan usage</span>
                <span class="tt-plan tt-plan-sm" data-plan-label>Free</span>
            </div>
            <div class="tt-meter" data-usage="bookings" data-testid="usage-bookings">
                <div class="tt-meter-top"><span>Bookings</span><span data-usage-text>– / {{ config('practice.tickets_max_bookings') }}</span></div>
                <div class="tt-meter-bar"><span></span></div>
            </div>
            <div class="tt-meter" data-usage="events" data-testid="usage-events">
                <div class="tt-meter-top"><span>Custom events</span><span data-usage-text>– / {{ config('practice.tickets_max_events') }}</span></div>
                <div class="tt-meter-bar"><span></span></div>
            </div>
            <p class="tt-usage-note" data-usage-note></p>
            <button type="button" class="btn btn-sm btn-tt w-100" data-open-upgrade data-testid="sidebar-upgrade-btn"><i class="bi bi-rocket-takeoff me-1"></i> <span data-upgrade-label>Upgrade to Pro</span></button>
        </section>
    </aside>
    <div class="tt-shell-main">
        @yield('tickets')
    </div>
</div>
@else
@yield('tickets')
@endif

@hasSection('nav')
<div class="tt-footer-gap"></div>
<footer class="tt-footer">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2"><span class="tt-logo" style="width:28px;height:28px;font-size:14px;border-radius:9px" aria-hidden="true"><i class="bi bi-ticket-perforated-fill"></i></span> <span><strong class="text-dark">TicketTrail</strong> &middot; a practice app by QA Practice Lab</span></div>
        <div class="d-flex flex-wrap gap-3">
            <a href="{{ url('/tickets/events') }}">Events</a>
            <a href="{{ url('/tickets/bookings') }}">My Bookings</a>
            <a href="{{ url('/tickets/referrals') }}">Refer &amp; Earn</a>
            <a href="{{ url('/tickets/api-docs') }}" target="_blank" rel="noopener">API Docs</a>
            <button type="button" class="btn btn-link p-0 tt-footer-link" data-open-shortcuts data-testid="footer-shortcuts">Keyboard shortcuts <kbd>?</kbd></button>
            <a href="{{ url('/') }}">All practice apps</a>
        </div>
    </div>
</footer>

{{-- Phones only (< 576px): a thumb-friendly tab bar. Hidden at Selenium's 800x600 default so it never covers elements. --}}
<nav class="tt-tabbar" data-testid="mobile-tabbar" aria-label="Primary (mobile)">
    <a href="{{ url('/tickets') }}" data-tab="home"><i class="bi bi-house"></i><span>Home</span></a>
    <a href="{{ url('/tickets/events') }}" data-tab="events"><i class="bi bi-calendar-event"></i><span>Events</span></a>
    <button type="button" data-open-palette data-testid="tab-search"><i class="bi bi-search"></i><span>Search</span></button>
    <a href="{{ url('/tickets/bookings') }}" data-tab="bookings"><i class="bi bi-ticket-perforated"></i><span>Bookings</span></a>
    <div class="dropup">
        <button type="button" data-bs-toggle="dropdown" aria-expanded="false" data-tab="admin" data-testid="tab-more"><i class="bi bi-grid"></i><span>More</span></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ url('/tickets/admin/events') }}"><i class="bi bi-calendar2-plus me-2"></i>Manage Events</a></li>
            <li><a class="dropdown-item" href="{{ url('/tickets/admin/bookings') }}"><i class="bi bi-receipt me-2"></i>Manage Bookings</a></li>
            <li><a class="dropdown-item" href="{{ url('/tickets/referrals') }}"><i class="bi bi-gift me-2"></i>Refer &amp; Earn</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><button class="dropdown-item" type="button" data-logout><i class="bi bi-box-arrow-right me-2"></i>Logout</button></li>
        </ul>
    </div>
</nav>

<button type="button" class="tt-to-top" id="back-to-top" data-testid="back-to-top" aria-label="Back to top" hidden><i class="bi bi-arrow-up"></i></button>
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
            <div class="modal-body text-secondary">
                <div id="confirm-dialog-description"></div>
                <ul class="tt-consequences" id="confirm-dialog-impact" data-testid="confirm-dialog-impact" hidden></ul>
                <div class="tt-phrase" id="confirm-dialog-phrase" hidden>
                    <label for="confirm-dialog-input" class="form-label small mb-1">Type <code id="confirm-dialog-expected"></code> to confirm</label>
                    <input id="confirm-dialog-input" class="form-control" autocomplete="off" spellcheck="false" data-testid="confirm-dialog-input">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" data-testid="confirm-dialog-no" id="confirm-dialog-no">Keep it</button>
                <button type="button" class="btn btn-danger" data-testid="confirm-dialog-yes" id="confirm-dialog-yes">Yes</button>
            </div>
        </div>
    </div>
</div>

@hasSection('nav')
<div class="modal fade" id="command-palette" tabindex="-1" aria-label="Command palette" aria-hidden="true" data-testid="command-palette">
    <div class="modal-dialog tt-palette-dialog">
        <div class="modal-content">
            <div class="tt-palette-search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input id="palette-input" type="text" placeholder="Search events or jump to a page…" autocomplete="off" spellcheck="false"
                       role="combobox" aria-expanded="true" aria-controls="palette-results" aria-autocomplete="list" data-testid="palette-input">
                <span class="spinner-border spinner-border-sm text-secondary" id="palette-spinner" role="status" aria-label="Searching" hidden></span>
                <kbd>Esc</kbd>
            </div>
            <ul class="tt-palette-results" id="palette-results" role="listbox" aria-label="Results" data-testid="palette-results"></ul>
            <div class="tt-palette-foot">
                <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span><span><kbd>Enter</kbd> open</span><span><kbd>?</kbd> all shortcuts</span>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="shortcuts-dialog" tabindex="-1" aria-labelledby="shortcuts-title" aria-hidden="true" data-testid="shortcuts-dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h2 class="modal-title h5" id="shortcuts-title"><i class="bi bi-keyboard me-2 text-tt"></i>Keyboard shortcuts</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pb-4">
                <dl class="tt-keys">
                    <dt><kbd data-mod-key>Ctrl K</kbd></dt><dd>Open the command palette</dd>
                    <dt><kbd>/</kbd></dt><dd>Search events</dd>
                    <dt><kbd>g</kbd> <kbd>h</kbd></dt><dd>Go to Home</dd>
                    <dt><kbd>g</kbd> <kbd>e</kbd></dt><dd>Go to Events</dd>
                    <dt><kbd>g</kbd> <kbd>b</kbd></dt><dd>Go to My Bookings</dd>
                    <dt><kbd>g</kbd> <kbd>m</kbd></dt><dd>Go to Manage Events</dd>
                    <dt><kbd>g</kbd> <kbd>o</kbd></dt><dd>Go to Manage Bookings</dd>
                    <dt><kbd>g</kbd> <kbd>r</kbd></dt><dd>Go to Refer &amp; Earn</dd>
                    <dt><kbd>n</kbd></dt><dd>New event (Manage Events)</dd>
                    <dt><kbd>r</kbd></dt><dd>Refresh the current list</dd>
                    <dt><kbd>[</kbd></dt><dd>Collapse or expand the sidebar</dd>
                    <dt><kbd>?</kbd></dt><dd>Show this help</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="onboarding-dialog" tabindex="-1" aria-labelledby="onboarding-title" aria-hidden="true" data-testid="onboarding-dialog" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="onboarding-form" novalidate>
            <div class="modal-header border-0 pb-0 d-block">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="tt-eyebrow" id="onboarding-step-label" data-testid="onboarding-step-label">Step 1 of 3</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="tt-progress mt-2" aria-hidden="true"><span id="onboarding-progress"></span></div>
                <h2 class="modal-title h5 mt-3" id="onboarding-title">Let's personalise TicketTrail</h2>
            </div>
            <div class="modal-body pt-2">
                <section data-step="1">
                    <p class="small text-secondary">We use this to fill in booking forms for you. It stays on this device.</p>
                    <div class="mb-3">
                        <label for="onboard-name" class="form-label">Full name</label>
                        <input id="onboard-name" name="name" class="form-control" autocomplete="name" placeholder="e.g. Nusrat Jahan" data-testid="onboarding-name">
                        <div class="invalid-feedback" data-error-for="name" id="onboard-name-error"></div>
                    </div>
                    <div class="mb-2">
                        <label for="onboard-phone" class="form-label">Phone <span class="text-secondary fw-normal">(optional)</span></label>
                        <input id="onboard-phone" name="phone" type="tel" class="form-control" autocomplete="tel" placeholder="+880 1712-345678" data-testid="onboarding-phone">
                        <div class="invalid-feedback" data-error-for="phone" id="onboard-phone-error"></div>
                    </div>
                </section>
                <section data-step="2" hidden>
                    <p class="small text-secondary">We'll show events near you first.</p>
                    <label for="onboard-city" class="form-label">Your city</label>
                    <input id="onboard-city" name="city" class="form-control" list="onboard-cities" placeholder="e.g. Dhaka" data-testid="onboarding-city">
                    <datalist id="onboard-cities"><option value="Dhaka"><option value="Chattogram"><option value="Sylhet"><option value="Khulna"><option value="Rajshahi"></datalist>
                    <div class="invalid-feedback" data-error-for="city" id="onboard-city-error"></div>
                </section>
                <section data-step="3" hidden>
                    <p class="small text-secondary">Pick at least one. You can change these later.</p>
                    <div class="tt-cat-chips" id="onboarding-interests" data-testid="onboarding-interests" role="group" aria-label="Interests">
                        @foreach (\App\Models\Event::CATEGORIES as $category)
                            <label class="tt-cat-chip tt-cat tt-cat-{{ $category }}"><input type="checkbox" name="interests" value="{{ $category }}" class="visually-hidden"> {{ $category }}</label>
                        @endforeach
                    </div>
                    <div class="small text-danger fw-semibold mt-2" data-error-for="interests"></div>
                </section>
            </div>
            <div class="modal-footer border-0 justify-content-between">
                <button type="button" class="btn btn-link text-secondary px-0" id="onboarding-skip" data-testid="onboarding-skip">Skip for now</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light" id="onboarding-back" data-testid="onboarding-back" hidden>Back</button>
                    <button type="submit" class="btn btn-tt" id="onboarding-next" data-testid="onboarding-next">Next</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="upgrade-dialog" tabindex="-1" aria-labelledby="upgrade-title" aria-hidden="true" data-testid="upgrade-dialog">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <div>
                    <span class="tt-eyebrow"><i class="bi bi-rocket-takeoff"></i> Plans</span>
                    <h2 class="modal-title h5 mt-1" id="upgrade-title">Compare plans</h2>
                    <p class="small text-secondary mb-0" id="upgrade-reason" data-testid="upgrade-reason"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="tt-plan-card" data-plan-card="free">
                            <span class="tt-plan">Free</span>
                            <div class="tt-plan-price">৳0 <small>/ month</small></div>
                            <ul>
                                <li><i class="bi bi-check2"></i> {{ config('practice.tickets_max_bookings') }} bookings in your sandbox</li>
                                <li><i class="bi bi-check2"></i> {{ config('practice.tickets_max_events') }} custom events</li>
                                <li><i class="bi bi-check2"></i> Full REST API access</li>
                                <li class="is-off"><i class="bi bi-x"></i> CSV export</li>
                                <li class="is-off"><i class="bi bi-x"></i> Analytics</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="tt-plan-card is-pro" data-plan-card="pro">
                            <span class="tt-plan tt-plan-pro">Pro</span>
                            <div class="tt-plan-price">৳499 <small>/ month</small></div>
                            <ul>
                                <li><i class="bi bi-check2"></i> Everything in Free</li>
                                <li><i class="bi bi-check2"></i> CSV export of bookings</li>
                                <li><i class="bi bi-check2"></i> Analytics dashboard</li>
                                <li><i class="bi bi-check2"></i> Pro badge across the app</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <p class="small text-secondary mt-3 mb-0"><i class="bi bi-info-circle me-1"></i> Demo only: switching plan just changes what this browser shows. Nothing is charged and the API limits stay the same.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Not now</button>
                <button type="button" class="btn btn-tt" id="upgrade-confirm" data-testid="upgrade-confirm-btn">Switch to Pro (demo)</button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    window.TT = {
        api: @json(url('/api/tickets')),
        base: @json(url('/tickets')),
        page: @json($page ?? ''),
        images: @json(collect(\App\Models\Event::CATEGORIES)->mapWithKeys(fn ($c) => [$c => \App\Support\VersionedAsset::url('images/events/'.strtolower($c).'.svg')])),
        accountDays: {{ config('practice.account_lifetime_days') }},
        tokenMinutes: {{ (int) config('sanctum.expiration') }},
        limits: { events: {{ config('practice.tickets_max_events') }}, bookings: {{ config('practice.tickets_max_bookings') }}, perBooking: {{ config('practice.tickets_max_per_booking') }} }
    };
</script>
<script src="{{ \App\Support\VersionedAsset::url('assets/js/tickets-kit.js') }}"></script>
<script src="{{ \App\Support\VersionedAsset::url('assets/js/tickets.js') }}"></script>
@endpush
