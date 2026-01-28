<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\SystemSetting;

class UploadSelfieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = SystemSetting::get('driver.max_doc_file_mb', 8) * 1024; // Convert to KB

        return [
            'selfie' => 'required|image|mimes:jpeg,jpg,png|max:' . $maxSize,
        ];
    }
}
