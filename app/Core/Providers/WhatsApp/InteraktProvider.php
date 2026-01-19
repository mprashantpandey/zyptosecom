<?php

namespace App\Core\Providers\WhatsApp;

use App\Core\Services\SecretsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InteraktProvider
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
        $this->credentials = $this->secrets->getCredentials('whatsapp', 'interakt', $this->environment);
    }

    protected function getBaseUrl(): string
    {
        return 'https://api.interakt.ai/v1/public';
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . ($this->credentials['api_key'] ?? ''),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Send WhatsApp message
     */
    public function sendWhatsApp(string $to, string $message, ?string $templateName = null, array $templateParams = []): array
    {
        try {
            $phoneNumberId = $this->credentials['phone_number_id'] ?? '';

            if ($templateName) {
                // Send template message
                $payload = [
                    'fullPhoneNumber' => $to,
                    'templateName' => $templateName,
                    'templateParams' => $templateParams,
                ];

                $response = Http::withHeaders($this->getHeaders())
                    ->post($this->getBaseUrl() . '/message/send-template', $payload);
            } else {
                // Send simple text message
                $payload = [
                    'fullPhoneNumber' => $to,
                    'message' => $message,
                ];

                $response = Http::withHeaders($this->getHeaders())
                    ->post($this->getBaseUrl() . '/message/send', $payload);
            }

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => 'sent',
                    'external_id' => $data['messageId'] ?? null,
                    'message' => 'WhatsApp message sent successfully',
                ];
            }

            throw new \Exception('Failed to send WhatsApp: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Interakt sendWhatsApp failed', [
                'to' => substr($to, 0, 5) . '...',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

