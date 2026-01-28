<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Cache;

class UpdateDeviceLastSeen
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only update for authenticated API requests
        if ($request->user() && $request->is('api/*')) {
            $deviceId = $request->header('X-Device-ID');
            
            if ($deviceId) {
                // Throttle: update once per 5 minutes per device
                $cacheKey = "device_last_seen:{$request->user()->id}:{$deviceId}";
                
                if (!Cache::has($cacheKey)) {
                    UserDevice::where('user_id', $request->user()->id)
                        ->where('device_id', $deviceId)
                        ->update(['last_seen_at' => now()]);
                    
                    Cache::put($cacheKey, true, now()->addMinutes(5));
                }
            }
        }

        return $response;
    }
}
