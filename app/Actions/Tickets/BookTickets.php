<?php

namespace App\Actions\Tickets;

use App\Enums\BookingStatus;
use App\Exceptions\TicketsApiException;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Books tickets for an event: the business rules behind POST /api/tickets/bookings.
 *
 * The seat check and the insert run while the event row is locked
 * (lockForUpdate), so two parallel requests cannot oversell an event.
 */
class BookTickets
{
    /**
     * @param  array{eventId: int, customerName: string, customerEmail: string, customerPhone: string, quantity: int}  $details
     *
     * @throws TicketsApiException when the event is missing or has too few seats
     */
    public function handle(User $user, array $details): Booking
    {
        return DB::transaction(function () use ($user, $details) {
            $event = Event::visibleTo($user)->lockForUpdate()->find($details['eventId']);

            if (! $event) {
                throw TicketsApiException::notFound("Event with id {$details['eventId']} not found");
            }

            $available = $event->availableSeats();

            if ($details['quantity'] > $available) {
                throw new TicketsApiException($available === 0 ? 'This event is sold out' : "Only {$available} seat(s) available");
            }

            $user->makeRoomFor('bookings', config('practice.tickets_max_bookings'));

            return $user->bookings()->create([
                'booking_ref' => Booking::newReference(),
                'event_id' => $event->id,
                'customer_name' => trim($details['customerName']),
                'customer_email' => $details['customerEmail'],
                'customer_phone' => trim($details['customerPhone']),
                'quantity' => $details['quantity'],
                'total_price' => round($event->price * $details['quantity'], 2),
                'status' => BookingStatus::Confirmed,
            ]);
        });
    }
}
