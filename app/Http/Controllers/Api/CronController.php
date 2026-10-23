<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Vercel has no long-running scheduler, so Vercel Cron calls this endpoint
 * with "Authorization: Bearer $CRON_SECRET".
 */
class CronController extends Controller
{
    public function prune(Request $request): JsonResponse
    {
        $secret = config('practice.cron_secret');

        if (! $secret || ! hash_equals('Bearer '.$secret, (string) $request->header('Authorization'))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        Artisan::call('practice:prune');

        return response()->json(['message' => trim(Artisan::output())]);
    }
}
