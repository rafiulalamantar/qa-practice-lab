<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    use Paginates;

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
            ->when($filters['search'] ?? null, function ($q, $term) {
                $like = '%'.mb_strtolower(trim($term)).'%';
                $q->where(fn ($q) => $q->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(venue) LIKE ?', [$like]));
            })
            ->orderBy('event_date')->orderBy('id');

        return $this->paginated($query, $filters, fn (Event $event) => $event->toApi());
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $event = $this->findVisible($request, $id);

        return $event
            ? response()->json(['success' => true, 'data' => $event->toApi()])
            : $this->notFound($id);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateEvent($request);
        $user = $request->user();

        $event = DB::transaction(function () use ($data, $user) {
            // Keep each sandbox small: the oldest custom event makes room.
            $excess = $user->events()->count() - config('practice.tickets_max_events') + 1;
            if ($excess > 0) {
                $user->events()->oldest()->oldest('id')->limit($excess)->get()->each->delete();
            }

            return $user->events()->create($this->attributes($data));
        });

        return response()->json([
            'success' => true,
            'data' => Event::visibleTo($user)->find($event->id)->toApi(),
            'message' => 'Event created successfully',
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $event = $this->findVisible($request, $id);

        if (! $event) {
            return $this->notFound($id);
        }

        if ($event->isFeatured()) {
            return response()->json(['success' => false, 'error' => 'Featured events are read-only'], 403);
        }

        $data = $this->validateEvent($request);

        if ($data['totalSeats'] < (int) $event->booked_seats) {
            throw ValidationException::withMessages([
                'totalSeats' => "totalSeats cannot be less than the {$event->booked_seats} seat(s) already booked",
            ]);
        }

        $event->update($this->attributes($data));

        return response()->json([
            'success' => true,
            'data' => $event->refresh()->loadSum(['bookings as booked_seats' => fn ($q) => $q->where('user_id', $request->user()->id)], 'quantity')->toApi(),
            'message' => 'Event updated successfully',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $event = $this->findVisible($request, $id);

        if (! $event) {
            return $this->notFound($id);
        }

        if ($event->isFeatured()) {
            return response()->json(['success' => false, 'error' => 'Featured events are read-only'], 403);
        }

        $event->delete();

        return response()->json(['success' => true, 'message' => 'Event deleted successfully']);
    }

    private function findVisible(Request $request, string $id): ?Event
    {
        return ctype_digit($id) ? Event::visibleTo($request->user())->find((int) $id) : null;
    }

    private function notFound(string $id): JsonResponse
    {
        return response()->json(['success' => false, 'error' => "Event with id {$id} not found"], 404);
    }

    /**
     * @return array{title: string, description: ?string, category: string, venue: string, city: string, eventDate: string, price: float|int, totalSeats: int, imageUrl: ?string}
     */
    private function validateEvent(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', Rule::in(Event::CATEGORIES)],
            'venue' => ['required', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:80'],
            'eventDate' => ['required', 'date', 'after:now'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'totalSeats' => ['required', 'integer', 'min:1', 'max:100000'],
            'imageUrl' => ['nullable', 'url:http,https', 'max:500'],
        ], [
            'category.in' => 'Category must be one of: '.implode(', ', Event::CATEGORIES),
            'eventDate.after' => 'Event date must be in the future',
        ]);
    }

    private function attributes(array $data): array
    {
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
