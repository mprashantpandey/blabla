<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class City extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'state',
        'country',
        'latitude',
        'longitude',
        'service_area_polygon',
        'timezone',
        'currency',
        'currency_code',
        'is_active',
        'sort_order',
        'default_search_radius_km',
        'created_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'service_area_polygon' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'default_search_radius_km' => 'decimal:2',
    ];

    /**
     * Get the users for the city.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the service areas for the city.
     */
    public function serviceAreas()
    {
        return $this->hasMany(ServiceArea::class);
    }

    /**
     * Get active service areas.
     */
    public function activeServiceAreas()
    {
        return $this->hasMany(ServiceArea::class)->where('is_active', true);
    }

    /**
     * Get the admin assignments for the city.
     */
    public function adminAssignments()
    {
        return $this->hasMany(CityAdminAssignment::class);
    }

    /**
     * Get the user who created the city.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generate slug from name.
     */
    public static function generateSlug(string $name): string
    {
        $slug = \Illuminate\Support\Str::slug($name);
        $count = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = \Illuminate\Support\Str::slug($name) . '-' . $count;
            $count++;
        }
        return $slug;
    }
}
