<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\SystemSetting;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [];

        // Email/password registration
        if ($this->input('method') === 'email' && SystemSetting::get('auth.enable_email_password', true)) {
            $rules = [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'city_id' => 'nullable|exists:cities,id',
            ];
        }

        // Phone OTP registration
        if ($this->input('method') === 'phone' && SystemSetting::get('auth.enable_phone_otp', true)) {
            $rules = [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|regex:/^[0-9]{10,15}$/',
                'country_code' => 'required|string|regex:/^\+[0-9]{1,4}$/',
                'city_id' => 'nullable|exists:cities,id',
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered.',
            'phone.regex' => 'Invalid phone number format.',
            'country_code.regex' => 'Invalid country code format. Use format: +123',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
