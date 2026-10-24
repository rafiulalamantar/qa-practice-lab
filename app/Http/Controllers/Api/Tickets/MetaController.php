<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\TicketsOpenApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Public, unauthenticated endpoints: health check, sandbox limits and the
 * OpenAPI spec. Their response shapes are fixed by the public API, so they do
 * not use TicketsController's {success: true} envelope.
 */
class MetaController extends Controller
{
    public function health(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
            $db = 'connected';
        } catch (Throwable) {
            $db = 'disconnected';
        }

        return response()->json(['status' => 'ok', 'timestamp' => now()->toJSON(), 'dbStatus' => $db], $db === 'connected' ? 200 : 503);
    }

    public function config(): JsonResponse
    {
        return response()->json([
            'maxCustomEvents' => config('practice.tickets_max_events'),
            'maxBookings' => config('practice.tickets_max_bookings'),
            'maxTicketsPerBooking' => config('practice.tickets_max_per_booking'),
            'categories' => Event::CATEGORIES,
        ]);
    }

    public function openapi(): JsonResponse
    {
        return response()->json(TicketsOpenApi::spec(url('/api/tickets')), options: JSON_UNESCAPED_SLASHES);
    }
}
