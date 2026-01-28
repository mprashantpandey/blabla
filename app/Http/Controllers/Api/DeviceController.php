<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Device\RegisterDeviceRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends BaseController
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register device.
     */
    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        $user = $request->user();

        $device = $this->authService->registerDevice(
            $user,
            $request->device_id,
            $request->platform,
            $request->fcm_token,
            $request->app_version,
            $request->device_model,
            $request->os_version
        );

        return $this->success($device, 'Device registered successfully');
    }

    /**
     * Unregister device.
     */
    public function unregister(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|string',
        ]);

        $user = $request->user();

        $deleted = $this->authService->unregisterDevice($user, $request->device_id);

        if ($deleted) {
            return $this->success(null, 'Device unregistered successfully');
        }

        return $this->error('Device not found.');
    }
}
