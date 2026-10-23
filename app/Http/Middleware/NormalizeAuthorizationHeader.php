<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accept both "Authorization: Bearer <token>" and the bare
 * "Authorization: <token>" style many tutorials use.
 */
class NormalizeAuthorizationHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = trim((string) $request->header('Authorization'));

        if ($header !== '' && ! str_contains($header, ' ')) {
            $request->headers->set('Authorization', 'Bearer '.$header);
        }

        return $next($request);
    }
}
