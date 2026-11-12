<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Exceptions\TicketsApiException;
use App\Http\Requests\Tickets\SaveEventRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * /api/tickets/events: the shared featured events plus the learner's own
 * custom events. Featured events (user_id = null) are read-only.
 */
class EventController extends TicketsController
{
    /**
     * Allowed `sort` values for the events list, as [column, direction].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const SORTS = [
        'date' => ['event_date', 'asc'],
        '-date' => ['event_date', 'desc'],
        'price' => ['price', 'asc'],
        '-price' => ['price', 'desc'],
        'title' => ['title', 'asc'],
    ];

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(Event::CATEGORIES)],
            'city' => ['nullable', 'string', 'max:80'],
            'search' => ['nullable', 'string', 'max:100'],
            'minPrice' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'maxPrice' => ['nullable', 'numeric', 'min:0', 'max:1000000', Rule::when($request->filled('minPrice'), 'gte:minPrice')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', Rule::when($request->filled('from'), 'after_or_equal:from')],
            'availableOnly' => ['nullable', 'in:0,1,true,false'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            ...$this->paginationRules(),
        ], [
            'minPrice.numeric' => 'minPrice must be a number',
            'minPrice.min' => 'minPrice must be 0 or more',
            'minPrice.max' => 'minPrice must be 1000000 or less',
            'maxPrice.numeric' => 'maxPrice must be a number',
            'maxPrice.min' => 'maxPrice must be 0 or more',
            'maxPrice.max' => 'maxPrice must be 1000000 or less',
            'from.date' => 'from must be a valid date, e.g. 2027-03-07',
            'to.date' => 'to must be a valid date, e.g. 2027-03-07',
            'maxPrice.gte' => 'maxPrice must be greater than or equal to minPrice',
            'to.after_or_equal' => 'to must be on or after from',
            'availableOnly.in' => 'availableOnly must be true or false',
            'sort.in' => 'sort must be one of: '.implode(', ', array_keys(self::SORTS)),
        ]);
        $user = $request->user();
        [$sortColumn, $sortDirection] = self::SORTS[$filters['sort'] ?? 'date'];

        $query = Event::visibleTo($user)
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($filters['city'] ?? null, fn ($q, $city) => $q->whereRaw('LOWER(city) = ?', [mb_strtolower(trim($city))]))
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->search($term))
            ->when(isset($filters['minPrice']), fn ($q) => $q->where('price', '>=', $filters['minPrice']))
            ->when(isset($filters['maxPrice']), fn ($q) => $q->where('price', '<=', $filters['maxPrice']))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('event_date', '>=', $this->dateBound($from, endOfDay: false)))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('event_date', '<=', $this->dateBound($to, endOfDay: true)))
            ->when($request->boolean('availableOnly'), fn ($q) => $q->withSeatsLeftFor($user))
            ->orderBy($sortColumn, $sortDirection)->orderBy('id');

        return $this->paginated($query, $filters, fn (Event $event) => $event->toApi());
    }

    /**
     * A plain date (2027-03-07) covers that whole day in UTC. A date-time with
     * an offset (2027-03-07T00:00:00+06:00) is used as the exact instant, so
     * the UI can filter by the visitor's own calendar day.
     */
    private function dateBound(string $value, bool $endOfDay): Carbon
    {
        $moment = Carbon::parse($value)->utc();

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $endOfDay ? $moment->endOfDay() : $moment->startOfDay();
        }

        return $moment;
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
