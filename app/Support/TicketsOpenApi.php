<?php

namespace App\Support;

use App\Models\Event;

/**
 * OpenAPI 3 description of the TicketTrail API, rendered by Swagger UI at
 * /tickets/api-docs and importable into Postman.
 */
class TicketsOpenApi
{
    public static function spec(string $serverUrl): array
    {
        $ref = fn (string $name) => ['$ref' => "#/components/schemas/{$name}"];
        $json = fn (array $schema) => ['content' => ['application/json' => ['schema' => $schema]]];
        $error = fn (string $description, string $example) => ['description' => $description] + $json([
            'type' => 'object',
            'properties' => ['success' => ['type' => 'boolean', 'example' => false], 'error' => ['type' => 'string', 'example' => $example]],
        ]);
        $validation = ['description' => 'Validation failed'] + $json($ref('ValidationError'));
        $unauthorized = $error('Missing, invalid or expired token', 'Unauthorized');
        $ok = fn (string $description, array $data, ?string $message = null) => ['description' => $description] + $json([
            'type' => 'object',
            'properties' => array_filter([
                'success' => ['type' => 'boolean', 'example' => true],
                'data' => $data,
                'message' => $message ? ['type' => 'string', 'example' => $message] : null,
            ]),
        ]);
        $list = fn (string $item) => ['description' => 'Paginated list'] + $json([
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean', 'example' => true],
                'data' => ['type' => 'array', 'items' => $ref($item)],
                'pagination' => $ref('Pagination'),
            ],
        ]);
        $path = fn (string $name, string $description) => ['name' => $name, 'in' => 'path', 'required' => true, 'description' => $description, 'schema' => ['type' => $name === 'ref' ? 'string' : 'integer']];
        $query = fn (string $name, array $schema, string $description) => ['name' => $name, 'in' => 'query', 'schema' => $schema, 'description' => $description];
        $paging = [
            $query('page', ['type' => 'integer', 'default' => 1, 'minimum' => 1], 'Page number'),
            $query('limit', ['type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100], 'Items per page'),
        ];
        $body = fn (string $schema) => ['required' => true] + $json($ref($schema));
        $public = ['security' => []];

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'TicketTrail API',
                'version' => '1.0.0',
                'description' => "REST API behind the TicketTrail event-booking practice app.\n\n"
                    .'Log in, then click **Authorize** and paste the token. Every account is an isolated sandbox: featured events are shared and read-only, '
                    .'your own events and bookings are private. Limits: '.config('practice.tickets_max_events').' custom events and '
                    .config('practice.tickets_max_bookings').' bookings per account (the oldest is replaced). Send `X-Chaos-Mode: 1` for random latency.',
            ],
            'servers' => [['url' => $serverUrl]],
            'security' => [['bearerAuth' => []]],
            'tags' => [
                ['name' => 'Auth', 'description' => 'Register, log in and check a token'],
                ['name' => 'Events', 'description' => 'Browse featured events and manage your own'],
                ['name' => 'Bookings', 'description' => 'Book, view and cancel tickets'],
                ['name' => 'Meta', 'description' => 'Health and limits'],
            ],
            'paths' => [
                '/auth/register' => ['post' => $public + [
                    'tags' => ['Auth'], 'summary' => 'Register a new account',
                    'requestBody' => $body('Credentials'),
                    'responses' => ['201' => ['description' => 'Account created'] + $json($ref('AuthResponse')), '400' => $error('Validation error or email already registered', 'Email already registered')],
                ]],
                '/auth/login' => ['post' => $public + [
                    'tags' => ['Auth'], 'summary' => 'Log in and receive a bearer token',
                    'requestBody' => $body('Credentials'),
                    'responses' => ['200' => ['description' => 'Logged in'] + $json($ref('AuthResponse')), '400' => $error('Wrong password or validation error', 'Invalid credentials'), '404' => $error('No account for this email', 'User not found'), '429' => $error('Too many login attempts', 'Too many requests')],
                ]],
                '/auth/me' => ['get' => [
                    'tags' => ['Auth'], 'summary' => 'Who am I? (validates the token)',
                    'responses' => ['200' => ['description' => 'Token is valid'] + $json(['type' => 'object', 'properties' => ['success' => ['type' => 'boolean', 'example' => true], 'user' => ['type' => 'object', 'properties' => ['userId' => ['type' => 'integer', 'example' => 42], 'email' => ['type' => 'string', 'example' => 'student@example.com']]]]]), '401' => $unauthorized],
                ]],
                '/events' => [
                    'get' => [
                        'tags' => ['Events'], 'summary' => 'List events (featured + yours), soonest first',
                        'parameters' => [
                            $query('category', ['type' => 'string', 'enum' => Event::CATEGORIES], 'Filter by category'),
                            $query('city', ['type' => 'string'], 'Filter by city (case-insensitive exact match)'),
                            $query('search', ['type' => 'string'], 'Search title, description and venue'),
                            ...$paging,
                        ],
                        'responses' => ['200' => $list('Event'), '400' => $validation, '401' => $unauthorized],
                    ],
                    'post' => [
                        'tags' => ['Events'], 'summary' => 'Create an event',
                        'description' => 'availableSeats starts equal to totalSeats. When you already have '.config('practice.tickets_max_events').' events, your oldest one (and its bookings) is deleted.',
                        'requestBody' => $body('EventInput'),
                        'responses' => ['201' => $ok('Created', $ref('Event'), 'Event created successfully'), '400' => $validation, '401' => $unauthorized],
                    ],
                ],
                '/events/{id}' => [
                    'parameters' => [$path('id', 'Event id')],
                    'get' => ['tags' => ['Events'], 'summary' => 'Get one event', 'responses' => ['200' => $ok('Found', $ref('Event')), '404' => $error('Not found or owned by someone else', 'Event with id 99 not found'), '401' => $unauthorized]],
                    'put' => [
                        'tags' => ['Events'], 'summary' => 'Update your event', 'requestBody' => $body('EventInput'),
                        'responses' => ['200' => $ok('Updated', $ref('Event'), 'Event updated successfully'), '400' => $validation, '403' => $error('Featured events cannot be changed', 'Featured events are read-only'), '404' => $error('Not found', 'Event with id 99 not found'), '401' => $unauthorized],
                    ],
                    'delete' => [
                        'tags' => ['Events'], 'summary' => 'Delete your event and its bookings',
                        'responses' => ['200' => ['description' => 'Deleted'] + $json($ref('Message')), '403' => $error('Featured events cannot be deleted', 'Featured events are read-only'), '404' => $error('Not found', 'Event with id 99 not found'), '401' => $unauthorized],
                    ],
                ],
                '/bookings' => [
                    'get' => [
                        'tags' => ['Bookings'], 'summary' => 'List your bookings, newest first',
                        'parameters' => [
                            $query('eventId', ['type' => 'integer'], 'Filter by event'),
                            $query('status', ['type' => 'string', 'enum' => ['confirmed', 'cancelled']], 'Filter by status'),
                            ...$paging,
                        ],
                        'responses' => ['200' => $list('Booking'), '400' => $validation, '401' => $unauthorized],
                    ],
                    'post' => [
                        'tags' => ['Bookings'], 'summary' => 'Book tickets',
                        'description' => "Checks the event exists, checks seats, computes the total and creates a TKT-XXXXXX reference, all in one transaction.\nWhen you already have ".config('practice.tickets_max_bookings').' bookings, the oldest is replaced.',
                        'requestBody' => $body('BookingInput'),
                        'responses' => ['201' => $ok('Booked', $ref('Booking'), 'Booking confirmed!'), '400' => $error('Validation error or not enough seats', 'Only 3 seat(s) available'), '404' => $error('Event not found', 'Event with id 99 not found'), '401' => $unauthorized],
                    ],
                    'delete' => [
                        'tags' => ['Bookings'], 'summary' => 'Clear all your bookings (handy before a test run)',
                        'responses' => ['200' => ['description' => 'Cleared'] + $json(['type' => 'object', 'properties' => ['success' => ['type' => 'boolean'], 'message' => ['type' => 'string', 'example' => '3 booking(s) cleared'], 'deleted' => ['type' => 'integer', 'example' => 3]]]), '401' => $unauthorized],
                    ],
                ],
                '/bookings/ref/{ref}' => [
                    'parameters' => [$path('ref', 'Booking reference, e.g. TKT-7Q2M9X')],
                    'get' => ['tags' => ['Bookings'], 'summary' => 'Find a booking by reference', 'responses' => ['200' => $ok('Found', $ref('Booking')), '404' => $error('Not found', 'Booking with reference "TKT-XYZ123" not found'), '401' => $unauthorized]],
                ],
                '/bookings/{id}' => [
                    'parameters' => [$path('id', 'Booking id')],
                    'get' => ['tags' => ['Bookings'], 'summary' => 'Get one booking', 'responses' => ['200' => $ok('Found', $ref('Booking')), '404' => $error('Not found or someone else\'s', 'Booking with id 99 not found'), '401' => $unauthorized]],
                    'delete' => ['tags' => ['Bookings'], 'summary' => 'Cancel a booking and release its seats', 'responses' => ['200' => ['description' => 'Cancelled'] + $json($ref('Message')), '404' => $error('Not found', 'Booking with id 99 not found'), '401' => $unauthorized]],
                ],
                '/health' => ['get' => $public + ['tags' => ['Meta'], 'summary' => 'API and database health', 'responses' => ['200' => ['description' => 'Healthy'] + $json(['type' => 'object', 'properties' => ['status' => ['type' => 'string', 'example' => 'ok'], 'timestamp' => ['type' => 'string', 'format' => 'date-time'], 'dbStatus' => ['type' => 'string', 'example' => 'connected']]])]]],
                '/config' => ['get' => $public + ['tags' => ['Meta'], 'summary' => 'Sandbox limits and categories', 'responses' => ['200' => ['description' => 'Limits'] + $json(['type' => 'object', 'properties' => ['maxCustomEvents' => ['type' => 'integer', 'example' => 6], 'maxBookings' => ['type' => 'integer', 'example' => 9], 'maxTicketsPerBooking' => ['type' => 'integer', 'example' => 10], 'categories' => ['type' => 'array', 'items' => ['type' => 'string']]]])]]],
            ],
            'components' => [
                'securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer']],
                'schemas' => [
                    'Credentials' => ['type' => 'object', 'required' => ['email', 'password'], 'properties' => ['email' => ['type' => 'string', 'format' => 'email', 'example' => config('practice.demo_email')], 'password' => ['type' => 'string', 'minLength' => 6, 'example' => config('practice.demo_password')]]],
                    'AuthResponse' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean', 'example' => true], 'token' => ['type' => 'string', 'example' => '12|Qm9vay…'], 'user' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer', 'example' => 42], 'email' => ['type' => 'string', 'example' => 'student@example.com']]]]],
                    'Event' => ['type' => 'object', 'properties' => [
                        'id' => ['type' => 'integer', 'example' => 1],
                        'title' => ['type' => 'string', 'example' => 'CloudScale Engineering Summit'],
                        'description' => ['type' => 'string', 'nullable' => true],
                        'category' => ['type' => 'string', 'enum' => Event::CATEGORIES],
                        'venue' => ['type' => 'string', 'example' => 'Agargaon Convention Hall'],
                        'city' => ['type' => 'string', 'example' => 'Dhaka'],
                        'eventDate' => ['type' => 'string', 'format' => 'date-time'],
                        'price' => ['type' => 'number', 'example' => 1200, 'description' => 'Ticket price in Bangladeshi Taka (BDT); 0 means free'],
                        'totalSeats' => ['type' => 'integer', 'example' => 500],
                        'availableSeats' => ['type' => 'integer', 'example' => 497],
                        'imageUrl' => ['type' => 'string', 'nullable' => true],
                        'isStatic' => ['type' => 'boolean', 'description' => 'true for featured, read-only events'],
                        'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                        'updatedAt' => ['type' => 'string', 'format' => 'date-time'],
                    ]],
                    'EventInput' => ['type' => 'object', 'required' => ['title', 'category', 'venue', 'city', 'eventDate', 'price', 'totalSeats'], 'properties' => [
                        'title' => ['type' => 'string', 'minLength' => 3, 'example' => 'Selenium Grid Bootcamp'],
                        'description' => ['type' => 'string', 'example' => 'Hands-on day of parallel browser testing.'],
                        'category' => ['type' => 'string', 'enum' => Event::CATEGORIES, 'example' => 'Workshop'],
                        'venue' => ['type' => 'string', 'example' => 'Sylhet Hi-Tech Park, Hall B'],
                        'city' => ['type' => 'string', 'example' => 'Sylhet'],
                        'eventDate' => ['type' => 'string', 'format' => 'date-time', 'example' => now()->addMonths(3)->startOfHour()->toJSON(), 'description' => 'Must be in the future'],
                        'price' => ['type' => 'number', 'minimum' => 0, 'example' => 799, 'description' => 'Ticket price in BDT'],
                        'totalSeats' => ['type' => 'integer', 'minimum' => 1, 'example' => 50],
                        'imageUrl' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                    ]],
                    'Booking' => ['type' => 'object', 'properties' => [
                        'id' => ['type' => 'integer', 'example' => 7],
                        'eventId' => ['type' => 'integer', 'example' => 1],
                        'customerName' => ['type' => 'string', 'example' => 'Nusrat Jahan'],
                        'customerEmail' => ['type' => 'string', 'example' => 'nusrat@example.com'],
                        'customerPhone' => ['type' => 'string', 'example' => '+880 1712-345678'],
                        'quantity' => ['type' => 'integer', 'example' => 2],
                        'totalPrice' => ['type' => 'number', 'example' => 2400, 'description' => 'Total in BDT'],
                        'status' => ['type' => 'string', 'enum' => ['confirmed', 'cancelled']],
                        'bookingRef' => ['type' => 'string', 'example' => 'TKT-7Q2M9X'],
                        'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                        'updatedAt' => ['type' => 'string', 'format' => 'date-time'],
                        'event' => $ref('Event'),
                    ]],
                    'BookingInput' => ['type' => 'object', 'required' => ['eventId', 'customerName', 'customerEmail', 'customerPhone', 'quantity'], 'properties' => [
                        'eventId' => ['type' => 'integer', 'example' => 1],
                        'customerName' => ['type' => 'string', 'minLength' => 2, 'example' => 'Nusrat Jahan'],
                        'customerEmail' => ['type' => 'string', 'format' => 'email', 'example' => 'nusrat@example.com'],
                        'customerPhone' => ['type' => 'string', 'minLength' => 10, 'example' => '+880 1712-345678'],
                        'quantity' => ['type' => 'integer', 'minimum' => 1, 'maximum' => config('practice.tickets_max_per_booking'), 'example' => 2],
                    ]],
                    'Pagination' => ['type' => 'object', 'properties' => ['total' => ['type' => 'integer', 'example' => 14], 'page' => ['type' => 'integer', 'example' => 1], 'limit' => ['type' => 'integer', 'example' => 10], 'totalPages' => ['type' => 'integer', 'example' => 2]]],
                    'Message' => ['type' => 'object', 'properties' => ['success' => ['type' => 'boolean', 'example' => true], 'message' => ['type' => 'string']]],
                    'ValidationError' => ['type' => 'object', 'properties' => [
                        'success' => ['type' => 'boolean', 'example' => false],
                        'error' => ['type' => 'string', 'example' => 'Validation failed'],
                        'details' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['field' => ['type' => 'string', 'example' => 'customerEmail'], 'message' => ['type' => 'string', 'example' => 'A valid email is required']]]],
                    ]],
                ],
            ],
        ];
    }
}
