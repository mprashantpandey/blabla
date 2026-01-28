<?php

namespace App\Http\Controllers\Api;

use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends BaseController
{
    /**
     * Get public settings (for mobile app).
     */
    public function index(): JsonResponse
    {
        $publicSettings = SystemSetting::whereIn('key', [
            'app_name',
            'app_logo',
            'currency',
            'map_provider',
            'map_api_key',
            'min_app_version',
            'force_update',
            'maintenance_mode',
            'maintenance_message',
        ])->get()->mapWithKeys(function ($setting) {
            return [$setting->key => $this->castValue($setting)];
        });

        return $this->success($publicSettings, 'Settings retrieved successfully');
    }

    /**
     * Get all settings (admin only).
     */
    public function all(): JsonResponse
    {
        $settings = SystemSetting::orderBy('group')->orderBy('key')->get()
            ->groupBy('group')
            ->map(function ($group) {
                return $group->mapWithKeys(function ($setting) {
                    return [$setting->key => [
                        'value' => $this->castValue($setting),
                        'type' => $setting->type,
                        'description' => $setting->description,
                    ]];
                });
            });

        return $this->success($settings, 'All settings retrieved successfully');
    }

    /**
     * Cast setting value based on type.
     */
    private function castValue(SystemSetting $setting)
    {
        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }
}
