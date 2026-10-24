<?php

namespace App\Enums;

/**
 * Every booking is created as Confirmed. Cancelling a booking deletes it (which
 * frees its seats), so Cancelled exists only because the public API documents
 * it as a filter value.
 */
enum BookingStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
