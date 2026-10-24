<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'eventId' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:confirmed,cancelled'],
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

    /**
     * Seats are checked and the booking inserted while the event row is
     * locked, so two parallel requests cannot oversell an event.
     */
    public function store(Request $request): JsonResponse
    {
        $max = config('practice.tickets_max_per_booking');
        $data = $request->validate([
            'eventId' => ['required', 'integer'],
            'customerName' => ['required', 'string', 'min:2', 'max:100'],
            'customerEmail' => ['required', 'email', 'max:255'],
            'customerPhone' => ['required', 'string', 'min:10', 'max:20', 'regex:/^\+?[0-9\s\-()]+$/'],
            'quantity' => ['required', 'integer', 'min:1', "max:{$max}"],
        ], [
            'customerName.min' => 'Name must be at least 2 characters',
            'customerEmail.email' => 'A valid email is required',
            'customerPhone.min' => 'Phone must be at least 10 characters',
            'customerPhone.regex' => 'Phone may only contain digits, spaces, +, - and brackets',
            'quantity.max' => "You can book at most {$max} tickets at once",
        ]);
        $user = $request->user();

        $result = DB::transaction(function () use ($data, $user) {
            $event = Event::visibleTo($user)->lockForUpdate()->find($data['eventId']);

            if (! $event) {
                return response()->json(['success' => false, 'error' => "Event with id {$data['eventId']} not found"], 404);
            }

            $available = $event->availableSeats();
            if ($data['quantity'] > $available) {
                $error = $available === 0 ? 'This event is sold out' : "Only {$available} seat(s) available";

                return response()->json(['success' => false, 'error' => $error], 400);
            }

            $this->makeRoomForBooking($user);

            return $user->bookings()->create([
                'booking_ref' => Booking::newReference(),
                'event_id' => $event->id,
                'customer_name' => trim($data['customerName']),
                'customer_email' => $data['customerEmail'],
                'customer_phone' => trim($data['customerPhone']),
                'quantity' => $data['quantity'],
                'total_price' => round($event->price * $data['quantity'], 2),
                'status' => 'confirmed',
            ]);
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json([
            'success' => true,
            'data' => $result->toApi($this->eventFor($user, $result->event_id)),
            'message' => 'Booking confirmed!',
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $booking = ctype_digit($id) ? $request->user()->bookings()->find((int) $id) : null;

        if (! $booking) {
            return response()->json(['success' => false, 'error' => "Booking with id {$id} not found"], 404);
        }

        return response()->json(['success' => true, 'data' => $booking->toApi($this->eventFor($request->user(), $booking->event_id))]);
    }

    public function showByRef(Request $request, string $ref): JsonResponse
    {
        $booking = $request->user()->bookings()->where('booking_ref', strtoupper($ref))->first();

        if (! $booking) {
            return response()->json(['success' => false, 'error' => "Booking with reference \"{$ref}\" not found"], 404);
        }

        return response()->json(['success' => true, 'data' => $booking->toApi($this->eventFor($request->user(), $booking->event_id))]);
    }

    /** Cancelling deletes the booking, which releases its seats. */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $booking = ctype_digit($id) ? $request->user()->bookings()->find((int) $id) : null;

        if (! $booking) {
            return response()->json(['success' => false, 'error' => "Booking with id {$id} not found"], 404);
        }

        $booking->delete();

        return response()->json(['success' => true, 'message' => 'Booking cancelled']);
    }

    public function clear(Request $request): JsonResponse
    {
        $deleted = $request->user()->bookings()->delete();

        return response()->json(['success' => true, 'message' => "{$deleted} booking(s) cleared", 'deleted' => $deleted]);
    }

    private function makeRoomForBooking(User $user): void
    {
        $excess = $user->bookings()->count() - config('practice.tickets_max_bookings') + 1;

        if ($excess > 0) {
            $user->bookings()->oldest()->oldest('id')->limit($excess)->get()->each->delete();
        }
    }

    private function eventFor(User $user, int $eventId): ?Event
    {
        return Event::visibleTo($user)->find($eventId);
    }
}
