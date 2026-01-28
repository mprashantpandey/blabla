<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DriverDocument extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'driver_profile_id',
        'key',
        'label',
        'status',
        'document_number',
        'issue_date',
        'expiry_date',
        'verified_at',
        'rejection_reason',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'verified_at' => 'datetime',
    ];

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $allowedMimes = \App\Models\SystemSetting::get('driver.allowed_doc_mimes', 'jpg,jpeg,png,pdf');
        $mimeTypes = array_map(function ($ext) {
            return match($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'pdf' => 'application/pdf',
                default => 'application/octet-stream',
            };
        }, explode(',', $allowedMimes));

        $this->addMediaCollection('file')
            ->singleFile()
            ->acceptsMimeTypes($mimeTypes);
    }

    /**
     * Get the driver profile.
     */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    /**
     * Check if document is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if document is expired.
     */
    public function isExpired(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }
        
        return $this->expiry_date->isPast();
    }

    /**
     * Approve document.
     */
    public function approve(?int $performedBy = null): void
    {
        $this->status = 'approved';
        $this->verified_at = now();
        $this->rejection_reason = null;
        $this->save();

        // Record event
        DriverVerificationEvent::create([
            'driver_profile_id' => $this->driver_profile_id,
            'action' => 'docs_updated',
            'performed_by' => $performedBy ?? auth()->id(),
            'meta' => [
                'document_key' => $this->key,
                'action' => 'approved',
            ],
        ]);
    }

    /**
     * Reject document.
     */
    public function reject(string $reason, ?int $performedBy = null): void
    {
        $this->status = 'rejected';
        $this->rejection_reason = $reason;
        $this->verified_at = null;
        $this->save();

        // Record event
        DriverVerificationEvent::create([
            'driver_profile_id' => $this->driver_profile_id,
            'action' => 'docs_updated',
            'performed_by' => $performedBy ?? auth()->id(),
            'meta' => [
                'document_key' => $this->key,
                'action' => 'rejected',
                'reason' => $reason,
            ],
        ]);
    }
}
