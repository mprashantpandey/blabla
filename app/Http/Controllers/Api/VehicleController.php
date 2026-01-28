<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Vehicle\CreateVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends BaseController
{
    /**
     * Get driver's vehicles.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found');
        }

        $vehicles = $profile->vehicles()
            ->with('city')
            ->get()
            ->map(function ($vehicle) {
                return $this->formatVehicle($vehicle);
            });

        return $this->success($vehicles, 'Vehicles retrieved successfully');
    }

    /**
     * Create a vehicle.
     */
    public function store(CreateVehicleRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found. Please apply to become a driver first.');
        }

        // Check city is active
        $city = \App\Models\City::find($request->city_id);
        if (!$city || !$city->is_active) {
            return $this->error('Selected city is not available');
        }

        try {
            $vehicle = Vehicle::create([
                'driver_profile_id' => $profile->id,
                'city_id' => $request->city_id,
                'type' => $request->type,
                'make' => $request->make,
                'model' => $request->model,
                'year' => $request->year,
                'color' => $request->color,
                'plate_number' => $request->plate_number,
                'seats_total' => $request->seats_total,
                'seats_available_default' => $request->seats_available_default,
                'is_active' => true,
                'is_primary' => $profile->vehicles()->count() === 0, // First vehicle is primary
            ]);

            return $this->success($this->formatVehicle($vehicle), 'Vehicle created successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to create vehicle: ' . $e->getMessage());
        }
    }

    /**
     * Update a vehicle.
     */
    public function update(UpdateVehicleRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found');
        }

        $vehicle = $profile->vehicles()->find($id);

        if (!$vehicle) {
            return $this->error('Vehicle not found');
        }

        try {
            $vehicle->update($request->validated());

            return $this->success($this->formatVehicle($vehicle->fresh()), 'Vehicle updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update vehicle: ' . $e->getMessage());
        }
    }

    /**
     * Upload vehicle photos.
     */
    public function uploadPhotos(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'photos' => 'required|array|min:1|max:10',
            'photos.*' => 'image|mimes:jpeg,jpg,png|max:5120', // 5MB per photo
        ]);

        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found');
        }

        $vehicle = $profile->vehicles()->find($id);

        if (!$vehicle) {
            return $this->error('Vehicle not found');
        }

        try {
            $files = $request->file('photos');
            if (!is_array($files)) {
                $files = [$files];
            }
            
            foreach ($files as $photo) {
                $vehicle->addMedia($photo)
                    ->usingName($vehicle->full_name)
                    ->toMediaCollection('photos');
            }

            return $this->success([
                'photos_count' => $vehicle->getMedia('photos')->count(),
            ], 'Photos uploaded successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to upload photos: ' . $e->getMessage());
        }
    }

    /**
     * Delete a vehicle photo.
     */
    public function deletePhoto(Request $request, int $id, int $mediaId): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found');
        }

        $vehicle = $profile->vehicles()->find($id);

        if (!$vehicle) {
            return $this->error('Vehicle not found');
        }

        $media = $vehicle->getMedia('photos')->find($mediaId);

        if (!$media) {
            return $this->error('Photo not found');
        }

        try {
            $media->delete();

            return $this->success(null, 'Photo deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete photo: ' . $e->getMessage());
        }
    }

    /**
     * Set vehicle as primary.
     */
    public function setPrimary(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found');
        }

        $vehicle = $profile->vehicles()->find($id);

        if (!$vehicle) {
            return $this->error('Vehicle not found');
        }

        try {
            $vehicle->setAsPrimary();

            return $this->success($this->formatVehicle($vehicle->fresh()), 'Primary vehicle updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to set primary vehicle: ' . $e->getMessage());
        }
    }

    /**
     * Format vehicle for response.
     */
    protected function formatVehicle(Vehicle $vehicle): array
    {
        $photos = $vehicle->getMedia('photos')->map(function ($media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl(),
            ];
        });

        return [
            'id' => $vehicle->id,
            'type' => $vehicle->type,
            'make' => $vehicle->make,
            'model' => $vehicle->model,
            'year' => $vehicle->year,
            'color' => $vehicle->color,
            'plate_number' => $vehicle->plate_number,
            'full_name' => $vehicle->full_name,
            'seats_total' => $vehicle->seats_total,
            'seats_available_default' => $vehicle->seats_available_default,
            'is_active' => $vehicle->is_active,
            'is_primary' => $vehicle->is_primary,
            'city' => $vehicle->city ? [
                'id' => $vehicle->city->id,
                'name' => $vehicle->city->name,
            ] : null,
            'photos' => $photos,
            'photos_count' => $photos->count(),
        ];
    }
}
