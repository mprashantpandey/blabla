<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class Otp extends Model
{
    protected $fillable = [
        'phone',
        'code_hash',
        'expires_at',
        'attempts',
        'used_at',
        'context',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * Generate and store OTP.
     */
    public static function generate(string $phone, string $context = 'login', ?string $ipAddress = null, ?string $userAgent = null): self
    {
        // Generate 6-digit OTP
        $code = str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash = Hash::make($code);

        // Get TTL from settings (default 5 minutes)
        $ttl = (int) SystemSetting::get('auth.otp_ttl_seconds', 300);
        
        return self::create([
            'phone' => $phone,
            'code_hash' => $codeHash,
            'expires_at' => now()->addSeconds($ttl),
            'context' => $context,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
        ]);
    }

    /**
     * Verify OTP code.
     */
    public function verify(string $code): bool
    {
        if ($this->used_at !== null) {
            return false;
        }

        if ($this->expires_at->isPast()) {
            return false;
        }

        // Check max attempts
        $maxAttempts = (int) SystemSetting::get('auth.otp_max_attempts', 5);
        if ($this->attempts >= $maxAttempts) {
            return false;
        }

        $this->increment('attempts');

        if (Hash::check($code, $this->code_hash)) {
            $this->update(['used_at' => now()]);
            return true;
        }

        return false;
    }

    /**
     * Check if OTP is valid (not used and not expired).
     */
    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    /**
     * Clean up expired OTPs.
     */
    public static function cleanup(): void
    {
        self::where('expires_at', '<', now())
            ->orWhere('used_at', '!=', null)
            ->where('created_at', '<', now()->subDays(1))
            ->delete();
    }
}
