<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements HasMedia
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, SoftDeletes, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'country_code',
        'password',
        'avatar',
        'language',
        'notifications_enabled',
        'is_active',
        'is_driver',
        'status',
        'auth_provider',
        'city_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'notifications_enabled' => 'boolean',
            'is_active' => 'boolean',
            'is_driver' => 'boolean',
        ];
    }

    /**
     * Get the city that the user belongs to.
     */
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the user's social identities.
     */
    public function identities()
    {
        return $this->hasMany(UserIdentity::class);
    }

    /**
     * Get the user's devices.
     */
    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * Get the user's city preference.
     */
    public function cityPreference()
    {
        return $this->hasOne(UserCityPreference::class);
    }

    /**
     * Get the user's city admin assignments.
     */
    public function cityAdminAssignments()
    {
        return $this->hasMany(CityAdminAssignment::class);
    }

    /**
     * Get assigned cities for city admin.
     */
    public function assignedCities()
    {
        return $this->belongsToMany(City::class, 'city_admin_assignments')
            ->wherePivot('is_active', true)
            ->withPivot('role_scope')
            ->withTimestamps();
    }

    /**
     * Check if user is banned.
     */
    public function isBanned(): bool
    {
        return $this->status === 'banned';
    }

    /**
     * Ban the user.
     */
    public function ban(): void
    {
        $this->update(['status' => 'banned']);
    }

    /**
     * Unban the user.
     */
    public function unban(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Update last login timestamp.
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Get the driver profile.
     */
    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class);
    }

    /**
     * Check if user is a driver.
     */
    public function isDriver(): bool
    {
        return $this->driverProfile !== null;
    }

    /**
     * Check if user is an approved driver.
     */
    public function isApprovedDriver(): bool
    {
        return $this->driverProfile && $this->driverProfile->isApproved();
    }

    /**
     * Get bookings as rider.
     */
    public function bookingsAsRider()
    {
        return $this->hasMany(Booking::class, 'rider_user_id');
    }

    /**
     * Get support tickets created by the user.
     */
    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }
}
