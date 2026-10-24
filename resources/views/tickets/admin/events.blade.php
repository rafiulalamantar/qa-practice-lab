@extends('tickets.layout', ['page' => 'admin-events'])

@section('nav', true)
@section('tt-title', 'Manage Events')

@section('tickets')
<main class="container-xxl px-3 px-lg-4">
    <header class="tt-page-head mb-4">
        <span class="tt-eyebrow"><i class="bi bi-sliders"></i> Manage</span>
        <h1 class="mt-1">Manage Events</h1>
        <p class="text-secondary mb-0">Create, edit and delete your own events. Featured events are read-only.</p>
    </header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="tt-card tt-kpi"><small><i class="bi bi-calendar-event"></i> All events</small><strong id="kpi-total" data-testid="kpi-total">–</strong></div></div>
        <div class="col-6 col-lg-3"><div class="tt-card tt-kpi"><small><i class="bi bi-person-workspace" style="background:#e4f2fc;color:#0b7fd1"></i> Your events</small><strong id="kpi-custom" data-testid="kpi-custom">–</strong><span>max {{ config('practice.tickets_max_events') }}</span></div></div>
        <div class="col-6 col-lg-3"><div class="tt-card tt-kpi"><small><i class="bi bi-star" style="background:#fff1de;color:#e07a00"></i> Featured</small><strong id="kpi-featured" data-testid="kpi-featured">–</strong><span>read-only</span></div></div>
        <div class="col-6 col-lg-3"><div class="tt-card tt-kpi"><small><i class="bi bi-slash-circle" style="background:#fdecec;color:#e5484d"></i> Sold out</small><strong id="kpi-soldout" data-testid="kpi-soldout">–</strong></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4 col-xxl-3">
            <form class="tt-card tt-form-card" id="admin-event-form" data-testid="admin-event-form" novalidate>
                <h2 class="h5 mb-1" id="event-form-title">Add New Event</h2>
                <p class="small text-secondary mb-4">Fields marked <span class="text-danger">*</span> are required.</p>
                <div class="mb-3">
                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                    <input id="title" name="title" class="form-control" placeholder="Event title" data-testid="event-title-input">
                    <div class="invalid-feedback" data-error-for="title"></div>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="Describe the event…" data-testid="event-description-input"></textarea>
                    <div class="invalid-feedback" data-error-for="description"></div>
                </div>
                <div class="row g-2">
                    <div class="col-6 mb-3">
                        <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                        <select id="category" name="category" class="form-select" data-testid="event-category-input">
                            <option value="">Select…</option>
                            @foreach (\App\Models\Event::CATEGORIES as $category)
                                <option value="{{ $category }}">{{ $category }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" data-error-for="category"></div>
                    </div>
                    <div class="col-6 mb-3">
                        <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                        <input id="city" name="city" class="form-control" placeholder="e.g. Dhaka" data-testid="event-city-input">
                        <div class="invalid-feedback" data-error-for="city"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="venue" class="form-label">Venue <span class="text-danger">*</span></label>
                    <input id="venue" name="venue" class="form-control" placeholder="Venue name &amp; address" data-testid="event-venue-input">
                    <div class="invalid-feedback" data-error-for="venue"></div>
                </div>
                <div class="mb-3">
                    <label for="eventDate" class="form-label">Date &amp; time <span class="text-danger">*</span></label>
                    <input id="eventDate" name="eventDate" type="datetime-local" class="form-control" data-testid="event-date-input">
                    <div class="invalid-feedback" data-error-for="eventDate"></div>
                </div>
                <div class="row g-2">
                    <div class="col-6 mb-3">
                        <label for="price" class="form-label">Price (৳) <span class="text-danger">*</span></label>
                        <input id="price" name="price" type="number" min="0" step="0.01" class="form-control" placeholder="0.00" data-testid="event-price-input">
                        <div class="invalid-feedback" data-error-for="price"></div>
                    </div>
                    <div class="col-6 mb-3">
                        <label for="totalSeats" class="form-label">Total seats <span class="text-danger">*</span></label>
                        <input id="totalSeats" name="totalSeats" type="number" min="1" step="1" class="form-control" placeholder="e.g. 500" data-testid="event-seats-input">
                        <div class="invalid-feedback" data-error-for="totalSeats"></div>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="imageUrl" class="form-label">Image URL</label>
                    <input id="imageUrl" name="imageUrl" type="url" class="form-control" placeholder="https://…" data-testid="event-image-input">
                    <div class="invalid-feedback" data-error-for="imageUrl"></div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-tt flex-grow-1" id="add-event-btn" data-testid="add-event-btn">Add Event</button>
                    <button type="button" class="btn btn-outline-secondary" id="cancel-edit-btn" data-testid="cancel-edit-btn" hidden>Cancel edit</button>
                </div>
            </form>
        </div>

        <div class="col-lg-8 col-xxl-9">
            <div class="tt-card p-0 overflow-hidden">
                <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                    <h2 class="h5 mb-0">All Events</h2>
                    <span class="tt-chip" style="background:var(--tt-bg)" id="admin-events-count" data-testid="admin-events-count"></span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 tt-table" id="admin-events-table" data-testid="admin-events-table">
                        <thead><tr><th scope="col">Event</th><th scope="col">Date</th><th scope="col">Price</th><th scope="col">Seats</th><th scope="col" class="text-end">Actions</th></tr></thead>
                        <tbody id="admin-events-body"></tbody>
                    </table>
                </div>
                <div class="tt-empty" id="admin-events-empty" hidden><div class="tt-empty-icon"><i class="bi bi-calendar-x"></i></div><h3 class="h6">No events yet</h3></div>
                <nav class="tt-pager p-3 border-top" id="admin-events-pager" aria-label="Events pages" hidden>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-page-prev data-testid="prev-page-btn"><i class="bi bi-arrow-left me-1"></i> Prev</button>
                    <span class="small text-secondary" data-page-label data-testid="page-label"></span>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-page-next data-testid="next-page-btn">Next <i class="bi bi-arrow-right ms-1"></i></button>
                </nav>
            </div>
        </div>
    </div>
</main>
@endsection
