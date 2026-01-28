<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use phpseclib3\Crypt\RSA;
use phpseclib3\Math\BigInteger;

class AppleTokenVerifier
{
    protected ?array $publicKeys = null;

    /**
     * Verify Apple identity token.
     */
    public function verify(string $identityToken): ?array
    {
        try {
            // Get Apple's public keys
            $publicKeys = $this->getApplePublicKeys();
            if (empty($publicKeys)) {
                Log::error('Failed to fetch Apple public keys');
                return null;
            }

            // Decode token header to get key ID
            $parts = explode('.', $identityToken);
            if (count($parts) !== 3) {
                return null;
            }

            $header = json_decode(base64_decode($parts[0]), true);
            $kid = $header['kid'] ?? null;

            if (!$kid || !isset($publicKeys[$kid])) {
                Log::warning('Apple token key ID not found', ['kid' => $kid]);
                return null;
            }

            // Verify token
            $keyData = $publicKeys[$kid];
            // Convert JWK to PEM format for verification
            $pem = $this->jwkToPem($keyData);
            $decoded = JWT::decode($identityToken, new Key($pem, 'RS256'));

            // Validate issuer
            if ($decoded->iss !== 'https://appleid.apple.com') {
                Log::warning('Apple token invalid issuer', ['iss' => $decoded->iss]);
                return null;
            }

            // Validate audience (client ID)
            $clientId = SystemSetting::get('auth.apple_client_id');
            if (!empty($clientId) && $decoded->aud !== $clientId) {
                Log::warning('Apple token audience mismatch', [
                    'aud' => $decoded->aud,
                    'expected' => $clientId,
                ]);
                return null;
            }

            // Validate expiration
            if (isset($decoded->exp) && $decoded->exp < time()) {
                Log::warning('Apple token expired');
                return null;
            }

            return [
                'sub' => $decoded->sub ?? null,
                'email' => $decoded->email ?? null,
                'email_verified' => $decoded->email_verified ?? false,
            ];
        } catch (\Exception $e) {
            Log::error('Apple token verification error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get Apple's public keys.
     */
    protected function getApplePublicKeys(): array
    {
        if ($this->publicKeys !== null) {
            return $this->publicKeys;
        }

        try {
            $response = Http::get('https://appleid.apple.com/auth/keys');
            
            if (!$response->successful()) {
                return [];
            }

            $keys = $response->json();
            $publicKeys = [];

            foreach ($keys['keys'] ?? [] as $key) {
                $publicKeys[$key['kid']] = $key;
            }

            $this->publicKeys = $publicKeys;
            return $publicKeys;
        } catch (\Exception $e) {
            Log::error('Failed to fetch Apple public keys', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Convert JWK to PEM format.
     */
    protected function jwkToPem(array $jwk): string
    {
        try {
            $rsa = RSA::loadPublicKey([
                'n' => new BigInteger($this->base64UrlDecode($jwk['n']), 256),
                'e' => new BigInteger($this->base64UrlDecode($jwk['e']), 256),
            ]);
            return $rsa->toString('PKCS8');
        } catch (\Exception $e) {
            Log::error('Failed to convert JWK to PEM', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Base64 URL decode.
     */
    protected function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

