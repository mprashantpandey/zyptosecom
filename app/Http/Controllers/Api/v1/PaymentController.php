<?php

namespace App\Http\Controllers\Api\v1;

use App\Core\Contracts\PaymentGatewayInterface;
use App\Core\Services\SecretsService;
use App\Core\Services\SettingsService;
use App\Core\Settings\SettingKeys;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected SettingsService $settings;
    protected SecretsService $secrets;

    public function __construct(SettingsService $settings, SecretsService $secrets)
    {
        $this->settings = $settings;
        $this->secrets = $secrets;
    }

    /**
     * Create payment session
     * POST /api/v1/payments/create
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'provider_key' => 'nullable|string',
        ]);

        $order = Order::findOrFail($request->order_id);

        // Validate order
        if ($order->payment_status === 'paid') {
            return response()->json([
                'status' => 'error',
                'message' => 'Order is already paid',
            ], 400);
        }

        // Get provider
        $providerKey = $request->provider_key ?? $this->getDefaultProvider();
        if (!$providerKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'No payment provider configured',
            ], 400);
        }

        try {
            $provider = $this->getProvider($providerKey);
            if (!$provider) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Payment provider '{$providerKey}' is not configured or enabled",
                ], 400);
            }

            $result = $provider->createOrder($order, [
                'return_url' => $request->input('return_url'),
            ]);

            // Log payment attempt
            DB::table('payment_logs')->insert([
                'order_id' => $order->id,
                'provider' => $providerKey,
                'payment_id' => $result['payment_id'] ?? null,
                'status' => $result['status'] ?? 'pending',
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'metadata' => json_encode($result['metadata'] ?? []),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Return provider-specific response
            return response()->json([
                'status' => 'success',
                'message' => 'Payment session created',
                'data' => $this->formatProviderResponse($providerKey, $result, $order),
            ]);

        } catch (\Exception $e) {
            Log::error('Payment creation failed', [
                'order_id' => $order->id,
                'provider' => $providerKey,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create payment session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify payment (for callback/redirect flows)
     * POST /api/v1/payments/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'provider_key' => 'required|string',
            'payment_id' => 'required|string',
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::findOrFail($request->order_id);
        $providerKey = $request->provider_key;

        try {
            $provider = $this->getProvider($providerKey);
            if (!$provider) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Payment provider '{$providerKey}' is not configured",
                ], 400);
            }

            // Verify payment status
            $status = $provider->fetchStatus($request->payment_id);

            if ($status['status'] === 'paid' || $status['status'] === 'captured') {
                // Update order
                $order->update([
                    'payment_status' => 'paid',
                    'payment_method' => $providerKey,
                    'metadata' => array_merge($order->metadata ?? [], [
                        'payment_id' => $request->payment_id,
                        'transaction_id' => $status['transaction_id'] ?? $request->payment_id,
                        'paid_at' => now()->toIso8601String(),
                    ]),
                ]);

                // Log payment
                DB::table('payment_logs')->where('order_id', $order->id)
                    ->where('payment_id', $request->payment_id)
                    ->update([
                        'status' => 'paid',
                        'transaction_id' => $status['transaction_id'] ?? $request->payment_id,
                        'updated_at' => now(),
                    ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment verified successfully',
                    'data' => [
                        'order_id' => $order->id,
                        'payment_status' => 'paid',
                        'provider' => $providerKey,
                        'transaction_id' => $status['transaction_id'] ?? $request->payment_id,
                    ],
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Payment verification failed',
                'data' => [
                    'order_id' => $order->id,
                    'payment_status' => $status['status'] ?? 'failed',
                    'provider' => $providerKey,
                ],
            ], 400);

        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'order_id' => $order->id,
                'provider' => $providerKey,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get default enabled payment provider
     */
    protected function getDefaultProvider(): ?string
    {
        $providers = ['razorpay', 'stripe', 'cashfree', 'phonepe', 'payu'];
        
        foreach ($providers as $provider) {
            $enabledKey = strtoupper($provider) . '_ENABLED';
            if ($this->settings->get($enabledKey, false)) {
                return $provider;
            }
        }
        
        return null;
    }

    /**
     * Get payment provider instance
     */
    protected function getProvider(string $providerKey): ?PaymentGatewayInterface
    {
        $enabledKey = strtoupper($providerKey) . '_ENABLED';
        if (!$this->settings->get($enabledKey, false)) {
            return null;
        }

        $envKey = strtoupper($providerKey) . '_ENVIRONMENT';
        $environment = $this->settings->get($envKey, 'sandbox');

        $providerClass = match($providerKey) {
            'razorpay' => \App\Core\Providers\Payment\RazorpayProvider::class,
            'stripe' => \App\Core\Providers\Payment\StripeProvider::class,
            'cashfree' => \App\Core\Providers\Payment\CashfreeProvider::class,
            'phonepe' => \App\Core\Providers\Payment\PhonePeProvider::class,
            'payu' => \App\Core\Providers\Payment\PayUProvider::class,
            default => null,
        };

        if (!$providerClass || !class_exists($providerClass)) {
            return null;
        }

        return new $providerClass($this->secrets, $environment);
    }

    /**
     * Format provider-specific response for client
     */
    protected function formatProviderResponse(string $providerKey, array $result, Order $order): array
    {
        $base = [
            'order_id' => $order->id,
            'amount' => $order->total_amount,
            'currency' => $order->currency ?? 'INR',
            'provider' => $providerKey,
            'payment_id' => $result['payment_id'] ?? null,
        ];

        return match($providerKey) {
            'razorpay' => array_merge($base, [
                'key_id' => $this->secrets->getCredentials('payment', 'razorpay', $this->settings->get(SettingKeys::RAZORPAY_ENVIRONMENT, 'sandbox'))['key_id'] ?? '',
                'order_id' => $result['payment_id'] ?? null,
            ]),
            'stripe' => array_merge($base, [
                'client_secret' => $result['metadata']['client_secret'] ?? null,
            ]),
            'cashfree' => array_merge($base, [
                'payment_session_id' => $result['payment_id'] ?? null,
                'order_token' => $result['metadata']['order_token'] ?? null,
            ]),
            'phonepe' => array_merge($base, [
                'redirect_url' => $result['redirect_url'] ?? null,
                'intent' => $result['metadata']['intent'] ?? null,
            ]),
            'payu' => array_merge($base, [
                'form_fields' => $result['metadata']['form_fields'] ?? [],
                'hash' => $result['metadata']['hash'] ?? null,
            ]),
            default => $base,
        };
    }
}
