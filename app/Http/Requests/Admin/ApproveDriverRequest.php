<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ApproveDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasAnyRole(['Super Admin', 'City Admin']);
    }

    public function rules(): array
    {
        return [
            'admin_note' => 'nullable|string|max:1000',
        ];
    }
}
