<?php

namespace App\Models;

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'title', 'description', 'category', 'venue', 'city',
    'event_date', 'price', 'total_seats', 'image_url',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    public const CATEGORIES = ['Conference', 'Concert', 'Sports', 'Workshop', 'Festival'];

    protected function casts(): array
    {
        return ['event_date' => 'datetime', 'price' => 'decimal:2', 'total_seats' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Featured events plus the user's own events. Seats are counted per user,
     * so every learner sees a featured event with its full capacity.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))
            ->withSum(['bookings as booked_seats' => fn (Builder $q) => $q->where('user_id', $user->id)], 'quantity');
    }

    public function isFeatured(): bool
    {
        return $this->user_id === null;
    }

    public function availableSeats(): int
    {
        return max(0, $this->total_seats - (int) $this->booked_seats);
    }

    public function toApi(): array
    {
        $image = $this->image_url;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'venue' => $this->venue,
            'city' => $this->city,
            'eventDate' => $this->event_date->toJSON(),
            'price' => (float) $this->price,
            'totalSeats' => $this->total_seats,
            'availableSeats' => $this->availableSeats(),
            'imageUrl' => $image && ! str_starts_with($image, 'http') ? asset($image) : $image,
            'isStatic' => $this->isFeatured(),
            'createdAt' => $this->created_at?->toJSON(),
            'updatedAt' => $this->updated_at?->toJSON(),
        ];
    }
}
