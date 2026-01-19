<?php

namespace App\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmHttpV1Client
{
    protected FcmAccessTokenService $tokenService;
    protected ?string $projectId = null;
    protected string $providerKey;
    protected string $environment;

    public function __construct(
        FcmAccessTokenService $tokenService,
        string $providerKey = 'firebase_fcm_v1',
        string $environment = 'production'
    ) {
        $this->tokenService = $tokenService;
        $this->providerKey = $providerKey;
        $this->environment = $environment;
        $this->projectId = $tokenService->getProjectId($providerKey, $environment);
    }

    /**
     * Send FCM message to a device token
     * 
     * @param string $token Device FCM token
     * @param array $messagePayload Message payload (notification, data, android, apns, webpush)
     * @return array Response with status and external_id
     * @throws \Exception
     */
    public function sendToToken(string $token, array $messagePayload): array
    {
        if (empty($this->projectId)) {
            throw new \Exception("Firebase project ID not configured");
        }

        try {
            $accessToken = $this->tokenService->getAccessToken($this->providerKey, $this->environment);

            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

            $payload = [
                'message' => array_merge([
                    'token' => $token,
                ], $messagePayload),
            ];

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'status' => 'sent',
                    'external_id' => $responseData['name'] ?? null,
                ];
            } else {
                $error = $response->json();
                $errorMessage = $error['error']['message'] ?? $response->body();
                
                Log::error('FCM HTTP v1 send failed', [
                    'token' => substr($token, 0, 20) . '...', // Partial token for logging
                    'error' => $errorMessage,
                    'status' => $response->status(),
                ]);

                return [
                    'status' => 'failed',
                    'error' => $errorMessage,
                ];
            }

        } catch (\Exception $e) {
            Log::error('FCM HTTP v1 client error', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 20) . '...',
            ]);

            throw $e;
        }
    }

    /**
     * Send notification message (simplified)
     */
    public function sendNotification(
        string $token,
        string $title,
        string $body,
        ?array $data = null,
        ?array $options = null
    ): array {
        $messagePayload = [
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
        ];

        if ($data) {
            $messagePayload['data'] = $data;
        }

        // Android-specific options
        if (isset($options['android'])) {
            $messagePayload['android'] = $options['android'];
        }

        // iOS-specific options
        if (isset($options['apns'])) {
            $messagePayload['apns'] = $options['apns'];
        }

        // Web push options
        if (isset($options['webpush'])) {
            $messagePayload['webpush'] = $options['webpush'];
        }

        return $this->sendToToken($token, $messagePayload);
    }
}

