<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of POST /api/tickets/bookings. Any logged-in user may book, so there is
 * no authorize() check beyond the auth:sanctum middleware.
 */
class StoreBookingRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $max = config('practice.tickets_max_per_booking');

        return [
            'eventId' => ['required', 'integer'],
            'customerName' => ['required', 'string', 'min:2', 'max:100'],
            'customerEmail' => ['required', 'email', 'max:255'],
            'customerPhone' => ['required', 'string', 'min:10', 'max:20', 'regex:/^\+?[0-9\s\-()]+$/'],
            'quantity' => ['required', 'integer', 'min:1', "max:{$max}"],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $max = config('practice.tickets_max_per_booking');

        return [
            'customerName.min' => 'Name must be at least 2 characters',
            'customerEmail.email' => 'A valid email is required',
            'customerPhone.min' => 'Phone must be at least 10 characters',
            'customerPhone.regex' => 'Phone may only contain digits, spaces, +, - and brackets',
            'quantity.max' => "You can book at most {$max} tickets at once",
        ];
    }
}
