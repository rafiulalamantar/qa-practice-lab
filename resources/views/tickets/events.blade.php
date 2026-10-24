@extends('tickets.layout', ['page' => 'events'])

@section('nav', true)
@section('tt-title', 'Events')

@section('tickets')
<main class="container py-4">
    <h1 class="h3 mb-1">Upcoming Events</h1>
    <p class="text-secondary">Find your next unforgettable experience</p>

    <div class="alert tt-note small" role="note" data-testid="sandbox-note">
        Your sandbox holds up to <strong>{{ config('practice.tickets_max_bookings') }} bookings</strong> and
        <strong>{{ config('practice.tickets_max_events') }} custom events</strong>. When a limit is reached, the oldest entry is replaced automatically.
    </div>

    <form class="row g-2 align-items-end mb-4" id="event-filters" data-testid="event-filters" role="search">
        <div class="col-md-5">
            <label for="search" class="form-label small text-secondary mb-1">Search</label>
            <input id="search" name="search" type="search" class="form-control" placeholder="Search events, venues…" data-testid="search-input">
        </div>
        <div class="col-6 col-md-3">
            <label for="category" class="form-label small text-secondary mb-1">Category</label>
            <select id="category" name="category" class="form-select" data-testid="category-filter">
                <option value="">All categories</option>
                @foreach (\App\Models\Event::CATEGORIES as $category)
                    <option value="{{ $category }}">{{ $category }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="city" class="form-label small text-secondary mb-1">City</label>
            <input id="city" name="city" type="text" class="form-control" placeholder="Any city" data-testid="city-filter">
        </div>
        <div class="col-md-2">
            <button type="reset" class="btn btn-outline-secondary w-100" data-testid="clear-filters-btn" id="clear-filters-btn">Clear filters</button>
        </div>
    </form>

    <p class="small text-secondary" id="events-count" data-testid="events-count" aria-live="polite"></p>
    <div class="row g-4" id="events-grid" data-testid="events-grid" aria-busy="true"></div>

    <div class="text-center py-5" id="events-empty" data-testid="events-empty" hidden>
        <div class="fs-1" aria-hidden="true">🔍</div>
        <h2 class="h5">No events found</h2>
        <p class="text-secondary">Try a different search or clear the filters.</p>
    </div>
    <div class="text-center py-5" id="events-error" data-testid="events-error" hidden>
        <h2 class="h5">Couldn't load events</h2>
        <button class="btn btn-outline-secondary" type="button" data-retry>Retry</button>
    </div>

    <nav class="tt-pager mt-4" id="events-pager" aria-label="Events pages" hidden>
        <button class="btn btn-outline-secondary btn-sm" type="button" data-page-prev data-testid="prev-page-btn">&larr; Prev</button>
        <span class="small text-secondary" data-page-label data-testid="page-label"></span>
        <button class="btn btn-outline-secondary btn-sm" type="button" data-page-next data-testid="next-page-btn">Next &rarr;</button>
    </nav>
</main>
@endsection
