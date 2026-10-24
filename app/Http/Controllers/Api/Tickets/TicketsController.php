<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Base class for TicketTrail API controllers.
 *
 * Every successful response is {"success": true, ...}. Errors are thrown as
 * TicketsApiException (or validation errors) and never built by hand here.
 */
abstract class TicketsController extends Controller
{
    /** @param  array<string, mixed>  $body */
    protected function success(array $body = [], int $status = 200): JsonResponse
    {
        return response()->json(['success' => true] + $body, $status);
    }

    /** @return array<string, list<string>> */
    protected function paginationRules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Respond with {success, data, pagination: {total, page, limit, totalPages}}.
     *
     * @param  array{page?: int|string|null, limit?: int|string|null}  $input
     * @param  callable(mixed): array<string, mixed>  $toApi  turns one row into its JSON shape
     */
    protected function paginated(Builder $query, array $input, callable $toApi): JsonResponse
    {
        $page = (int) ($input['page'] ?? 1);
        $limit = (int) ($input['limit'] ?? 10);
        $total = (clone $query)->toBase()->getCountForPagination();

        return $this->success([
            'data' => $query->forPage($page, $limit)->get()->map($toApi)->values(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ]);
    }
}
