@extends('tickets.layout')

@section('tickets')
<div class="container-fluid">
    <div class="row tt-auth">
        <aside class="col-lg-6 tt-auth-aside d-none d-lg-flex flex-column justify-content-center p-5">
            <div class="tt-brand text-white fs-4 mb-4"><span aria-hidden="true">🎟</span> TicketTrail</div>
            <h1 class="display-6 fw-bold">An event-booking app built for automation practice</h1>
            <p class="mt-3 lead">Browse events, book tickets, manage your own listings, and hit the same flows through a live REST API.</p>
            <ul class="tt-feature-list mt-3">
                <li>Real token-secured API with Swagger docs</li>
                <li>A private sandbox: your events and bookings are yours alone</li>
                <li>Seat checks, pagination, confirm dialogs and slow async checks</li>
                <li>Works with Selenium, Playwright, Cypress, RestAssured and Postman</li>
            </ul>
        </aside>
        <main class="col-lg-6 d-flex flex-column align-items-center justify-content-center py-5 px-3">
            <a class="small mb-3" href="{{ url('/tickets/api-docs') }}" target="_blank" rel="noopener" data-testid="api-docs-link">API documentation (Swagger)</a>
            <div class="tt-card p-4 p-md-5 w-100" style="max-width: 460px">
                @yield('auth')
            </div>
            <a class="small text-secondary mt-4" href="{{ url('/') }}">&larr; QA Practice Lab</a>
        </main>
    </div>
</div>
@endsection
