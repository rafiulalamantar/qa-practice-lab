<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_ref' => fn () => Booking::newReference(),
            'user_id' => User::factory(),
            'event_id' => Event::factory()->featured(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => '01712345678',
            'quantity' => 1,
            'total_price' => 500,
            'status' => 'confirmed',
        ];
    }
}
