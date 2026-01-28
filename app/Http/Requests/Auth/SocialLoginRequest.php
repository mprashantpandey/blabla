<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SocialLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|in:google,apple',
            'id_token' => 'required|string',
            'email' => 'nullable|email',
            'name' => 'nullable|string|max:255',
        ];
    }
}
