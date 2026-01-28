<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\ApproveDriverRequest;
use App\Http\Requests\Admin\RejectDriverRequest;
use App\Models\DriverProfile;
use App\Models\DriverDocument;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverModerationController extends BaseController
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get drivers list (with filters).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = DriverProfile::with(['user', 'city', 'documents']);

        // City Admin scope
        if ($user->hasRole('City Admin') && !$user->hasRole('Super Admin')) {
            $assignedCityIds = $user->assignedCities()->pluck('cities.id');
            $query->whereIn('city_id', $assignedCityIds);
        }

        // Filters
        if ($request->has('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('needs_review')) {
            $query->where(function ($q) {
                $q->where('status', 'pending')
                  ->orWhereHas('documents', function ($docQ) {
                      $docQ->where('status', 'pending');
                  });
            });
        }

        $drivers = $query->paginate($request->get('per_page', 15));

        $data = $drivers->map(function ($driver) {
            $missingDocs = $driver->getMissingRequiredDocuments();
            $pendingDocs = $driver->documents()->where('status', 'pending')->count();

            return [
                'id' => $driver->id,
                'user' => [
                    'id' => $driver->user->id,
                    'name' => $driver->user->name,
                    'email' => $driver->user->email,
                    'phone' => $driver->user->phone,
                ],
                'city' => $driver->city ? [
                    'id' => $driver->city->id,
                    'name' => $driver->city->name,
                ] : null,
                'status' => $driver->status,
                'applied_at' => $driver->applied_at?->toDateTimeString(),
                'verified_at' => $driver->verified_at?->toDateTimeString(),
                'missing_docs_count' => count($missingDocs),
                'pending_docs_count' => $pendingDocs,
            ];
        });

        return $this->success($data, 'Drivers retrieved successfully', 200, [
            'current_page' => $drivers->currentPage(),
            'last_page' => $drivers->lastPage(),
            'per_page' => $drivers->perPage(),
            'total' => $drivers->total(),
        ]);
    }

    /**
     * Get driver details.
     */
    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $driver = DriverProfile::with(['user', 'city', 'documents.media', 'vehicles', 'verificationEvents.performer'])->find($id);

        if (!$driver) {
            return $this->error('Driver not found', [], 404);
        }

        // City Admin scope check
        if ($user->hasRole('City Admin') && !$user->hasRole('Super Admin')) {
            $assignedCityIds = $user->assignedCities()->pluck('cities.id');
            if (!in_array($driver->city_id, $assignedCityIds->toArray())) {
                return $this->error('Access denied', [], 403);
            }
        }

        $documents = $driver->documents->map(function ($doc) {
            return [
                'id' => $doc->id,
                'key' => $doc->key,
                'label' => $doc->label,
                'status' => $doc->status,
                'document_number' => $doc->document_number,
                'issue_date' => $doc->issue_date?->format('Y-m-d'),
                'expiry_date' => $doc->expiry_date?->format('Y-m-d'),
                'is_expired' => $doc->isExpired(),
                'file_url' => $doc->getFirstMediaUrl('file'),
                'rejection_reason' => $doc->rejection_reason,
            ];
        });

        $vehicles = $driver->vehicles->map(function ($vehicle) {
            $photos = $vehicle->getMedia('photos')->map(fn ($m) => $m->getUrl());
            return [
                'id' => $vehicle->id,
                'type' => $vehicle->type,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'plate_number' => $vehicle->plate_number,
                'is_primary' => $vehicle->is_primary,
                'photos' => $photos,
            ];
        });

        return $this->success([
            'id' => $driver->id,
            'user' => [
                'id' => $driver->user->id,
                'name' => $driver->user->name,
                'email' => $driver->user->email,
                'phone' => $driver->user->phone,
            ],
            'city' => $driver->city ? [
                'id' => $driver->city->id,
                'name' => $driver->city->name,
            ] : null,
            'status' => $driver->status,
            'dob' => $driver->dob?->format('Y-m-d'),
            'address' => $driver->address,
            'gender' => $driver->gender,
            'applied_at' => $driver->applied_at?->toDateTimeString(),
            'verified_at' => $driver->verified_at?->toDateTimeString(),
            'rejected_reason' => $driver->rejected_reason,
            'admin_note' => $driver->admin_note,
            'selfie_url' => $driver->getFirstMediaUrl('selfie'),
            'documents' => $documents,
            'vehicles' => $vehicles,
            'verification_events' => $driver->verificationEvents->map(function ($event) {
                return [
                    'action' => $event->action,
                    'performed_by' => $event->performer ? $event->performer->name : 'System',
                    'meta' => $event->meta,
                    'created_at' => $event->created_at->toDateTimeString(),
                ];
            }),
        ], 'Driver details retrieved successfully');
    }

    /**
     * Approve driver.
     */
    public function approve(ApproveDriverRequest $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $driver = DriverProfile::with('user')->find($id);

        if (!$driver) {
            return $this->error('Driver not found', [], 404);
        }

        // City Admin scope check
        if ($user->hasRole('City Admin') && !$user->hasRole('Super Admin')) {
            $assignedCityIds = $user->assignedCities()->pluck('cities.id');
            if (!in_array($driver->city_id, $assignedCityIds->toArray())) {
                return $this->error('Access denied. You can only approve drivers in your assigned cities.', [], 403);
            }
        }

        if ($driver->status === 'approved') {
            return $this->error('Driver is already approved');
        }

        try {
            $driver->admin_note = $request->admin_note;
            $driver->updateStatus('approved', null, $user->id);

            // Send notification
            $this->notificationService->sendToUser(
                $driver->user,
                'Driver Application Approved',
                'Congratulations! Your driver application has been approved. You can now create rides.',
                ['type' => 'driver_approved', 'driver_id' => $driver->id],
                true
            );

            return $this->success([
                'status' => $driver->status,
                'verified_at' => $driver->verified_at?->toDateTimeString(),
            ], 'Driver approved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to approve driver: ' . $e->getMessage());
        }
    }

    /**
     * Reject driver.
     */
    public function reject(RejectDriverRequest $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $driver = DriverProfile::with('user')->find($id);

        if (!$driver) {
            return $this->error('Driver not found', [], 404);
        }

        // City Admin scope check
        if ($user->hasRole('City Admin') && !$user->hasRole('Super Admin')) {
            $assignedCityIds = $user->assignedCities()->pluck('cities.id');
            if (!in_array($driver->city_id, $assignedCityIds->toArray())) {
                return $this->error('Access denied. You can only reject drivers in your assigned cities.', [], 403);
            }
        }

        if ($driver->status === 'rejected') {
            return $this->error('Driver is already rejected');
        }

        try {
            $driver->updateStatus('rejected', $request->reason, $user->id);

            // Send notification
            $this->notificationService->sendToUser(
                $driver->user,
                'Driver Application Rejected',
                'Your driver application has been rejected. Reason: ' . $request->reason,
                ['type' => 'driver_rejected', 'driver_id' => $driver->id, 'reason' => $request->reason],
                true
            );

            return $this->success([
                'status' => $driver->status,
            ], 'Driver rejected successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to reject driver: ' . $e->getMessage());
        }
    }

    /**
     * Suspend driver.
     */
    public function suspend(RejectDriverRequest $request, int $id): JsonResponse
    {
        $user = auth()->user();
        $driver = DriverProfile::with('user')->find($id);

        if (!$driver) {
            return $this->error('Driver not found', [], 404);
        }

        // City Admin scope check
        if ($user->hasRole('City Admin') && !$user->hasRole('Super Admin')) {
            $assignedCityIds = $user->assignedCities()->pluck('cities.id');
            if (!in_array($driver->city_id, $assignedCityIds->toArray())) {
                return $this->error('Access denied. You can only suspend drivers in your assigned cities.', [], 403);
            }
        }

        if ($driver->status === 'suspended') {
            return $this->error('Driver is already suspended');
        }

        try {
            $driver->updateStatus('suspended', $request->reason, $user->id);

            // Send notification
            $this->notificationService->sendToUser(
                $driver->user,
                'Driver Account Suspended',
                'Your driver account has been suspended. Reason: ' . $request->reason,
                ['type' => 'driver_suspended', 'driver_id' => $driver->id, 'reason' => $request->reason],
                true
            );

            return $this->success([
                'status' => $driver->status,
            ], 'Driver suspended successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to suspend driver: ' . $e->getMessage());
        }
    }

    /**
     * Approve a document.
     */
    public function approveDocument(Request $request, int $id): JsonResponse
    {
        $document = DriverDocument::with('driverProfile.user')->find($id);

        if (!$document) {
            return $this->error('Document not found', [], 404);
        }

        // City Admin scope check
        $user = auth()->user();
        if ($user->hasRole('City Admin') && !$user->hasRole('Super Admin')) {
            $assignedCityIds = $user->assignedCities()->pluck('cities.id');
            if (!in_array($document->driverProfile->city_id, $assignedCityIds->toArray())) {
                return $this->error('Access denied', [], 403);
            }
        }

        try {
            $document->approve($user->id);

            return $this->success([
                'status' => $document->status,
            ], 'Document approved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to approve document: ' . $e->getMessage());
        }
    }

    /**
     * Reject a document.
     */
    public function rejectDocument(RejectDriverRequest $request, int $id): JsonResponse
    {
        $document = DriverDocument::with('driverProfile.user')->find($id);

        if (!$document) {
            return $this->error('Document not found', [], 404);
        }

        // City Admin scope check
        $user = auth()->user();
        if ($user->hasRole('City Admin') && !$user->hasRole('Super Admin')) {
            $assignedCityIds = $user->assignedCities()->pluck('cities.id');
            if (!in_array($document->driverProfile->city_id, $assignedCityIds->toArray())) {
                return $this->error('Access denied', [], 403);
            }
        }

        try {
            $document->reject($request->reason, $user->id);

            return $this->success([
                'status' => $document->status,
            ], 'Document rejected successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to reject document: ' . $e->getMessage());
        }
    }
}
