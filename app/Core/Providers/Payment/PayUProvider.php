<?php

namespace App\Core\Providers\Payment;

use App\Core\Contracts\PaymentGatewayInterface;
use App\Core\Services\SecretsService;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayUProvider implements PaymentGatewayInterface
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
        $this->credentials = $this->secrets->getCredentials('payment', 'payu', $this->environment);
    }

    protected function getBaseUrl(): string
    {
        return $this->environment === 'sandbox'
            ? 'https://sandboxsecure.payu.in'
            : 'https://secure.payu.in';
    }

    protected function generateHash(array $params): string
    {
        $merchantSalt = $this->credentials['merchant_salt'] ?? '';
        $hashString = '';
        
        // PayU hash order: key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|||||||salt
        $hashString = ($params['key'] ?? '') . '|' .
                     ($params['txnid'] ?? '') . '|' .
                     ($params['amount'] ?? '') . '|' .
                     ($params['productinfo'] ?? '') . '|' .
                     ($params['firstname'] ?? '') . '|' .
                     ($params['email'] ?? '') . '|' .
                     ($params['udf1'] ?? '') . '|' .
                     ($params['udf2'] ?? '') . '|' .
                     ($params['udf3'] ?? '') . '|' .
                     ($params['udf4'] ?? '') . '|' .
                     ($params['udf5'] ?? '') . '|||||||' . $merchantSalt;
        
        return strtolower(hash('sha512', $hashString));
    }

    protected function verifyHash(array $params): bool
    {
        $receivedHash = $params['hash'] ?? '';
        unset($params['hash']);
        $expectedHash = $this->generateHash($params);
        return hash_equals($expectedHash, $receivedHash);
    }

    public function createOrder(Order $order, array $options = []): array
    {
        try {
            $merchantKey = $this->credentials['merchant_key'] ?? '';
            $txnid = $order->order_number;
            $amount = $order->total_amount;
            $productinfo = 'Order #' . $order->order_number;
            $firstname = $order->user->name ?? 'Customer';
            $email = $order->user->email ?? '';
            $phone = $order->user->phone ?? '';

            $params = [
                'key' => $merchantKey,
                'txnid' => $txnid,
                'amount' => $amount,
                'productinfo' => $productinfo,
                'firstname' => $firstname,
                'email' => $email,
                'phone' => $phone,
                'surl' => $options['return_url'] ?? url('/payment/success'),
                'furl' => $options['cancel_url'] ?? url('/payment/failure'),
            ];

            $hash = $this->generateHash($params);
            $params['hash'] = $hash;

            // PayU uses form POST, so we return the form data
            return [
                'payment_id' => $txnid,
                'redirect_url' => $this->getBaseUrl() . '/_payment',
                'form_data' => $params, // For frontend to submit
                'status' => 'created',
                'metadata' => $params,
            ];

        } catch (\Exception $e) {
            Log::error('PayU createOrder failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function verifyWebhook(Request $request): ?array
    {
        try {
            // PayU sends callback via POST
            $params = $request->all();
            
            if (!$this->verifyHash($params)) {
                Log::warning('PayU webhook hash mismatch');
                return null;
            }

            $status = strtolower($params['status'] ?? '');
            $txnid = $params['txnid'] ?? '';

            return [
                'payment_id' => $txnid,
                'order_id' => $txnid,
                'status' => $this->mapStatus($status),
                'amount' => $params['amount'] ?? 0,
                'metadata' => $params,
            ];

        } catch (\Exception $e) {
            Log::error('PayU webhook verification failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function mapStatus(string $status): string
    {
        return match($status) {
            'success' => 'paid',
            'failure', 'pending' => 'failed',
            default => 'pending',
        };
    }

    public function capture(string $paymentId, ?float $amount = null): array
    {
        // PayU doesn't require manual capture
        return [
            'status' => 'captured',
            'transaction_id' => $paymentId,
            'metadata' => [],
        ];
    }

    public function refund(string $paymentId, ?float $amount = null, string $reason = ''): array
    {
        try {
            $merchantKey = $this->credentials['merchant_key'] ?? '';
            $merchantSalt = $this->credentials['merchant_salt'] ?? '';
            
            $params = [
                'key' => $merchantKey,
                'command' => 'cancel_refund_transaction',
                'var1' => $paymentId,
                'var2' => $amount ?? 'full',
            ];

            $hashString = $merchantKey . '|' . $params['command'] . '|' . $params['var1'] . '|' . $params['var2'] . '|' . $merchantSalt;
            $hash = strtolower(hash('sha512', $hashString));
            $params['hash'] = $hash;

            $response = Http::asForm()
                ->post($this->getBaseUrl() . '/merchant/postservice?form=2', $params);

            if ($response->successful()) {
                $data = $response->body();
                return [
                    'refund_id' => $paymentId,
                    'status' => strpos($data, 'SUCCESS') !== false ? 'processed' : 'failed',
                    'amount' => $amount,
                    'metadata' => ['response' => $data],
                ];
            }

            throw new \Exception('Failed to refund: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('PayU refund failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function fetchStatus(string $paymentId): array
    {
        try {
            $merchantKey = $this->credentials['merchant_key'] ?? '';
            $merchantSalt = $this->credentials['merchant_salt'] ?? '';
            
            $hashString = $merchantKey . '|verify_payment|' . $paymentId . '|' . $merchantSalt;
            $hash = strtolower(hash('sha512', $hashString));

            $response = Http::asForm()
                ->post($this->getBaseUrl() . '/merchant/postservice?form=2', [
                    'key' => $merchantKey,
                    'command' => 'verify_payment',
                    'var1' => $paymentId,
                    'hash' => $hash,
                ]);

            if ($response->successful()) {
                $data = $response->body();
                // Parse response (PayU returns pipe-separated values)
                $parts = explode('|', $data);
                $status = $parts[0] ?? 'unknown';
                
                return [
                    'status' => $this->mapStatus($status),
                    'amount' => 0, // Amount not in response
                    'currency' => 'INR',
                    'metadata' => ['response' => $data],
                ];
            }

            throw new \Exception('Failed to fetch status: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('PayU fetchStatus failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

