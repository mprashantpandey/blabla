<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RideStop extends Model
{
    protected $fillable = [
        'ride_id',
        'type',
        'name',
        'lat',
        'lng',
        'stop_order',
    ];

    protected $casts = [
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'stop_order' => 'integer',
    ];

    /**
     * Get the ride.
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }
}
