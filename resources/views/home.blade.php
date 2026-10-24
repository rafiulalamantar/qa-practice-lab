@extends('layouts.base')

@section('title', 'QA Practice Lab')

@section('body')
<section class="hero">
    <div class="container">
        <h1 class="display-5 fw-bold">QA Practice Lab</h1>
        <p class="lead mt-3">Free, always-on practice applications for Selenium, Playwright, Cypress and API automation.
            Every learner gets isolated data, so you can practise any time without stepping on anyone else.</p>
        <div class="d-flex flex-wrap gap-2 mt-4">
            <a class="btn btn-light fw-semibold" href="{{ url('/practice') }}">Start practising</a>
            <a class="btn btn-outline-light" href="?chaos=1" id="enable-chaos">Enable challenge mode</a>
        </div>
    </div>
</section>

<main class="container py-5">
    <div class="row g-4">
        <div class="col-md-6 col-xl-3">
            <a href="{{ url('/practice') }}" class="text-decoration-none text-reset">
                <div class="module-card p-4 h-100">
                    <div class="tag">Module 1 &middot; UI widgets</div>
                    <h2 class="h4 mt-2">Automation Practice</h2>
                    <p class="text-secondary mb-0">Radio buttons, auto-suggest, dropdowns, checkboxes, windows, tabs, alerts, web tables, hover menus and iframes on a single page.</p>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-xl-3">
            <a href="{{ url('/protoshop') }}" class="text-decoration-none text-reset">
                <div class="module-card p-4 h-100">
                    <div class="tag">Module 2 &middot; Forms &amp; flows</div>
                    <h2 class="h4 mt-2">ProtoShop</h2>
                    <p class="text-secondary mb-0">Form validation, two-way binding, a product grid, cart table and delivery checkout with country suggestions.</p>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-xl-3">
            <a href="{{ url('/client') }}" class="text-decoration-none text-reset">
                <div class="module-card p-4 h-100">
                    <div class="tag">Module 3 &middot; E2E + API</div>
                    <h2 class="h4 mt-2">ShopSphere</h2>
                    <p class="text-secondary mb-0">A full e-commerce app: register, login, filter products, cart, coupons, checkout and order history, backed by a token-secured REST API.</p>
                </div>
            </a>
        </div>
        <div class="col-md-6 col-xl-3">
            <a href="{{ url('/tickets') }}" class="text-decoration-none text-reset">
                <div class="module-card p-4 h-100">
                    <div class="tag">Module 4 &middot; CRUD + API</div>
                    <h2 class="h4 mt-2">TicketTrail</h2>
                    <p class="text-secondary mb-0">Event booking: browse and filter events, book seats, cancel, check refunds, and manage your own events, with a Swagger-documented REST API.</p>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-7">
            <div class="module-card p-4 h-100">
                <h2 class="h5">Challenge mode</h2>
                <p class="text-secondary">Real applications are slow, change their ids and reorder data. Challenge mode simulates that so your scripts learn to cope:</p>
                <ul class="text-secondary mb-3">
                    <li>Sections appear after a random 0.5&ndash;3&nbsp;s delay: practise explicit waits.</li>
                    <li>Some element ids get a random suffix: practise robust locators.</li>
                    <li>Table rows are shuffled: never locate by row index.</li>
                    <li>API responses are delayed: wait for network, not for time.</li>
                </ul>
                <p class="small mb-0">Add <code>?chaos=1</code> to any page to switch it on, <code>?chaos=0</code> to switch off, and
                    <code>?seed=1234</code> to replay the same random run when debugging a flaky test.</p>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="module-card p-4 h-100">
                <h2 class="h5">API practice</h2>
                <p class="text-secondary">Both apps' REST APIs are documented so you can mix UI and API steps: log in via API, inject the token, or mock responses.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-lab" href="{{ url('/docs/api') }}">ShopSphere API</a>
                    <a class="btn btn-outline-secondary" href="{{ url('/tickets/api-docs') }}">TicketTrail API (Swagger)</a>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
