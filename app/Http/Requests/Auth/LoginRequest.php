<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\SystemSetting;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [];

        // Email/password login
        if ($this->input('method') === 'email' && SystemSetting::get('auth.enable_email_password', true)) {
            $rules = [
                'email' => 'required|email',
                'password' => 'required|string',
            ];
        }

        // Phone OTP login
        if ($this->input('method') === 'phone' && SystemSetting::get('auth.enable_phone_otp', true)) {
            $rules = [
                'phone' => 'required|string|regex:/^[0-9]{10,15}$/',
                'country_code' => 'required|string|regex:/^\+[0-9]{1,4}$/',
            ];
        }

        return $rules;
    }
}
