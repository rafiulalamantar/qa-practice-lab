<?php

use App\Http\Middleware\NormalizeAuthorizationHeader;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();
        // Must rewrite the header before Sanctum reads it; Authenticate
        // otherwise sorts ahead of it in the default priority list.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: NormalizeAuthorizationHeader::class,
        );
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/client/auth/login');
        // Set by client-side JS to toggle challenge mode; never encrypted.
        $middleware->encryptCookies(except: ['practice_chaos']);
        // Vercel terminates TLS at its edge.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // TicketTrail's API reports every error as {success: false, error}.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/tickets/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => collect($e->errors())->map(fn (array $messages, string $field) => ['field' => $field, 'message' => $messages[0]])->values(),
                ], 400),
                $e instanceof AuthenticationException => response()->json(['success' => false, 'error' => 'Unauthorized'], 401),
                $e instanceof HttpExceptionInterface => response()->json([
                    'success' => false,
                    'error' => match ($e->getStatusCode()) {
                        404 => 'Resource not found',
                        405 => 'Method not allowed',
                        429 => 'Too many requests',
                        default => $e->getMessage() ?: 'Request failed',
                    },
                ], $e->getStatusCode(), $e->getHeaders()),
                default => config('app.debug') ? null : response()->json(['success' => false, 'error' => 'Internal server error'], 500),
            };
        });
    })->create();
