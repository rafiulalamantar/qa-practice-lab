<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

/**
 * Featured TicketTrail events: shared by every learner and read-only.
 * Fixed dates keep test assertions stable across reseeds.
 */
class EventSeeder extends Seeder
{
    public const EVENTS = [
        ['CloudScale Engineering Summit', 'Conference', 'Agargaon Convention Hall', 'Dhaka', '2027-02-18 09:30:00', 1200, 500,
            'Two days of talks on distributed systems, platform engineering and reliability from teams running software at scale.'],
        ['Monsoon Beats Live', 'Concert', 'Patenga Seaside Arena', 'Chattogram', '2027-03-06 19:00:00', 2500, 2000,
            'An open-air night of indie, electronic and fusion acts with a late-night DJ set by the sea.'],
        ['Capital City Half Marathon', 'Sports', 'Hatirjheel Lakeside Track', 'Dhaka', '2027-01-24 05:30:00', 350, 1500,
            '21 km route through the heart of the city. Timing chip, finisher medal and breakfast included.'],
        ['Playwright in a Day', 'Workshop', 'Sylhet Hi-Tech Park Hub', 'Sylhet', '2027-02-02 10:00:00', 499, 8,
            'Hands-on workshop: locators, auto-waiting, fixtures and API testing with Playwright. Only a few seats.'],
        ['Festival of Lanterns', 'Festival', 'Padma Riverside Grounds', 'Rajshahi', '2027-11-12 17:00:00', 150, 5000,
            'Thousands of lanterns, folk music, food stalls and craft markets along the Padma river bank.'],
        ['Testing Leaders Roundtable', 'Conference', 'Khan Jahan Ali Tech Studio', 'Khulna', '2027-04-15 16:00:00', 0, 40,
            'A free evening meetup for QA leads: test strategy, hiring and building automation culture.'],
    ];

    public function run(): void
    {
        foreach (self::EVENTS as [$title, $category, $venue, $city, $date, $price, $seats, $description]) {
            Event::updateOrCreate(['title' => $title, 'user_id' => null], [
                'category' => $category,
                'venue' => $venue,
                'city' => $city,
                'event_date' => $date,
                'price' => $price,
                'total_seats' => $seats,
                'description' => $description,
                'image_url' => 'images/events/'.strtolower($category).'.svg',
            ]);
        }
    }
}
