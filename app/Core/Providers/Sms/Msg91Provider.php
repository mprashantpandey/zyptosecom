<?php

namespace App\Core\Providers\Sms;

use App\Core\Services\SecretsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Msg91Provider
{
    protected SecretsService $secrets;
    protected string $environment;
    protected array $credentials;

    public function __construct(SecretsService $secrets, string $environment = 'sandbox')
    {
        $this->secrets = $secrets;
        $this->environment = $environment;
        $this->loadCredentials();
    }

    protected function loadCredentials(): void
    {
        $this->credentials = $this->secrets->getCredentials('sms', 'msg91', $this->environment);
    }

    protected function getBaseUrl(): string
    {
        return 'https://control.msg91.com/api/v5/flow/';
    }

    /**
     * Send SMS
     */
    public function sendSms(string $to, string $message): array
    {
        try {
            $authKey = $this->credentials['auth_key'] ?? '';
            $senderId = $this->credentials['sender_id'] ?? '';
            $route = $this->credentials['route'] ?? '4'; // 4 = Transactional, 1 = Promotional

            $payload = [
                'flow_id' => null, // For template-based, use flow_id
                'sender' => $senderId,
                'mobiles' => $to,
                'message' => $message,
            ];

            // Use flow API for transactional SMS
            $response = Http::withHeaders([
                'authkey' => $authKey,
                'Content-Type' => 'application/json',
            ])->post($this->getBaseUrl(), $payload);

            // Alternative: Use simple send API
            if (!$response->successful()) {
                $simpleUrl = 'https://control.msg91.com/api/sendhttp.php';
                $params = [
                    'authkey' => $authKey,
                    'mobiles' => $to,
                    'message' => urlencode($message),
                    'sender' => $senderId,
                    'route' => $route,
                    'country' => '91', // India
                ];

                $response = Http::get($simpleUrl, $params);
            }

            if ($response->successful() || $response->status() === 200) {
                $responseBody = $response->body();
                // MSG91 returns request ID on success
                return [
                    'status' => 'sent',
                    'external_id' => $responseBody,
                    'message' => 'SMS sent successfully',
                ];
            }

            throw new \Exception('Failed to send SMS: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('MSG91 sendSms failed', [
                'to' => substr($to, 0, 5) . '...', // Partial for logging
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send OTP
     */
    public function sendOtp(string $to): array
    {
        try {
            $authKey = $this->credentials['auth_key'] ?? '';
            $otp = str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);

            $response = Http::withHeaders([
                'authkey' => $authKey,
                'Content-Type' => 'application/json',
            ])->post('https://control.msg91.com/api/v5/otp', [
                'template_id' => null, // Use template ID if configured
                'mobile' => $to,
                'otp' => $otp,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => 'sent',
                    'otp' => $otp, // Return OTP for verification (in production, store in cache)
                    'external_id' => $data['request_id'] ?? null,
                ];
            }

            throw new \Exception('Failed to send OTP: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('MSG91 sendOtp failed', [
                'to' => substr($to, 0, 5) . '...',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(string $to, string $otp): bool
    {
        try {
            $authKey = $this->credentials['auth_key'] ?? '';

            $response = Http::withHeaders([
                'authkey' => $authKey,
                'Content-Type' => 'application/json',
            ])->post('https://control.msg91.com/api/v5/otp/verify', [
                'mobile' => $to,
                'otp' => $otp,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return ($data['type'] ?? '') === 'success';
            }

            return false;

        } catch (\Exception $e) {
            Log::error('MSG91 verifyOtp failed', [
                'to' => substr($to, 0, 5) . '...',
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

