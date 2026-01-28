<?php

namespace App\Http\Requests\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

class CreateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_id' => 'required|exists:cities,id',
            'type' => 'required|in:car,bike,suv,van,other',
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'plate_number' => 'required|string|max:50',
            'seats_total' => 'required|integer|min:1|max:50',
            'seats_available_default' => 'required|integer|min:0|max:50',
        ];
    }
}
