<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * An expected TicketTrail API error (not found, sold out, read-only...).
 *
 * Throw it from anywhere in the module; Laravel turns it into the module's
 * error shape: {"success": false, "error": "<message>"} with the given status.
 * Expected errors are not logged (see bootstrap/app.php).
 */
class TicketsApiException extends Exception
{
    public function __construct(string $message, public readonly int $status = 400)
    {
        parent::__construct($message);
    }

    public static function notFound(string $message): self
    {
        return new self($message, 404);
    }

    public static function forbidden(string $message): self
    {
        return new self($message, 403);
    }

    public function render(): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $this->getMessage()], $this->status);
    }
}
