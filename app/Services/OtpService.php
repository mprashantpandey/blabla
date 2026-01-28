<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\SystemSetting;
use App\Services\SmsService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send OTP to phone.
     */
    public function send(string $phone, string $context = 'login'): array
    {
        // Rate limiting
        $key = "otp_send:{$phone}";
        $maxAttempts = 3; // 3 per minute
        $decayMinutes = 1;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return [
                'success' => false,
                'message' => 'Too many OTP requests. Please try again later.',
            ];
        }

        // Daily limit
        $dailyKey = "otp_send_daily:{$phone}:" . now()->format('Y-m-d');
        $dailyMax = 10;

        if (RateLimiter::tooManyAttempts($dailyKey, $dailyMax)) {
            return [
                'success' => false,
                'message' => 'Daily OTP limit reached. Please try again tomorrow.',
            ];
        }

        // Generate code first
        $code = str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Get TTL from settings
        $ttl = (int) SystemSetting::get('auth.otp_ttl_seconds', 300);
        
        // Create OTP record with hashed code
        $otp = Otp::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds($ttl),
            'context' => $context,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Send SMS
        $provider = SystemSetting::get('auth.otp_provider', 'firebase');
        
        if ($provider === 'custom_sms') {
            $sent = $this->smsService->sendOtp($phone, $code);
            
            if (!$sent) {
                $otp->delete();
                return [
                    'success' => false,
                    'message' => 'Failed to send OTP. Please try again.',
                ];
            }
        }

        RateLimiter::hit($key, $decayMinutes * 60);
        RateLimiter::hit($dailyKey, 86400);

        return [
            'success' => true,
            'message' => $provider === 'firebase' 
                ? 'OTP will be sent via Firebase' 
                : 'OTP sent successfully',
            'provider' => $provider,
        ];
    }

    /**
     * Verify OTP.
     */
    public function verify(string $phone, string $code, string $context = 'login'): array
    {
        // Rate limiting for verification
        $key = "otp_verify:{$phone}";
        $maxAttempts = 5; // 5 attempts per minute
        $decayMinutes = 1;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return [
                'success' => false,
                'message' => 'Too many verification attempts. Please request a new OTP.',
            ];
        }

        // Find valid OTP
        $otp = Otp::where('phone', $phone)
            ->where('context', $context)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (!$otp) {
            RateLimiter::hit($key, $decayMinutes * 60);
            return [
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ];
        }

        // Verify code
        if ($otp->verify($code)) {
            RateLimiter::clear($key);
            return [
                'success' => true,
                'message' => 'OTP verified successfully',
                'otp' => $otp,
            ];
        }

        RateLimiter::hit($key, $decayMinutes * 60);
        return [
            'success' => false,
            'message' => 'Invalid OTP code.',
        ];
    }
}

