<?php

namespace App\Core\Providers\Payment;

use App\Core\Contracts\PaymentGatewayInterface;
use App\Core\Services\SecretsService;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CashfreeProvider implements PaymentGatewayInterface
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
        $this->credentials = $this->secrets->getCredentials('payment', 'cashfree', $this->environment);
    }

    protected function getBaseUrl(): string
    {
        return $this->environment === 'sandbox'
            ? 'https://sandbox.cashfree.com/pg'
            : 'https://api.cashfree.com/pg';
    }

    protected function getHeaders(): array
    {
        return [
            'x-client-id' => $this->credentials['app_id'] ?? '',
            'x-client-secret' => $this->credentials['secret_key'] ?? '',
            'x-api-version' => '2022-09-01',
            'Content-Type' => 'application/json',
        ];
    }

    public function createOrder(Order $order, array $options = []): array
    {
        try {
            $payload = [
                'order_id' => $order->order_number,
                'order_amount' => $order->total_amount,
                'order_currency' => $order->currency ?? 'INR',
                'order_note' => 'Order #' . $order->order_number,
                'customer_details' => [
                    'customer_id' => (string)$order->user_id,
                    'customer_name' => $order->user->name ?? 'Customer',
                    'customer_email' => $order->user->email ?? '',
                    'customer_phone' => $order->user->phone ?? '',
                ],
            ];

            if (isset($options['return_url'])) {
                $payload['order_meta'] = [
                    'return_url' => $options['return_url'],
                ];
            }

            $response = Http::withHeaders($this->getHeaders())
                ->post($this->getBaseUrl() . '/orders', $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'payment_id' => $data['payment_session_id'] ?? null,
                    'redirect_url' => $data['payment_link'] ?? null,
                    'status' => 'created',
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to create Cashfree order: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Cashfree createOrder failed', [
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
                Log::warning('Cashfree webhook secret not configured');
                return null;
            }

            $payload = $request->getContent();
            $signature = $request->header('x-cashfree-signature');

            if (empty($signature)) {
                Log::warning('Cashfree webhook signature missing');
                return null;
            }

            // Verify signature
            $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
            
            if (!hash_equals($expectedSignature, $signature)) {
                Log::warning('Cashfree webhook signature mismatch');
                return null;
            }

            $data = json_decode($payload, true);
            $orderData = $data['data'] ?? [];

            return [
                'payment_id' => $orderData['payment_session_id'] ?? null,
                'order_id' => $orderData['order_id'] ?? null,
                'status' => $this->mapStatus($orderData['payment_status'] ?? ''),
                'amount' => $orderData['order_amount'] ?? 0,
                'metadata' => $data,
            ];

        } catch (\Exception $e) {
            Log::error('Cashfree webhook verification failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function mapStatus(string $status): string
    {
        return match(strtolower($status)) {
            'success', 'paid' => 'paid',
            'failed' => 'failed',
            'refunded' => 'refunded',
            default => 'pending',
        };
    }

    public function capture(string $paymentId, ?float $amount = null): array
    {
        // Cashfree doesn't require manual capture for most flows
        return [
            'status' => 'captured',
            'transaction_id' => $paymentId,
            'metadata' => [],
        ];
    }

    public function refund(string $paymentId, ?float $amount = null, string $reason = ''): array
    {
        try {
            $payload = [
                'refund_amount' => $amount ?? null,
                'refund_note' => $reason,
            ];

            $response = Http::withHeaders($this->getHeaders())
                ->post($this->getBaseUrl() . "/orders/{$paymentId}/refund", $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'refund_id' => $data['refund_id'] ?? null,
                    'status' => $data['refund_status'] ?? 'processed',
                    'amount' => $data['refund_amount'] ?? $amount,
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to refund: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Cashfree refund failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function fetchStatus(string $paymentId): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->getBaseUrl() . "/orders/{$paymentId}");

            if ($response->successful()) {
                $data = $response->json();
                $orderData = $data['data'] ?? [];
                return [
                    'status' => $this->mapStatus($orderData['payment_status'] ?? ''),
                    'amount' => $orderData['order_amount'] ?? 0,
                    'currency' => $orderData['order_currency'] ?? 'INR',
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to fetch status: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Cashfree fetchStatus failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

