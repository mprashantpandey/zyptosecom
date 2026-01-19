<?php

namespace App\Core\Services;

use App\Core\Services\SecretsService;
use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\OAuth2;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FcmAccessTokenService
{
    protected SecretsService $secretsService;
    protected ?string $cachedToken = null;
    protected ?int $cachedExpiry = null;

    public function __construct(SecretsService $secretsService)
    {
        $this->secretsService = $secretsService;
    }

    /**
     * Get OAuth2 access token for FCM
     * 
     * @param string $providerKey Provider key (e.g., 'firebase_fcm_v1')
     * @param string $environment Environment (sandbox|production)
     * @return string Access token
     * @throws \Exception
     */
    public function getAccessToken(string $providerKey = 'firebase_fcm_v1', string $environment = 'production'): string
    {
        $cacheKey = "fcm_access_token:{$providerKey}:{$environment}";

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached && isset($cached['token']) && isset($cached['expires_at'])) {
            // If token expires in more than 5 minutes, use cached
            if ($cached['expires_at'] > now()->addMinutes(5)->timestamp) {
                return $cached['token'];
            }
        }

        // Generate new token
        try {
            $serviceAccountJson = $this->getServiceAccountJson($providerKey, $environment);
            
            if (empty($serviceAccountJson)) {
                throw new \Exception("Service account JSON not found for provider: {$providerKey}");
            }

            $serviceAccount = json_decode($serviceAccountJson, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid service account JSON");
            }

            // Create credentials
            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/firebase.messaging'],
                $serviceAccount
            );

            // Fetch access token
            $token = $credentials->fetchAuthToken();

            if (!isset($token['access_token'])) {
                throw new \Exception("Failed to obtain access token");
            }

            $accessToken = $token['access_token'];
            $expiresAt = $token['expires'] ?? (time() + 3600); // Default 1 hour

            // Cache token (with 5 minute buffer)
            Cache::put($cacheKey, [
                'token' => $accessToken,
                'expires_at' => $expiresAt,
            ], now()->addSeconds($expiresAt - time() - 300)); // Cache until 5 min before expiry

            return $accessToken;

        } catch (\Exception $e) {
            Log::error('FCM access token generation failed', [
                'provider' => $providerKey,
                'environment' => $environment,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get service account JSON from encrypted secrets
     */
    protected function getServiceAccountJson(string $providerKey, string $environment): ?string
    {
        $credentials = $this->secretsService->getCredentials(
            'notification',
            $providerKey,
            $environment
        );

        // Service account JSON might be stored as a single encrypted value
        // or as individual fields that we reconstruct
        if (isset($credentials['service_account_json'])) {
            return $credentials['service_account_json'];
        }

        // Alternative: reconstruct from individual fields if stored separately
        // This is less common but some setups do this
        if (isset($credentials['type']) && isset($credentials['project_id'])) {
            // Reconstruct JSON from fields
            $json = [
                'type' => $credentials['type'],
                'project_id' => $credentials['project_id'],
                'private_key_id' => $credentials['private_key_id'] ?? '',
                'private_key' => $credentials['private_key'] ?? '',
                'client_email' => $credentials['client_email'] ?? '',
                'client_id' => $credentials['client_id'] ?? '',
                'auth_uri' => $credentials['auth_uri'] ?? 'https://accounts.google.com/o/oauth2/auth',
                'token_uri' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            ];
            return json_encode($json);
        }

        return null;
    }

    /**
     * Extract project ID from service account JSON
     */
    public function getProjectId(string $providerKey = 'firebase_fcm_v1', string $environment = 'production'): ?string
    {
        try {
            $serviceAccountJson = $this->getServiceAccountJson($providerKey, $environment);
            if (empty($serviceAccountJson)) {
                return null;
            }

            $serviceAccount = json_decode($serviceAccountJson, true);
            return $serviceAccount['project_id'] ?? null;
        } catch (\Exception $e) {
            Log::error('Failed to extract project ID', [
                'provider' => $providerKey,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

