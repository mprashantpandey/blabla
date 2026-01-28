<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\SystemSetting;
use Carbon\Carbon;

class ApplyDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth checked via middleware
    }

    public function rules(): array
    {
        $minAge = SystemSetting::get('driver.min_age_years', 18);
        $maxDate = Carbon::now()->subYears($minAge)->format('Y-m-d');

        return [
            'city_id' => 'required|exists:cities,id',
            'dob' => 'required|date|before_or_equal:' . $maxDate,
            'address' => 'nullable|string|max:500',
            'gender' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        $minAge = SystemSetting::get('driver.min_age_years', 18);
        return [
            'dob.before_or_equal' => "You must be at least {$minAge} years old to become a driver.",
        ];
    }
}
