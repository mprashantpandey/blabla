<?php

namespace App\Http\Requests\Ride;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\SystemSetting;

class UpdateRideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxWaypoints = SystemSetting::get('rides.max_waypoints', 3);
        $maxSeats = SystemSetting::get('rides.max_seats', 8);

        return [
            'city_id' => 'sometimes|exists:cities,id',
            'origin_name' => 'sometimes|string|max:255',
            'origin_lat' => 'sometimes|numeric|between:-90,90',
            'origin_lng' => 'sometimes|numeric|between:-180,180',
            'destination_name' => 'sometimes|string|max:255',
            'destination_lat' => 'sometimes|numeric|between:-90,90',
            'destination_lng' => 'sometimes|numeric|between:-180,180',
            'waypoints' => 'nullable|array|max:' . $maxWaypoints,
            'waypoints.*.name' => 'required_with:waypoints|string|max:255',
            'waypoints.*.lat' => 'required_with:waypoints|numeric|between:-90,90',
            'waypoints.*.lng' => 'required_with:waypoints|numeric|between:-180,180',
            'route_polyline' => 'nullable|string',
            'departure_at' => 'sometimes|date|after:now',
            'arrival_estimated_at' => 'nullable|date|after:departure_at',
            'price_per_seat' => 'sometimes|numeric|min:0',
            'currency_code' => 'nullable|string|max:3',
            'seats_total' => 'sometimes|integer|min:1|max:' . $maxSeats,
            'allow_instant_booking' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
            'rules_json' => 'nullable|array',
            'cancellation_policy' => 'nullable|string|max:100',
        ];
    }
}
