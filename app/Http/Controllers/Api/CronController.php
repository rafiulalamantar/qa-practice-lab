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
        if (! $this->authorized($request)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        Artisan::call('practice:prune');

        return response()->json(['message' => trim(Artisan::output())]);
    }

    /**
     * Post-deploy hook: apply pending migrations and the idempotent seeders,
     * so production database credentials never leave Vercel.
     */
    public function migrate(Request $request): JsonResponse
    {
        if (! $this->authorized($request)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();
        Artisan::call('db:seed', ['--force' => true]);

        return response()->json(['message' => trim($output.Artisan::output())]);
    }

    private function authorized(Request $request): bool
    {
        $secret = config('practice.cron_secret');

        return $secret && hash_equals('Bearer '.$secret, (string) $request->header('Authorization'));
    }
}
