<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class FilterSensitiveData
{
    /**
     * Sensitive keys that should be filtered from logs.
     */
    private array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'old_password',
        'new_password',
        'api_key',
        'api_secret',
        'secret',
        'token',
        'access_token',
        'refresh_token',
        'razorpay_key_secret',
        'stripe_secret_key',
        'firebase_service_account',
        'msg91_auth_key',
        'smtp_password',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Filter sensitive data from request before logging
        $filteredData = $this->filterSensitiveData($request->all());
        
        // Store filtered data temporarily for logging purposes
        if (config('app.debug')) {
            Log::debug('Request data (filtered)', ['data' => $filteredData]);
        }

        return $next($request);
    }

    /**
     * Recursively filter sensitive data from array.
     */
    private function filterSensitiveData(array $data): array
    {
        $filtered = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            
            // Check if key contains sensitive keywords
            $isSensitive = false;
            foreach ($this->sensitiveKeys as $sensitiveKey) {
                if (str_contains($lowerKey, $sensitiveKey)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $filtered[$key] = '[FILTERED]';
            } elseif (is_array($value)) {
                $filtered[$key] = $this->filterSensitiveData($value);
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}

