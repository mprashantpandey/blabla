<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthController extends BaseController
{
    /**
     * Health check endpoint.
     */
    public function check(): JsonResponse
    {
        $status = 'ok';
        $checks = [];

        // Database check
        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ok';
        } catch (\Exception $e) {
            $checks['database'] = 'error';
            $status = 'error';
        }

        // Cache check
        try {
            Cache::put('health_check', 'ok', 10);
            $checks['cache'] = Cache::get('health_check') === 'ok' ? 'ok' : 'error';
            if ($checks['cache'] === 'error') {
                $status = 'error';
            }
        } catch (\Exception $e) {
            $checks['cache'] = 'error';
            $status = 'error';
        }

        return $this->success([
            'status' => $status,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], 'Health check completed');
    }
}

