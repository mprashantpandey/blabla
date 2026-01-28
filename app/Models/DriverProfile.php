<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DriverProfile extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'city_id',
        'status',
        'applied_at',
        'verified_at',
        'rejected_reason',
        'admin_note',
        'dob',
        'gender',
        'address',
        'last_status_changed_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'verified_at' => 'datetime',
        'last_status_changed_at' => 'datetime',
        'dob' => 'date',
    ];

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('selfie')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg']);
    }

    /**
     * Get the user that owns the driver profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the city.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the driver documents.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class);
    }

    /**
     * Get the vehicles.
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Get the verification events.
     */
    public function verificationEvents(): HasMany
    {
        return $this->hasMany(DriverVerificationEvent::class);
    }

    /**
     * Get the rides.
     */
    public function rides(): HasMany
    {
        return $this->hasMany(Ride::class);
    }

    /**
     * Get the bookings.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Check if driver is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if driver can create rides.
     */
    public function canCreateRides(): bool
    {
        return $this->isApproved() && $this->user->is_active;
    }

    /**
     * Get missing required documents.
     */
    public function getMissingRequiredDocuments(): array
    {
        $requiredDocs = \App\Models\SystemSetting::get('driver.required_documents', []);
        $requiredDocs = is_string($requiredDocs) ? json_decode($requiredDocs, true) : $requiredDocs;
        
        $uploadedKeys = $this->documents()->pluck('key')->toArray();
        
        $missing = [];
        foreach ($requiredDocs as $doc) {
            if (($doc['required'] ?? false) && !in_array($doc['key'], $uploadedKeys)) {
                $missing[] = $doc;
            }
        }
        
        return $missing;
    }

    /**
     * Check if all required documents are uploaded.
     */
    public function hasAllRequiredDocuments(): bool
    {
        return empty($this->getMissingRequiredDocuments());
    }

    /**
     * Check if selfie is uploaded (if required).
     */
    public function hasSelfie(): bool
    {
        $requireSelfie = \App\Models\SystemSetting::get('driver.require_selfie', true);
        if (!$requireSelfie) {
            return true;
        }
        
        return $this->hasMedia('selfie');
    }

    /**
     * Update status and record event.
     */
    public function updateStatus(string $status, ?string $reason = null, ?int $performedBy = null): void
    {
        $oldStatus = $this->status;
        $this->status = $status;
        $this->last_status_changed_at = now();
        
        if ($status === 'approved') {
            $this->verified_at = now();
        }
        
        if ($reason) {
            if ($status === 'rejected') {
                $this->rejected_reason = $reason;
            } elseif ($status === 'suspended') {
                $this->admin_note = ($this->admin_note ? $this->admin_note . "\n\n" : '') . "Suspended: " . $reason;
            }
        }
        
        $this->save();

        // Record verification event
        DriverVerificationEvent::create([
            'driver_profile_id' => $this->id,
            'action' => $status === 'approved' ? 'approved' : ($status === 'rejected' ? 'rejected' : ($status === 'suspended' ? 'suspended' : 'submitted')),
            'performed_by' => $performedBy ?? auth()->id(),
            'meta' => [
                'old_status' => $oldStatus,
                'new_status' => $status,
                'reason' => $reason,
            ],
        ]);

        // Create wallet when driver is approved
        if ($status === 'approved' && $oldStatus !== 'approved') {
            $walletService = app(\App\Services\WalletService::class);
            $walletService->getOrCreateWallet($this);
        }
    }

    /**
     * Get the wallet.
     */
    public function wallet()
    {
        return $this->hasOne(DriverWallet::class);
    }

    /**
     * Get the payout requests.
     */
    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }
}
