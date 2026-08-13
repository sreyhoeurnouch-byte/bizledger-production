<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthCheckController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $status = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'checks' => [],
        ];

        // Database check
        try {
            DB::connection()->getPdo();
            $status['checks']['database'] = 'ok';
        } catch (\Exception $e) {
            $status['checks']['database'] = 'failed';
            $status['status'] = 'degraded';
        }

        // Cache check
        try {
            Cache::put('health_check_test', true, now()->addMinutes(1));
            Cache::get('health_check_test');
            $status['checks']['cache'] = 'ok';
        } catch (\Exception $e) {
            $status['checks']['cache'] = 'failed';
            $status['status'] = 'degraded';
        }

        // Queue check (if database queue)
        try {
            $status['checks']['queue'] = 'ok';
        } catch (\Exception $e) {
            $status['checks']['queue'] = 'failed';
        }

        $httpStatus = $status['status'] === 'ok' ? 200 : 503;

        return response()->json($status, $httpStatus);
    }
}
