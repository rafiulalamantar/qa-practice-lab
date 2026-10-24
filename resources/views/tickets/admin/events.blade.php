@extends('tickets.layout', ['page' => 'admin-events'])

@section('nav', true)
@section('tt-title', 'Manage Events')

@section('tickets')
<main class="container-xxl py-4">
    <h1 class="h3 mb-4">Manage Events</h1>
    <div class="row g-4">
        <div class="col-lg-4">
            <form class="tt-card p-4" id="admin-event-form" data-testid="admin-event-form" novalidate>
                <h2 class="h5 mb-3" id="event-form-title">Add New Event</h2>
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

        <div class="col-lg-8">
            <div class="tt-card p-0 overflow-hidden">
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                    <h2 class="h5 mb-0">All Events</h2>
                    <span class="small text-secondary" id="admin-events-count" data-testid="admin-events-count"></span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 tt-table" id="admin-events-table" data-testid="admin-events-table">
                        <thead><tr><th scope="col">Title</th><th scope="col">Category</th><th scope="col">Date</th><th scope="col">City</th><th scope="col">Price</th><th scope="col">Seats</th><th scope="col">Actions</th></tr></thead>
                        <tbody id="admin-events-body"></tbody>
                    </table>
                </div>
                <div class="text-center text-secondary py-5" id="admin-events-empty" hidden>No events yet</div>
                <nav class="tt-pager p-3 border-top" id="admin-events-pager" aria-label="Events pages" hidden>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-page-prev data-testid="prev-page-btn">&larr; Prev</button>
                    <span class="small text-secondary" data-page-label data-testid="page-label"></span>
                    <button class="btn btn-outline-secondary btn-sm" type="button" data-page-next data-testid="next-page-btn">Next &rarr;</button>
                </nav>
            </div>
        </div>
    </div>
</main>
@endsection
