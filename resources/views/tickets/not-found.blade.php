@extends('tickets.layout', ['page' => 'not-found'])

@section('tt-title', 'Page not found')

@section('tickets')
<main class="tt-404" data-testid="not-found-page">
    <div class="container" style="max-width: 720px">
        <a class="tt-brand mb-5 d-inline-flex" href="{{ url('/tickets') }}"><span class="tt-logo" aria-hidden="true"><i class="bi bi-ticket-perforated-fill"></i></span> TicketTrail</a>

        <div class="tt-404-ticket" aria-hidden="true">
            <div class="tt-404-main"><small>Admit one</small><strong>404</strong><small>Row ? · Seat ?</small></div>
            <div class="tt-404-stub"><i class="bi bi-question-lg"></i></div>
        </div>

        <h1 class="mt-5">This page isn't on the programme</h1>
        <p class="lead text-secondary">We couldn't find <code class="tt-404-path" data-testid="not-found-path">{{ '/'.request()->path() }}</code>. The link may be old, or the page may have moved.</p>

        <form class="tt-search tt-search-light mt-4" action="{{ url('/tickets/events') }}" method="get" role="search" data-testid="not-found-search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" name="search" placeholder="Search events instead…" aria-label="Search events" data-testid="not-found-search-input">
            <button class="btn btn-tt" type="submit">Search</button>
        </form>

        <div class="d-flex flex-wrap gap-2 mt-4">
            <a class="btn btn-tt" href="{{ url('/tickets') }}" data-testid="not-found-home-btn"><i class="bi bi-house me-1"></i> Back to home</a>
            <a class="btn btn-outline-secondary" href="{{ url('/tickets/events') }}" data-testid="not-found-events-btn">Browse events</a>
            <a class="btn btn-outline-secondary" href="{{ url('/tickets/bookings') }}">My bookings</a>
            <button class="btn btn-link" type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/tickets') }}')" data-testid="not-found-back-btn"><i class="bi bi-arrow-left me-1"></i> Go back</button>
        </div>
    </div>
</main>
@endsection
