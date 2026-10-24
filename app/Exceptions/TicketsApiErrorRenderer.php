<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders framework errors on /api/tickets/* in TicketTrail's error shape:
 * {"success": false, "error": "...", "details"?: [{field, message}]}.
 *
 * Validation errors use 400 (not Laravel's default 422) to match the API
 * TicketTrail is modelled on. Other routes keep Laravel's default rendering.
 */
class TicketsApiErrorRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/tickets/*')) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => $this->error('Validation failed', 400, details: $this->validationDetails($e)),
            $e instanceof AuthenticationException => $this->error('Unauthorized', 401),
            $e instanceof HttpExceptionInterface => $this->error($this->httpMessage($e), $e->getStatusCode(), $e->getHeaders()),
            // In debug mode let Laravel show the full stack trace instead.
            default => config('app.debug') ? null : $this->error('Internal server error', 500),
        };
    }

    /** @return list<array{field: string, message: string}> */
    private function validationDetails(ValidationException $e): array
    {
        return collect($e->errors())
            ->map(fn (array $messages, string $field) => ['field' => $field, 'message' => $messages[0]])
            ->values()
            ->all();
    }

    private function httpMessage(HttpExceptionInterface $e): string
    {
        return match ($e->getStatusCode()) {
            404 => 'Resource not found',
            405 => 'Method not allowed',
            429 => 'Too many requests',
            default => $e->getMessage() ?: 'Request failed',
        };
    }

    /**
     * @param  array<string, string>  $headers
     * @param  list<array{field: string, message: string}>|null  $details
     */
    private function error(string $message, int $status, array $headers = [], ?array $details = null): JsonResponse
    {
        $body = ['success' => false, 'error' => $message];

        if ($details !== null) {
            $body['details'] = $details;
        }

        return response()->json($body, $status, $headers);
    }
}
