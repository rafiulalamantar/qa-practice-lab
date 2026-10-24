<?php

use App\Exceptions\TicketsApiErrorRenderer;
use App\Exceptions\TicketsApiException;
use App\Http\Middleware\NormalizeAuthorizationHeader;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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

        // Expected API errors (not found, sold out...) are answers, not bugs.
        $exceptions->dontReport(TicketsApiException::class);
        $exceptions->render((new TicketsApiErrorRenderer)(...));
    })->create();
