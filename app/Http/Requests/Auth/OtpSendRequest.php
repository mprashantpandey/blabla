<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class OtpSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => 'required|string|regex:/^[0-9]{10,15}$/',
            'country_code' => 'required|string|regex:/^\+[0-9]{1,4}$/',
            'context' => 'required|in:login,register',
        ];
    }
}
