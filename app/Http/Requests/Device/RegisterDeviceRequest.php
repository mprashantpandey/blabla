<?php

namespace App\Http\Requests\Device;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // User must be authenticated via middleware
    }

    public function rules(): array
    {
        return [
            'device_id' => 'required|string|max:255',
            'platform' => 'required|in:android,ios,web',
            'fcm_token' => 'nullable|string',
            'app_version' => 'nullable|string|max:20',
            'device_model' => 'nullable|string|max:100',
            'os_version' => 'nullable|string|max:50',
        ];
    }
}
