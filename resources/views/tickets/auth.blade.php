@extends('tickets.layout')

@section('tickets')
<div class="container-fluid">
    <div class="row tt-auth">
        <aside class="col-lg-6 tt-auth-aside d-none d-lg-flex flex-column justify-content-between p-5">
            <a class="tt-brand text-white fs-4" href="{{ url('/') }}" style="color:#fff!important"><span class="tt-logo" aria-hidden="true"><i class="bi bi-ticket-perforated-fill"></i></span> TicketTrail</a>
            <div style="max-width: 520px">
                <span class="tt-hero-pill mb-3"><i class="bi bi-lightning-charge-fill"></i> Practice app for automation engineers</span>
                <h1 class="display-5 mt-3">Book events.<br>Break things.<br>Write better tests.</h1>
                <p class="mt-3 fs-5">A realistic event-booking app with a live REST API, so you can practise UI, API and hybrid tests end to end.</p>
                <ul class="tt-features mt-4">
                    <li><i class="bi bi-code-slash"></i> Token-secured REST API with Swagger docs</li>
                    <li><i class="bi bi-shield-lock"></i> A private sandbox for every account</li>
                    <li><i class="bi bi-hourglass-split"></i> Seat checks, dialogs, pagination and slow async flows</li>
                    <li><i class="bi bi-tools"></i> Selenium, Playwright, Cypress, RestAssured, Postman</li>
                </ul>
            </div>
            <div class="tt-mock-ticket" aria-hidden="true">
                <div class="tt-mock-main">
                    <small><i class="bi bi-calendar-event me-1"></i> Sat, 6 Mar &middot; 7:00 PM</small>
                    <strong>Monsoon Beats Live</strong>
                    <small><i class="bi bi-geo-alt me-1"></i> Patenga Seaside Arena, Chattogram</small>
                    <div class="mt-2"><span class="tt-ref">TKT-7Q2M9X</span></div>
                </div>
                <div class="tt-mock-side"><i class="bi bi-qr-code"></i></div>
            </div>
        </aside>
        <main class="col-lg-6 d-flex flex-column align-items-center justify-content-center py-5 px-3">
            <a class="tt-brand d-lg-none mb-4" href="{{ url('/tickets') }}"><span class="tt-logo" aria-hidden="true"><i class="bi bi-ticket-perforated-fill"></i></span> TicketTrail</a>
            <div class="tt-card tt-auth-card w-100" style="max-width: 460px">
                @yield('auth')
            </div>
            <div class="d-flex gap-3 small mt-4">
                <a href="{{ url('/tickets/api-docs') }}" target="_blank" rel="noopener" data-testid="api-docs-link"><i class="bi bi-braces me-1"></i>API documentation (Swagger)</a>
                <span class="text-secondary">&middot;</span>
                <a href="{{ url('/') }}" class="text-secondary">All practice apps</a>
            </div>
        </main>
    </div>
</div>
@endsection
