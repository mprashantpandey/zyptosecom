<?php

namespace App\Core\Providers\Payment;

use App\Core\Contracts\PaymentGatewayInterface;
use App\Core\Services\SecretsService;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PhonePeProvider implements PaymentGatewayInterface
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
        $this->credentials = $this->secrets->getCredentials('payment', 'phonepe', $this->environment);
    }

    protected function getBaseUrl(): string
    {
        return $this->environment === 'sandbox'
            ? 'https://api-preprod.phonepe.com/apis/pg-sandbox'
            : 'https://api.phonepe.com/apis/pg-sandbox'; // Production URL
    }

    protected function generateChecksum(string $payload): string
    {
        $saltKey = $this->credentials['salt_key'] ?? '';
        $saltIndex = $this->credentials['salt_index'] ?? 1;
        
        $string = $payload . '/pg/v1/pay' . $saltKey;
        $sha256 = hash('sha256', $string);
        $finalString = $sha256 . '/pg/v1/pay' . $saltKey;
        
        return base64_encode($finalString);
    }

    protected function verifyChecksum(string $payload, string $checksum): bool
    {
        $expected = $this->generateChecksum($payload);
        return hash_equals($expected, $checksum);
    }

    public function createOrder(Order $order, array $options = []): array
    {
        try {
            $merchantId = $this->credentials['merchant_id'] ?? '';
            $merchantTransactionId = $order->order_number;
            $amount = (int)($order->total_amount * 100); // Convert to paise

            $payload = [
                'merchantId' => $merchantId,
                'merchantTransactionId' => $merchantTransactionId,
                'amount' => $amount,
                'merchantUserId' => (string)$order->user_id,
                'redirectUrl' => $options['return_url'] ?? url('/payment/callback'),
                'redirectMode' => 'POST',
                'callbackUrl' => url('/api/v1/webhooks/phonepe'),
                'mobileNumber' => $order->user->phone ?? '',
                'paymentInstrument' => [
                    'type' => 'PAY_PAGE',
                ],
            ];

            $payloadJson = json_encode($payload);
            $checksum = $this->generateChecksum($payloadJson);

            $requestPayload = [
                'request' => base64_encode($payloadJson),
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-VERIFY' => $checksum . '###' . $this->credentials['salt_index'] ?? 1,
            ])->post($this->getBaseUrl() . '/pay', $requestPayload);

            if ($response->successful()) {
                $data = $response->json();
                $responseData = json_decode(base64_decode($data['data'] ?? ''), true);
                
                return [
                    'payment_id' => $merchantTransactionId,
                    'redirect_url' => $responseData['instrumentResponse']['redirectInfo']['url'] ?? null,
                    'status' => 'created',
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to create PhonePe order: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('PhonePe createOrder failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function verifyWebhook(Request $request): ?array
    {
        try {
            $payload = $request->getContent();
            $xVerify = $request->header('X-VERIFY');
            
            if (empty($xVerify)) {
                Log::warning('PhonePe webhook X-VERIFY header missing');
                return null;
            }

            $parts = explode('###', $xVerify);
            $checksum = $parts[0] ?? '';
            $saltIndex = $parts[1] ?? '';

            // Verify checksum
            if (!$this->verifyChecksum($payload, $checksum)) {
                Log::warning('PhonePe webhook checksum mismatch');
                return null;
            }

            $data = json_decode($payload, true);
            $responseData = json_decode(base64_decode($data['response'] ?? ''), true);
            $transactionData = $responseData['data'] ?? [];

            return [
                'payment_id' => $transactionData['merchantTransactionId'] ?? null,
                'order_id' => $transactionData['merchantTransactionId'] ?? null,
                'status' => $this->mapStatus($transactionData['state'] ?? ''),
                'amount' => ($transactionData['amount'] ?? 0) / 100,
                'metadata' => $data,
            ];

        } catch (\Exception $e) {
            Log::error('PhonePe webhook verification failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function mapStatus(string $state): string
    {
        return match(strtolower($state)) {
            'success', 'paid' => 'paid',
            'failed' => 'failed',
            'refunded' => 'refunded',
            default => 'pending',
        };
    }

    public function capture(string $paymentId, ?float $amount = null): array
    {
        // PhonePe doesn't require manual capture
        return [
            'status' => 'captured',
            'transaction_id' => $paymentId,
            'metadata' => [],
        ];
    }

    public function refund(string $paymentId, ?float $amount = null, string $reason = ''): array
    {
        try {
            $merchantId = $this->credentials['merchant_id'] ?? '';
            $refundTransactionId = 'REFUND_' . $paymentId . '_' . time();
            $refundAmount = (int)(($amount ?? 0) * 100);

            $payload = [
                'merchantId' => $merchantId,
                'merchantUserId' => $paymentId,
                'originalTransactionId' => $paymentId,
                'merchantTransactionId' => $refundTransactionId,
                'amount' => $refundAmount,
                'callbackUrl' => url('/api/v1/webhooks/phonepe'),
            ];

            $payloadJson = json_encode($payload);
            $checksum = $this->generateChecksum($payloadJson);

            $requestPayload = [
                'request' => base64_encode($payloadJson),
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-VERIFY' => $checksum . '###' . ($this->credentials['salt_index'] ?? 1),
            ])->post($this->getBaseUrl() . '/refund', $requestPayload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'refund_id' => $refundTransactionId,
                    'status' => 'processed',
                    'amount' => $amount,
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to refund: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('PhonePe refund failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function fetchStatus(string $paymentId): array
    {
        try {
            $merchantId = $this->credentials['merchant_id'] ?? '';
            $url = $this->getBaseUrl() . "/status/{$merchantId}/{$paymentId}";
            
            $checksum = $this->generateChecksum('');
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-VERIFY' => $checksum . '###' . ($this->credentials['salt_index'] ?? 1),
            ])->get($url);

            if ($response->successful()) {
                $data = $response->json();
                $responseData = json_decode(base64_decode($data['response'] ?? ''), true);
                $transactionData = $responseData['data'] ?? [];
                
                return [
                    'status' => $this->mapStatus($transactionData['state'] ?? ''),
                    'amount' => ($transactionData['amount'] ?? 0) / 100,
                    'currency' => 'INR',
                    'metadata' => $data,
                ];
            }

            throw new \Exception('Failed to fetch status: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('PhonePe fetchStatus failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

