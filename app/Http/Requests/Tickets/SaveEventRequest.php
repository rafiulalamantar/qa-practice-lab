<?php

namespace App\Http\Requests\Tickets;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of POST and PUT /api/tickets/events (PUT replaces the whole event, so
 * both use the same rules). Ownership is checked in the controller.
 */
class SaveEventRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', Rule::in(Event::CATEGORIES)],
            'venue' => ['required', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:80'],
            'eventDate' => ['required', 'date', 'after:now'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'totalSeats' => ['required', 'integer', 'min:1', 'max:100000'],
            'imageUrl' => ['nullable', 'url:http,https', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category.in' => 'Category must be one of: '.implode(', ', Event::CATEGORIES),
            'eventDate.after' => 'Event date must be in the future',
        ];
    }

    /**
     * The validated body mapped from the API's camelCase names to the
     * events table's columns.
     *
     * @return array<string, mixed>
     */
    public function eventAttributes(): array
    {
        $data = $this->validated();

        return [
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'category' => $data['category'],
            'venue' => trim($data['venue']),
            'city' => trim($data['city']),
            'event_date' => now()->parse($data['eventDate'])->utc(),
            'price' => $data['price'],
            'total_seats' => $data['totalSeats'],
            'image_url' => $data['imageUrl'] ?? null,
        ];
    }
}
