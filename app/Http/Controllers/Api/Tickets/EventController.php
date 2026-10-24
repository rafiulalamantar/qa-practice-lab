<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Exceptions\TicketsApiException;
use App\Http\Requests\Tickets\SaveEventRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * /api/tickets/events: the shared featured events plus the learner's own
 * custom events. Featured events (user_id = null) are read-only.
 */
class EventController extends TicketsController
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(Event::CATEGORIES)],
            'city' => ['nullable', 'string', 'max:80'],
            'search' => ['nullable', 'string', 'max:100'],
            ...$this->paginationRules(),
        ]);

        $query = Event::visibleTo($request->user())
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($filters['city'] ?? null, fn ($q, $city) => $q->whereRaw('LOWER(city) = ?', [mb_strtolower(trim($city))]))
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->search($term))
            ->orderBy('event_date')->orderBy('id');

        return $this->paginated($query, $filters, fn (Event $event) => $event->toApi());
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return $this->success(['data' => $this->findVisibleEvent($request->user(), $id)->toApi()]);
    }

    public function store(SaveEventRequest $request): JsonResponse
    {
        $user = $request->user();

        $event = DB::transaction(function () use ($request, $user) {
            $user->makeRoomFor('events', config('practice.tickets_max_events'));

            return $user->events()->create($request->eventAttributes());
        });

        return $this->success([
            'data' => Event::visibleTo($user)->find($event->id)->toApi(),
            'message' => 'Event created successfully',
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $event = $this->findEditableEvent($request->user(), $id);

        // Resolved here rather than in the method signature so that a missing
        // or read-only event answers 404/403 before the body is validated.
        $input = app(SaveEventRequest::class);

        if ($input->validated('totalSeats') < (int) $event->booked_seats) {
            throw ValidationException::withMessages([
                'totalSeats' => "totalSeats cannot be less than the {$event->booked_seats} seat(s) already booked",
            ]);
        }

        $event->update($input->eventAttributes());

        return $this->success([
            'data' => Event::visibleTo($request->user())->find($event->id)->toApi(),
            'message' => 'Event updated successfully',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->findEditableEvent($request->user(), $id)->delete();

        return $this->success(['message' => 'Event deleted successfully']);
    }

    private function findVisibleEvent(User $user, string $id): Event
    {
        $event = ctype_digit($id) ? Event::visibleTo($user)->find((int) $id) : null;

        return $event ?? throw TicketsApiException::notFound("Event with id {$id} not found");
    }

    private function findEditableEvent(User $user, string $id): Event
    {
        $event = $this->findVisibleEvent($user, $id);

        if ($event->isFeatured()) {
            throw TicketsApiException::forbidden('Featured events are read-only');
        }

        return $event;
    }
}
