<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Challenge mode: randomly delay API responses so learners must rely on
 * proper waits instead of fixed sleeps.
 */
class ChaosLatency
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = $request->cookie('practice_chaos') === '1' || $request->header('X-Chaos-Mode') === '1';

        if ($enabled && ! app()->runningUnitTests()) {
            usleep(random_int(config('practice.chaos_min_ms'), config('practice.chaos_max_ms')) * 1000);
        }

        $response = $next($request);

        if ($enabled) {
            $response->headers->set('X-Chaos-Mode', '1');
        }

        return $response;
    }
}
