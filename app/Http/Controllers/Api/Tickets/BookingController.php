<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Actions\Tickets\BookTickets;
use App\Enums\BookingStatus;
use App\Exceptions\TicketsApiException;
use App\Http\Requests\Tickets\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * /api/tickets/bookings: a learner only ever sees their own bookings.
 */
class BookingController extends TicketsController
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'eventId' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(BookingStatus::class)],
            ...$this->paginationRules(),
        ]);
        $user = $request->user();

        $query = $user->bookings()->getQuery()
            ->with(['event' => fn ($q) => $q->visibleTo($user)])
            ->when($filters['eventId'] ?? null, fn ($q, $eventId) => $q->where('event_id', $eventId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()->latest('id');

        return $this->paginated($query, $filters, fn (Booking $booking) => $booking->toApi());
    }

    public function store(StoreBookingRequest $request, BookTickets $bookTickets): JsonResponse
    {
        $user = $request->user();
        $booking = $bookTickets->handle($user, $request->validated());

        return $this->success([
            'data' => $booking->toApi($this->eventWithSeats($user, $booking)),
            'message' => 'Booking confirmed!',
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $booking = $this->findOwnBooking($request->user(), $id);

        return $this->success(['data' => $booking->toApi($this->eventWithSeats($request->user(), $booking))]);
    }

    public function showByRef(Request $request, string $ref): JsonResponse
    {
        $booking = $request->user()->bookings()->where('booking_ref', strtoupper($ref))->first()
            ?? throw TicketsApiException::notFound("Booking with reference \"{$ref}\" not found");

        return $this->success(['data' => $booking->toApi($this->eventWithSeats($request->user(), $booking))]);
    }

    /** Cancelling deletes the booking, which releases its seats. */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->findOwnBooking($request->user(), $id)->delete();

        return $this->success(['message' => 'Booking cancelled']);
    }

    public function clear(Request $request): JsonResponse
    {
        $deleted = $request->user()->bookings()->delete();

        return $this->success(['message' => "{$deleted} booking(s) cleared", 'deleted' => $deleted]);
    }

    /**
     * Cancel several of the user's bookings at once. Ids that are not the
     * user's (or no longer exist) are reported back instead of failing.
     */
    public function bulkCancel(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'min:1', 'distinct'],
        ], [
            'ids.required' => 'ids must be a non-empty array of booking ids',
            'ids.array' => 'ids must be a non-empty array of booking ids',
            'ids.min' => 'ids must be a non-empty array of booking ids',
            'ids.max' => 'You can cancel at most 100 bookings at once',
            'ids.*.integer' => 'Each booking id must be an integer',
            'ids.*.min' => 'Each booking id must be a positive integer',
            'ids.*.distinct' => 'Booking ids must be unique',
        ])['ids'];

        $ownIds = $request->user()->bookings()->whereIn('id', $ids)->pluck('id')->all();
        $cancelled = $request->user()->bookings()->whereIn('id', $ownIds)->delete();

        return $this->success([
            'message' => "{$cancelled} booking(s) cancelled",
            'cancelled' => $cancelled,
            'notFound' => array_values(array_diff(array_map('intval', $ids), $ownIds)),
        ]);
    }

    private function findOwnBooking(User $user, string $id): Booking
    {
        $booking = ctype_digit($id) ? $user->bookings()->find((int) $id) : null;

        return $booking ?? throw TicketsApiException::notFound("Booking with id {$id} not found");
    }

    /** The booking's event, loaded with this user's booked seat count. */
    private function eventWithSeats(User $user, Booking $booking): ?Event
    {
        return Event::visibleTo($user)->find($booking->event_id);
    }
}
