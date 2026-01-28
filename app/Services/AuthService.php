<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserIdentity;
use App\Models\UserDevice;
use App\Models\SystemSetting;
use App\Models\Otp;
use App\Services\Auth\GoogleTokenVerifier;
use App\Services\Auth\AppleTokenVerifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    protected OtpService $otpService;
    protected GoogleTokenVerifier $googleVerifier;
    protected AppleTokenVerifier $appleVerifier;

    public function __construct(
        OtpService $otpService,
        GoogleTokenVerifier $googleVerifier,
        AppleTokenVerifier $appleVerifier
    ) {
        $this->otpService = $otpService;
        $this->googleVerifier = $googleVerifier;
        $this->appleVerifier = $appleVerifier;
    }

    /**
     * Register user with email/password.
     */
    public function registerWithEmail(string $name, string $email, string $password, ?int $cityId = null): array
    {
        if (!SystemSetting::get('auth.enable_email_password', true)) {
            return [
                'success' => false,
                'message' => 'Email/password registration is disabled.',
            ];
        }

        if (User::where('email', $email)->exists()) {
            return [
                'success' => false,
                'message' => 'Email already registered.',
            ];
        }

        $requireVerification = SystemSetting::get('auth.require_email_verification', false);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => $requireVerification ? null : now(),
            'city_id' => $cityId,
            'auth_provider' => 'email',
            'status' => 'active',
        ]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return [
            'success' => true,
            'message' => 'Registration successful',
            'user' => $user,
            'token' => $token,
            'requires_verification' => $requireVerification,
        ];
    }

    /**
     * Register user with phone OTP.
     */
    public function registerWithPhone(string $name, string $phone, string $countryCode, ?int $cityId = null): array
    {
        if (!SystemSetting::get('auth.enable_phone_otp', true)) {
            return [
                'success' => false,
                'message' => 'Phone OTP registration is disabled.',
            ];
        }

        $fullPhone = $countryCode . $phone;

        if (User::where('phone', $fullPhone)->exists()) {
            return [
                'success' => false,
                'message' => 'Phone number already registered.',
            ];
        }

        // Send OTP
        $otpResult = $this->otpService->send($fullPhone, 'register');

        if (!$otpResult['success']) {
            return $otpResult;
        }

        return [
            'success' => true,
            'message' => 'OTP sent. Please verify to complete registration.',
            'phone' => $fullPhone,
        ];
    }

    /**
     * Complete phone registration after OTP verification.
     */
    public function completePhoneRegistration(string $name, string $phone, string $countryCode, ?int $cityId = null): array
    {
        $fullPhone = $countryCode . $phone;

        $requireVerification = SystemSetting::get('auth.require_phone_verification', false);

        $user = User::create([
            'name' => $name,
            'phone' => $fullPhone,
            'country_code' => $countryCode,
            'phone_verified_at' => $requireVerification ? null : now(),
            'city_id' => $cityId,
            'auth_provider' => 'phone',
            'status' => 'active',
            'password' => Hash::make(uniqid()), // Random password for phone users
        ]);

        $token = $user->createToken('mobile-app')->plainTextToken;
        $user->updateLastLogin();

        return [
            'success' => true,
            'message' => 'Registration successful',
            'user' => $user,
            'token' => $token,
            'requires_verification' => $requireVerification,
        ];
    }

    /**
     * Login with email/password.
     */
    public function loginWithEmail(string $email, string $password): array
    {
        if (!SystemSetting::get('auth.enable_email_password', true)) {
            return [
                'success' => false,
                'message' => 'Email/password login is disabled.',
            ];
        }

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return [
                'success' => false,
                'message' => 'Invalid credentials.',
            ];
        }

        if ($user->isBanned()) {
            return [
                'success' => false,
                'message' => 'Your account has been banned.',
            ];
        }

        if (!$user->is_active) {
            return [
                'success' => false,
                'message' => 'Your account is inactive.',
            ];
        }

        $token = $user->createToken('mobile-app')->plainTextToken;
        $user->updateLastLogin();

        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Login with phone OTP.
     */
    public function loginWithPhone(string $phone, string $countryCode): array
    {
        if (!SystemSetting::get('auth.enable_phone_otp', true)) {
            return [
                'success' => false,
                'message' => 'Phone OTP login is disabled.',
            ];
        }

        $fullPhone = $countryCode . $phone;

        // Send OTP
        $otpResult = $this->otpService->send($fullPhone, 'login');

        if (!$otpResult['success']) {
            return $otpResult;
        }

        return [
            'success' => true,
            'message' => 'OTP sent. Please verify to login.',
            'phone' => $fullPhone,
        ];
    }

    /**
     * Complete phone login after OTP verification.
     */
    public function completePhoneLogin(string $phone, string $countryCode): array
    {
        $fullPhone = $countryCode . $phone;

        $user = User::where('phone', $fullPhone)->first();

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }

        if ($user->isBanned()) {
            return [
                'success' => false,
                'message' => 'Your account has been banned.',
            ];
        }

        if (!$user->is_active) {
            return [
                'success' => false,
                'message' => 'Your account is inactive.',
            ];
        }

        $token = $user->createToken('mobile-app')->plainTextToken;
        $user->updateLastLogin();

        // Update phone verification if needed
        if (!$user->phone_verified_at) {
            $user->update(['phone_verified_at' => now()]);
        }

        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Social login (Google/Apple).
     */
    public function socialLogin(string $provider, string $idToken, ?string $email = null, ?string $name = null): array
    {
        $providerKey = $provider === 'google' ? 'auth.enable_social_google' : 'auth.enable_social_apple';
        
        if (!SystemSetting::get($providerKey, false)) {
            return [
                'success' => false,
                'message' => ucfirst($provider) . ' login is disabled.',
            ];
        }

        // Verify token
        $verifiedData = match ($provider) {
            'google' => $this->googleVerifier->verify($idToken),
            'apple' => $this->appleVerifier->verify($idToken),
            default => null,
        };

        if (!$verifiedData || !isset($verifiedData['sub'])) {
            return [
                'success' => false,
                'message' => 'Invalid or expired token.',
            ];
        }

        $providerUserId = $verifiedData['sub'];
        $verifiedEmail = $verifiedData['email'] ?? $email;
        $verifiedName = $verifiedData['name'] ?? $name;

        // Find existing identity
        $identity = UserIdentity::where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($identity) {
            $user = $identity->user;
        } else {
            // Check if auto-register is enabled
            if (!SystemSetting::get('auth.social_auto_register', true)) {
                return [
                    'success' => false,
                    'message' => 'Account not found. Registration required.',
                ];
            }

            // Create new user
            $user = User::create([
                'name' => $verifiedName ?? 'User',
                'email' => $verifiedEmail,
                'email_verified_at' => $verifiedEmail ? now() : null,
                'password' => Hash::make(uniqid()),
                'auth_provider' => $provider,
                'status' => 'active',
            ]);

            // Create identity
            UserIdentity::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                'email' => $verifiedEmail,
                'meta' => $verifiedData,
            ]);
        }

        if ($user->isBanned()) {
            return [
                'success' => false,
                'message' => 'Your account has been banned.',
            ];
        }

        $token = $user->createToken('mobile-app')->plainTextToken;
        $user->updateLastLogin();

        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ];
    }


    /**
     * Register device.
     */
    public function registerDevice(
        User $user, 
        string $deviceId, 
        string $platform, 
        ?string $fcmToken = null, 
        ?string $appVersion = null,
        ?string $deviceModel = null,
        ?string $osVersion = null
    ): UserDevice {
        // If FCM token is provided, detach it from other users (security)
        if ($fcmToken) {
            UserDevice::where('fcm_token', $fcmToken)
                ->where('user_id', '!=', $user->id)
                ->update(['fcm_token' => null]);
        }

        return UserDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $deviceId,
            ],
            [
                'platform' => $platform,
                'device_model' => $deviceModel,
                'os_version' => $osVersion,
                'fcm_token' => $fcmToken,
                'app_version' => $appVersion,
                'last_seen_at' => now(),
            ]
        );
    }

    /**
     * Unregister device.
     */
    public function unregisterDevice(User $user, string $deviceId): bool
    {
        return UserDevice::where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->delete() > 0;
    }
}

