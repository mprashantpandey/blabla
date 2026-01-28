<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatingSummary extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'role',
        'avg_rating',
        'total_ratings',
        'total_trips',
        'updated_at',
    ];

    protected $casts = [
        'avg_rating' => 'decimal:2',
        'total_ratings' => 'integer',
        'total_trips' => 'integer',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function ($summary) {
            $summary->updated_at = now();
        });
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get formatted average rating.
     */
    public function getFormattedAvgRating(): string
    {
        return number_format($this->avg_rating, 1);
    }
}
