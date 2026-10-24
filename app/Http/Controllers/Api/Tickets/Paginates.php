<?php

namespace App\Http\Controllers\Api\Tickets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

trait Paginates
{
    private function paginationRules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Respond with {success, data, pagination: {total, page, limit, totalPages}}.
     */
    private function paginated(Builder $query, array $input, callable $map): JsonResponse
    {
        $page = (int) ($input['page'] ?? 1);
        $limit = (int) ($input['limit'] ?? 10);
        $total = (clone $query)->toBase()->getCountForPagination();

        return response()->json([
            'success' => true,
            'data' => $query->forPage($page, $limit)->get()->map($map)->values(),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ]);
    }
}
