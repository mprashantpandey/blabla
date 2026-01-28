<?php

namespace App\Http\Requests\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'sometimes|in:car,bike,suv,van,other',
            'make' => 'sometimes|string|max:100',
            'model' => 'sometimes|string|max:100',
            'year' => 'sometimes|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'plate_number' => 'sometimes|string|max:50',
            'seats_total' => 'sometimes|integer|min:1|max:50',
            'seats_available_default' => 'sometimes|integer|min:0|max:50',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
