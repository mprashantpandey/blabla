<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\SystemSetting;

class GoogleTokenVerifier
{
    /**
     * Verify Google ID token.
     */
    public function verify(string $idToken): ?array
    {
        try {
            // Use Google's tokeninfo endpoint
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

            if (!$response->successful()) {
                Log::warning('Google token verification failed', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $data = $response->json();

            // Validate audience (client ID)
            $clientIds = $this->getClientIds();
            if (!empty($clientIds) && !in_array($data['aud'] ?? '', $clientIds)) {
                Log::warning('Google token audience mismatch', [
                    'aud' => $data['aud'] ?? null,
                ]);
                return null;
            }

            // Validate email verification if required
            $requireEmailVerification = SystemSetting::get('auth.require_email_verification', false);
            if ($requireEmailVerification && empty($data['email_verified'])) {
                Log::warning('Google token email not verified');
                return null;
            }

            return [
                'sub' => $data['sub'] ?? null,
                'email' => $data['email'] ?? null,
                'email_verified' => $data['email_verified'] ?? false,
                'name' => $data['name'] ?? null,
                'picture' => $data['picture'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Google token verification error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get configured Google client IDs.
     */
    protected function getClientIds(): array
    {
        $clientIds = SystemSetting::get('auth.google_client_ids', '');
        if (empty($clientIds)) {
            return [];
        }

        return is_array($clientIds) ? $clientIds : explode(',', $clientIds);
    }
}

