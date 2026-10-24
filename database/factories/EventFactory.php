<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true).' Live',
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(Event::CATEGORIES),
            'venue' => fake()->company().' Hall',
            'city' => fake()->randomElement(['Dhaka', 'Chattogram', 'Sylhet', 'Khulna']),
            'event_date' => now()->addMonths(2),
            'price' => 500,
            'total_seats' => 100,
        ];
    }

    /** A shared, read-only featured event. */
    public function featured(): static
    {
        return $this->state(['user_id' => null]);
    }
}
