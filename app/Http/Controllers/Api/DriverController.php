<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Driver\ApplyDriverRequest;
use App\Http\Requests\Driver\UploadSelfieRequest;
use App\Http\Requests\Driver\UploadDocumentRequest;
use App\Models\DriverProfile;
use App\Models\DriverDocument;
use App\Models\SystemSetting;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverController extends BaseController
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get current user's driver profile status.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->success([
                'has_profile' => false,
                'status' => 'not_applied',
                'required_documents' => $this->getRequiredDocuments(),
            ], 'Driver profile not found');
        }

        $requiredDocs = $this->getRequiredDocuments();
        $uploadedDocs = $profile->documents()->get()->map(function ($doc) {
            return [
                'key' => $doc->key,
                'label' => $doc->label,
                'status' => $doc->status,
                'has_file' => $doc->hasMedia('file'),
                'document_number' => $doc->document_number,
                'expiry_date' => $doc->expiry_date?->format('Y-m-d'),
                'is_expired' => $doc->isExpired(),
            ];
        });

        $missingDocs = $profile->getMissingRequiredDocuments();
        $hasSelfie = $profile->hasSelfie();

        return $this->success([
            'has_profile' => true,
            'status' => $profile->status,
            'applied_at' => $profile->applied_at?->toDateTimeString(),
            'verified_at' => $profile->verified_at?->toDateTimeString(),
            'rejected_reason' => $profile->rejected_reason,
            'city' => $profile->city ? [
                'id' => $profile->city->id,
                'name' => $profile->city->name,
            ] : null,
            'has_selfie' => $hasSelfie,
            'selfie_url' => $hasSelfie ? $profile->getFirstMediaUrl('selfie') : null,
            'required_documents' => $requiredDocs,
            'uploaded_documents' => $uploadedDocs,
            'missing_documents' => $missingDocs,
            'vehicles_count' => $profile->vehicles()->count(),
            'can_create_rides' => $profile->canCreateRides(),
        ], 'Driver profile retrieved successfully');
    }

    /**
     * Apply to become a driver.
     */
    public function apply(ApplyDriverRequest $request): JsonResponse
    {
        $user = $request->user();

        // Check if driver onboarding is enabled
        if (!SystemSetting::get('driver.enabled', true)) {
            return $this->error('Driver onboarding is currently disabled');
        }

        // Check if already applied
        if ($user->driverProfile) {
            return $this->error('You have already applied to become a driver');
        }

        // Check city is active
        $city = \App\Models\City::find($request->city_id);
        if (!$city || !$city->is_active) {
            return $this->error('Selected city is not available');
        }

        DB::beginTransaction();
        try {
            $autoApprove = SystemSetting::get('driver.auto_approve', false);
            $requireVerification = SystemSetting::get('driver.require_verification', true);

            $status = 'not_applied';
            if ($autoApprove || !$requireVerification) {
                $status = 'approved';
            } else {
                $status = 'pending';
            }

            $profile = DriverProfile::create([
                'user_id' => $user->id,
                'city_id' => $request->city_id,
                'status' => $status,
                'dob' => $request->dob,
                'address' => $request->address,
                'gender' => $request->gender,
                'applied_at' => $status === 'pending' ? now() : null,
                'verified_at' => $status === 'approved' ? now() : null,
                'last_status_changed_at' => now(),
            ]);

            if ($status === 'approved') {
                $profile->updateStatus('approved', null, null);
            } elseif ($status === 'pending') {
                $profile->updateStatus('pending', null, null);
            }

            // Update user is_driver flag
            $user->update(['is_driver' => true]);

            DB::commit();

            return $this->success([
                'profile' => $profile,
                'status' => $profile->status,
                'requires_verification' => $requireVerification && !$autoApprove,
            ], $status === 'approved' ? 'Driver application approved automatically' : 'Driver application submitted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to submit application: ' . $e->getMessage());
        }
    }

    /**
     * Upload selfie.
     */
    public function uploadSelfie(UploadSelfieRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found. Please apply first.');
        }

        try {
            // Delete existing selfie
            $profile->clearMediaCollection('selfie');

            // Add new selfie
            $profile->addMediaFromRequest('selfie')
                ->toMediaCollection('selfie');

            return $this->success([
                'selfie_url' => $profile->getFirstMediaUrl('selfie'),
            ], 'Selfie uploaded successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to upload selfie: ' . $e->getMessage());
        }
    }

    /**
     * Upload/update a document.
     */
    public function uploadDocument(string $key, UploadDocumentRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found. Please apply first.');
        }

        // Validate document key exists in required documents
        $requiredDocs = $this->getRequiredDocuments();
        $docConfig = collect($requiredDocs)->firstWhere('key', $key);

        if (!$docConfig) {
            return $this->error('Invalid document key');
        }

        try {
            // Find or create document record
            $document = DriverDocument::firstOrNew([
                'driver_profile_id' => $profile->id,
                'key' => $key,
            ]);

            $document->label = $docConfig['label'];
            $document->status = 'pending';
            $document->document_number = $request->document_number;
            $document->issue_date = $request->issue_date;
            $document->expiry_date = $request->expiry_date;
            $document->verified_at = null;
            $document->rejection_reason = null;
            $document->save();

            // Delete existing file
            $document->clearMediaCollection('file');

            // Add new file
            $document->addMediaFromRequest('file')
                ->toMediaCollection('file');

            return $this->success([
                'document' => [
                    'key' => $document->key,
                    'label' => $document->label,
                    'status' => $document->status,
                    'file_url' => $document->getFirstMediaUrl('file'),
                ],
            ], 'Document uploaded successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to upload document: ' . $e->getMessage());
        }
    }

    /**
     * Submit driver application (validate all required docs).
     */
    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return $this->error('Driver profile not found. Please apply first.');
        }

        if ($profile->status !== 'not_applied') {
            return $this->error('Application already submitted');
        }

        // Check required documents
        if (!$profile->hasAllRequiredDocuments()) {
            $missing = $profile->getMissingRequiredDocuments();
            return $this->error('Missing required documents: ' . implode(', ', array_column($missing, 'label')), [
                'missing_documents' => $missing,
            ]);
        }

        // Check selfie if required
        if (!$profile->hasSelfie()) {
            return $this->error('Selfie is required');
        }

        // Check all documents have files
        foreach ($profile->documents as $doc) {
            if (!$doc->hasMedia('file')) {
                return $this->error("Document '{$doc->label}' file is missing");
            }
        }

        $autoApprove = SystemSetting::get('driver.auto_approve', false);
        $requireVerification = SystemSetting::get('driver.require_verification', true);

        $status = 'pending';
        if ($autoApprove || !$requireVerification) {
            $status = 'approved';
        }

        $profile->status = $status;
        $profile->applied_at = now();
        $profile->last_status_changed_at = now();

        if ($status === 'approved') {
            $profile->verified_at = now();
        }

        $profile->save();

        $profile->updateStatus($status, null, null);

        return $this->success([
            'status' => $profile->status,
            'applied_at' => $profile->applied_at->toDateTimeString(),
        ], $status === 'approved' ? 'Application approved automatically' : 'Application submitted successfully');
    }

    /**
     * Get required documents from settings.
     */
    protected function getRequiredDocuments(): array
    {
        $docs = SystemSetting::get('driver.required_documents', '[]');
        $docs = is_string($docs) ? json_decode($docs, true) : $docs;
        return is_array($docs) ? $docs : [];
    }
}
