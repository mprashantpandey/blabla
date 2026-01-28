<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\SystemSetting;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = SystemSetting::get('driver.max_doc_file_mb', 8) * 1024;
        $allowedMimes = SystemSetting::get('driver.allowed_doc_mimes', 'jpg,jpeg,png,pdf');
        $mimes = explode(',', $allowedMimes);

        return [
            'file' => 'required|file|mimes:' . implode(',', $mimes) . '|max:' . $maxSize,
            'document_number' => 'nullable|string|max:100',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:issue_date',
        ];
    }
}
