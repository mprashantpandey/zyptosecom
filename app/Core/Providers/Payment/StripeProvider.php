<?php

namespace App\Core\Providers\Payment;

use App\Core\Contracts\PaymentGatewayInterface;
use App\Core\Services\SecretsService;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeProvider implements PaymentGatewayInterface
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
        $this->credentials = $this->secrets->getCredentials('payment', 'stripe', $this->environment);
    }

    protected function getBaseUrl(): string
    {
        return 'https://api.stripe.com/v1';
    }

    protected function getSecretKey(): string
    {
        return $this->credentials['secret_key'] ?? '';
    }

    public function createOrder(Order $order, array $options = []): array
    {
        try {
            $amount = (int)($order->total_amount * 100); // Convert to cents
            
            $payload = [
                'amount' => $amount,
                'currency' => strtolower($order->currency ?? 'usd'),
                'metadata' => [
                    'order_id' => (string)$order->id,
                    'order_number' => $order->order_number,
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ];

            // Add return URL if provided
            if (isset($options['return_url'])) {
                $payload['return_url'] = $options['return_url'];
            }

            $response = Http::withBasicAuth($this->getSecretKey(), '')
                ->asForm()
                ->post($this->getBaseUrl() . '/payment_intents', $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'payment_id' => $data['id'] ?? null,
                    'client_secret' => $data['client_secret'] ?? null,
                    'redirect_url' => null, // Stripe uses client-side integration
                    'status' => $data['status'] ?? 'created',
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to create Stripe PaymentIntent: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Stripe createOrder failed', [
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
                Log::warning('Stripe webhook secret not configured');
                return null;
            }

            $payload = $request->getContent();
            $signature = $request->header('Stripe-Signature');

            if (empty($signature)) {
                Log::warning('Stripe webhook signature missing');
                return null;
            }

            // Verify signature using Stripe's method
            $timestamp = null;
            $signatures = [];
            
            foreach (explode(',', $signature) as $sig) {
                $parts = explode('=', $sig);
                if ($parts[0] === 't') {
                    $timestamp = $parts[1];
                } elseif ($parts[0] === 'v1') {
                    $signatures[] = $parts[1];
                }
            }

            if (!$timestamp || empty($signatures)) {
                return null;
            }

            // Check timestamp (prevent replay attacks)
            if (abs(time() - $timestamp) > 300) { // 5 minutes
                Log::warning('Stripe webhook timestamp too old');
                return null;
            }

            // Compute expected signature
            $signedPayload = $timestamp . '.' . $payload;
            $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

            // Verify signature
            $isValid = false;
            foreach ($signatures as $sig) {
                if (hash_equals($expectedSignature, $sig)) {
                    $isValid = true;
                    break;
                }
            }

            if (!$isValid) {
                Log::warning('Stripe webhook signature mismatch');
                return null;
            }

            $data = json_decode($payload, true);
            $event = $data['type'] ?? null;
            $eventData = $data['data']['object'] ?? [];

            return [
                'payment_id' => $eventData['id'] ?? null,
                'order_id' => $eventData['metadata']['order_id'] ?? null,
                'status' => $this->mapEventToStatus($event, $eventData),
                'amount' => ($eventData['amount'] ?? 0) / 100,
                'event_type' => $event,
                'event_id' => $data['id'] ?? null,
                'metadata' => $data,
            ];

        } catch (\Exception $e) {
            Log::error('Stripe webhook verification failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function mapEventToStatus(string $event, array $data): string
    {
        return match($event) {
            'payment_intent.succeeded', 'charge.succeeded' => 'paid',
            'payment_intent.payment_failed', 'charge.failed' => 'failed',
            'charge.refunded', 'refund.created', 'refund.updated' => 'refunded',
            default => 'pending',
        };
    }

    public function capture(string $paymentId, ?float $amount = null): array
    {
        try {
            $payload = [];
            if ($amount !== null) {
                $payload['amount_to_capture'] = (int)($amount * 100);
            }

            $response = Http::withBasicAuth($this->getSecretKey(), '')
                ->asForm()
                ->post($this->getBaseUrl() . "/payment_intents/{$paymentId}/capture", $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => $data['status'] ?? 'succeeded',
                    'transaction_id' => $data['id'] ?? $paymentId,
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to capture payment: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Stripe capture failed', [
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
                'payment_intent' => $paymentId,
            ];
            
            if ($amount !== null) {
                $payload['amount'] = (int)($amount * 100);
            }

            if (!empty($reason)) {
                $payload['reason'] = $reason;
            }

            $response = Http::withBasicAuth($this->getSecretKey(), '')
                ->asForm()
                ->post($this->getBaseUrl() . '/refunds', $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'refund_id' => $data['id'] ?? null,
                    'status' => $data['status'] ?? 'succeeded',
                    'amount' => ($data['amount'] ?? 0) / 100,
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to refund: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Stripe refund failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function fetchStatus(string $paymentId): array
    {
        try {
            $response = Http::withBasicAuth($this->getSecretKey(), '')
                ->get($this->getBaseUrl() . "/payment_intents/{$paymentId}");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => $data['status'] ?? 'unknown',
                    'amount' => ($data['amount'] ?? 0) / 100,
                    'currency' => strtoupper($data['currency'] ?? 'usd'),
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to fetch status: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Stripe fetchStatus failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

