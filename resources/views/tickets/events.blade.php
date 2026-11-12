@extends('tickets.layout', ['page' => 'events'])

@section('nav', true)
@section('tt-title', 'Events')

@php
    $icons = ['Conference' => 'bi-mic', 'Concert' => 'bi-music-note-beamed', 'Sports' => 'bi-trophy', 'Workshop' => 'bi-tools', 'Festival' => 'bi-stars'];
@endphp

@section('tickets')
<main class="container">
    <header class="tt-page-head">
        <span class="tt-eyebrow"><i class="bi bi-calendar-event"></i> Explore</span>
        <h1 class="mt-1">Upcoming Events</h1>
        <p class="text-secondary mb-0">Find your next unforgettable experience</p>
    </header>

    <div class="tt-note my-4" role="note" data-testid="sandbox-note">
        <i class="bi bi-info-circle-fill"></i>
        <div>Your sandbox holds up to <strong>{{ config('practice.tickets_max_bookings') }} bookings</strong> and
            <strong>{{ config('practice.tickets_max_events') }} custom events</strong>. When a limit is reached, the oldest entry is replaced automatically.</div>
    </div>

    <form class="tt-card tt-filters mb-3" id="event-filters" data-testid="event-filters" role="search">
        <div class="row g-2 align-items-end">
            <div class="col-lg-5">
                <label for="search" class="form-label small">Search</label>
                <div class="tt-input-icon">
                    <i class="bi bi-search"></i>
                    <input id="search" name="search" type="search" class="form-control" placeholder="Search events, venues…" data-testid="search-input" aria-describedby="search-status">
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <label for="category" class="form-label small">Category</label>
                <div class="tt-input-icon">
                    <i class="bi bi-grid"></i>
                    <select id="category" name="category" class="form-select" data-testid="category-filter">
                        <option value="">All categories</option>
                        @foreach (\App\Models\Event::CATEGORIES as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <label for="city" class="form-label small">City</label>
                <div class="tt-input-icon">
                    <i class="bi bi-geo-alt"></i>
                    <input id="city" name="city" type="text" class="form-control" placeholder="Any city" data-testid="city-filter">
                </div>
            </div>
            <div class="col-lg-2">
                <button type="reset" class="btn btn-outline-secondary w-100" style="height:46px" data-testid="clear-filters-btn" id="clear-filters-btn"><i class="bi bi-x-circle me-1"></i> Clear filters</button>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
            <span class="tt-search-status" id="search-status" data-testid="search-status" data-state="idle" aria-live="polite"></span>
            <button class="btn btn-link btn-sm px-0 fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#advanced-filters" aria-expanded="false" aria-controls="advanced-filters" id="advanced-filters-btn" data-testid="advanced-filters-btn">
                <i class="bi bi-sliders2 me-1"></i> More filters
            </button>
        </div>

        <div class="collapse" id="advanced-filters" data-testid="advanced-filters">
            <div class="row g-2 align-items-end pt-2 mt-1 border-top">
                <div class="col-6 col-lg-2">
                    <label for="minPrice" class="form-label small">Min price (৳)</label>
                    <input id="minPrice" name="minPrice" type="number" min="0" step="50" inputmode="numeric" class="form-control" placeholder="0" data-testid="min-price-filter">
                </div>
                <div class="col-6 col-lg-2">
                    <label for="maxPrice" class="form-label small">Max price (৳)</label>
                    <input id="maxPrice" name="maxPrice" type="number" min="0" step="50" inputmode="numeric" class="form-control" placeholder="Any" data-testid="max-price-filter">
                </div>
                <div class="col-6 col-lg-2">
                    <label for="from" class="form-label small">From</label>
                    <input id="from" name="from" type="date" class="form-control" data-testid="from-date-filter">
                </div>
                <div class="col-6 col-lg-2">
                    <label for="to" class="form-label small">To</label>
                    <input id="to" name="to" type="date" class="form-control" data-testid="to-date-filter">
                </div>
                <div class="col-6 col-lg-2">
                    <label for="sort" class="form-label small">Sort by</label>
                    <select id="sort" name="sort" class="form-select" data-testid="sort-select">
                        <option value="">Soonest first</option>
                        <option value="-date">Latest first</option>
                        <option value="price">Price: low to high</option>
                        <option value="-price">Price: high to low</option>
                        <option value="title">Name (A–Z)</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="form-check form-switch tt-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="availableOnly" name="availableOnly" value="1" data-testid="available-only-filter">
                        <label class="form-check-label small fw-semibold" for="availableOnly">Hide sold out</label>
                    </div>
                </div>
                <div class="col-12"><div class="small text-danger fw-semibold" id="filters-error" data-testid="filters-error" role="alert"></div></div>
            </div>
        </div>
    </form>

    <div class="tt-active-filters mb-3" id="active-filters" data-testid="active-filters" aria-label="Active filters" hidden></div>

    <div class="tt-cat-chips mb-4" id="category-chips" data-testid="category-chips" role="group" aria-label="Quick category filter">
        <button type="button" class="tt-cat-chip tt-cat" data-chip="" aria-pressed="true"><i class="bi bi-grid-3x3-gap"></i> All</button>
        @foreach ($icons as $category => $icon)
            <button type="button" class="tt-cat-chip tt-cat tt-cat-{{ $category }}" data-chip="{{ $category }}" aria-pressed="false"><i class="bi {{ $icon }}"></i> {{ $category }}</button>
        @endforeach
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="fw-semibold text-dark mb-0" id="events-count" data-testid="events-count" aria-live="polite"></p>
    </div>
    <div class="row g-4" id="events-grid" data-testid="events-grid" aria-busy="true"></div>

    <div class="tt-empty" id="events-empty" data-testid="events-empty" hidden>
        <div class="tt-empty-icon"><i class="bi bi-search"></i></div>
        <h2 class="h5">No events found</h2>
        <p class="text-secondary">Try a different search or clear the filters.</p>
        <button class="btn btn-tt" type="button" id="empty-clear-filters" data-testid="empty-clear-filters"><i class="bi bi-x-circle me-1"></i> Clear filters</button>
    </div>
    <div class="tt-empty" id="events-error" data-testid="events-error" hidden>
        <div class="tt-empty-icon" style="background:#fdecec;color:#e5484d"><i class="bi bi-wifi-off"></i></div>
        <h2 class="h5">Couldn't load events</h2>
        <p class="text-secondary">Your filters are kept. We'll retry automatically when you're back online.</p>
        <button class="btn btn-outline-secondary" type="button" data-retry><i class="bi bi-arrow-repeat me-1"></i> Retry</button>
    </div>

    <nav class="tt-pager mt-5" id="events-pager" aria-label="Events pages" hidden>
        <button class="btn btn-outline-secondary" type="button" data-page-prev data-testid="prev-page-btn"><i class="bi bi-arrow-left me-1"></i> Prev</button>
        <span class="small text-secondary" data-page-label data-testid="page-label"></span>
        <button class="btn btn-outline-secondary" type="button" data-page-next data-testid="next-page-btn">Next <i class="bi bi-arrow-right ms-1"></i></button>
    </nav>
</main>
@endsection
