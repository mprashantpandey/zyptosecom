<?php

namespace App\Core\Providers\Payment;

use App\Core\Contracts\PaymentGatewayInterface;
use App\Core\Services\SecretsService;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RazorpayProvider implements PaymentGatewayInterface
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
        $this->credentials = $this->secrets->getCredentials('payment', 'razorpay', $this->environment);
    }

    protected function getBaseUrl(): string
    {
        return $this->environment === 'sandbox' 
            ? 'https://api.razorpay.com/v1'
            : 'https://api.razorpay.com/v1';
    }

    protected function getAuthHeader(): string
    {
        $keyId = $this->credentials['key_id'] ?? '';
        $keySecret = $this->credentials['key_secret'] ?? '';
        return 'Basic ' . base64_encode("{$keyId}:{$keySecret}");
    }

    public function createOrder(Order $order, array $options = []): array
    {
        try {
            $amount = (int)($order->total_amount * 100); // Convert to paise
            
            $payload = [
                'amount' => $amount,
                'currency' => $order->currency ?? 'INR',
                'receipt' => $order->order_number,
                'notes' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => $this->getAuthHeader(),
                'Content-Type' => 'application/json',
            ])->post($this->getBaseUrl() . '/orders', $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'payment_id' => $data['id'] ?? null,
                    'redirect_url' => null, // Razorpay uses client-side integration
                    'status' => 'created',
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to create Razorpay order: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Razorpay createOrder failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function verifyWebhook(Request $request): ?array
    {
        try {
            $webhookSecret = $this->credentials['webhook_secret'] ?? '';
            if (empty($webhookSecret)) {
                Log::warning('Razorpay webhook secret not configured');
                return null;
            }

            $payload = $request->getContent();
            $signature = $request->header('X-Razorpay-Signature');

            // Verify signature
            $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
            
            if (!hash_equals($expectedSignature, $signature)) {
                Log::warning('Razorpay webhook signature mismatch');
                return null;
            }

            $data = json_decode($payload, true);
            
            return [
                'payment_id' => $data['payload']['payment']['entity']['id'] ?? null,
                'order_id' => $data['payload']['payment']['entity']['order_id'] ?? null,
                'status' => $data['payload']['payment']['entity']['status'] ?? null,
                'amount' => ($data['payload']['payment']['entity']['amount'] ?? 0) / 100,
                'metadata' => $data,
            ];

        } catch (\Exception $e) {
            Log::error('Razorpay webhook verification failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function capture(string $paymentId, ?float $amount = null): array
    {
        try {
            $payload = [];
            if ($amount !== null) {
                $payload['amount'] = (int)($amount * 100);
            }

            $response = Http::withHeaders([
                'Authorization' => $this->getAuthHeader(),
                'Content-Type' => 'application/json',
            ])->post($this->getBaseUrl() . "/payments/{$paymentId}/capture", $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => $data['status'] ?? 'captured',
                    'transaction_id' => $data['id'] ?? $paymentId,
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to capture payment: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Razorpay capture failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function refund(string $paymentId, ?float $amount = null, string $reason = ''): array
    {
        try {
            $payload = [
                'notes' => ['reason' => $reason],
            ];
            
            if ($amount !== null) {
                $payload['amount'] = (int)($amount * 100);
            }

            $response = Http::withHeaders([
                'Authorization' => $this->getAuthHeader(),
                'Content-Type' => 'application/json',
            ])->post($this->getBaseUrl() . "/payments/{$paymentId}/refund", $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'refund_id' => $data['id'] ?? null,
                    'status' => $data['status'] ?? 'processed',
                    'amount' => ($data['amount'] ?? 0) / 100,
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to refund: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Razorpay refund failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function fetchStatus(string $paymentId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->getAuthHeader(),
            ])->get($this->getBaseUrl() . "/payments/{$paymentId}");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => $data['status'] ?? 'unknown',
                    'amount' => ($data['amount'] ?? 0) / 100,
                    'currency' => $data['currency'] ?? 'INR',
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to fetch status: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Razorpay fetchStatus failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

