<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceArea extends Model
{
    protected $fillable = [
        'city_id',
        'name',
        'type',
        'center_lat',
        'center_lng',
        'radius_km',
        'polygon',
        'is_active',
        'sort_order',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'center_lat' => 'decimal:8',
        'center_lng' => 'decimal:8',
        'radius_km' => 'decimal:2',
        'polygon' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the city that owns the service area.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the user who created the service area.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if point is within this service area.
     */
    public function containsPoint(float $lat, float $lng): bool
    {
        if ($this->type === 'circle') {
            return app(\App\Services\GeoService::class)->pointInCircle(
                $lat,
                $lng,
                (float) $this->center_lat,
                (float) $this->center_lng,
                (float) $this->radius_km
            );
        } elseif ($this->type === 'polygon' && is_array($this->polygon)) {
            return app(\App\Services\GeoService::class)->pointInPolygon(
                $lat,
                $lng,
                $this->polygon
            );
        }

        return false;
    }
}
