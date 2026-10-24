<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'booking_ref', 'user_id', 'event_id', 'customer_name', 'customer_email',
    'customer_phone', 'quantity', 'total_price', 'status',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'total_price' => 'decimal:2', 'status' => BookingStatus::class];
    }

    /** Reference shown to learners, e.g. TKT-7Q2M9X. */
    public static function newReference(): string
    {
        do {
            $ref = 'TKT-'.Str::upper(Str::random(6));
        } while (static::where('booking_ref', $ref)->exists());

        return $ref;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @param  Event|null  $event  the event loaded with the viewer's booked seats
     */
    public function toApi(?Event $event = null): array
    {
        $event ??= $this->event;

        return [
            'id' => $this->id,
            'eventId' => $this->event_id,
            'customerName' => $this->customer_name,
            'customerEmail' => $this->customer_email,
            'customerPhone' => $this->customer_phone,
            'quantity' => $this->quantity,
            'totalPrice' => (float) $this->total_price,
            'status' => $this->status->value,
            'bookingRef' => $this->booking_ref,
            'createdAt' => $this->created_at?->toJSON(),
            'updatedAt' => $this->updated_at?->toJSON(),
            'event' => $event?->toApi(),
        ];
    }
}
