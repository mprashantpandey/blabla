<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\OtpSendRequest;
use App\Http\Requests\Auth\OtpVerifyRequest;
use App\Http\Requests\Auth\SocialLoginRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\AuthService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends BaseController
{
    protected AuthService $authService;
    protected OtpService $otpService;

    public function __construct(AuthService $authService, OtpService $otpService)
    {
        $this->authService = $authService;
        $this->otpService = $otpService;
    }

    /**
     * Register user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $method = $request->input('method');

        if ($method === 'email') {
            $result = $this->authService->registerWithEmail(
                $request->name,
                $request->email,
                $request->password,
                $request->city_id
            );
        } elseif ($method === 'phone') {
            $result = $this->authService->registerWithPhone(
                $request->name,
                $request->phone,
                $request->country_code,
                $request->city_id
            );
        } else {
            return $this->error('Invalid registration method.');
        }

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->success($result, $result['message']);
    }

    /**
     * Login user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $method = $request->input('method');

        if ($method === 'email') {
            $result = $this->authService->loginWithEmail(
                $request->email,
                $request->password
            );
        } elseif ($method === 'phone') {
            $result = $this->authService->loginWithPhone(
                $request->phone,
                $request->country_code
            );
        } else {
            return $this->error('Invalid login method.');
        }

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->success($result, $result['message']);
    }

    /**
     * Send OTP.
     */
    public function sendOtp(OtpSendRequest $request): JsonResponse
    {
        $fullPhone = $request->country_code . $request->phone;
        
        $result = $this->otpService->send($fullPhone, $request->context);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->success([
            'phone' => $fullPhone,
            'provider' => $result['provider'] ?? null,
        ], $result['message']);
    }

    /**
     * Verify OTP.
     */
    public function verifyOtp(OtpVerifyRequest $request): JsonResponse
    {
        $fullPhone = $request->country_code . $request->phone;
        
        $result = $this->otpService->verify($fullPhone, $request->code, $request->context);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        // If context is register, complete registration
        if ($request->context === 'register') {
            // Get name from request (should be stored temporarily or sent again)
            $name = $request->input('name');
            if (!$name) {
                return $this->error('Name is required for registration.');
            }

            $registerResult = $this->authService->completePhoneRegistration(
                $name,
                $request->phone,
                $request->country_code,
                $request->city_id
            );

            if (!$registerResult['success']) {
                return $this->error($registerResult['message']);
            }

            return $this->success($registerResult, $registerResult['message']);
        }

        // If context is login, complete login
        if ($request->context === 'login') {
            $loginResult = $this->authService->completePhoneLogin(
                $request->phone,
                $request->country_code
            );

            if (!$loginResult['success']) {
                return $this->error($loginResult['message']);
            }

            return $this->success($loginResult, $loginResult['message']);
        }

        return $this->success(['verified' => true], 'OTP verified successfully');
    }

    /**
     * Social login (Google/Apple).
     */
    public function socialLogin(SocialLoginRequest $request): JsonResponse
    {
        $result = $this->authService->socialLogin(
            $request->provider,
            $request->id_token,
            $request->email,
            $request->name
        );

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        return $this->success($result, $result['message']);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Get authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('city', 'devices');

        return $this->success($user, 'User retrieved successfully');
    }

    /**
     * Forgot password.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return $this->success(null, 'Password reset link sent to your email.');
        }

        return $this->error('Unable to send reset link.');
    }

    /**
     * Reset password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(null, 'Password reset successfully.');
        }

        return $this->error('Unable to reset password.');
    }
}
