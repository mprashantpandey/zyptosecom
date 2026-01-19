<?php

namespace App\Http\Controllers\Api\v1;

use App\Core\Providers\Payment\RazorpayProvider;
use App\Core\Services\SecretsService;
use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected SecretsService $secrets;

    public function __construct(SecretsService $secrets)
    {
        $this->secrets = $secrets;
    }

    /**
     * Handle webhook from payment/shipping providers
     * 
     * POST /api/v1/webhooks/{provider}
     */
    public function handle(Request $request, string $provider): JsonResponse
    {
        try {
            // Log webhook attempt
            $log = $this->logWebhook($provider, $request);

            // Route to provider-specific handler
            $result = match($provider) {
                'razorpay' => $this->handleRazorpay($request),
                'stripe' => $this->handleStripe($request),
                'cashfree' => $this->handleCashfree($request),
                'phonepe' => $this->handlePhonePe($request),
                'payu' => $this->handlePayU($request),
                'shiprocket' => $this->handleShiprocket($request),
                default => ['success' => false, 'message' => 'Unknown provider'],
            };

            // Update log
            $log->update([
                'status' => $result['success'] ?? false ? 'processed' : 'failed',
                'error_message' => $result['message'] ?? null,
            ]);

            if ($result['success'] ?? false) {
                return response()->json(['success' => true], 200);
            }

            return response()->json(['success' => false, 'message' => $result['message'] ?? 'Processing failed'], 400);

        } catch (\Exception $e) {
            Log::error('Webhook handling failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Internal error'], 500);
        }
    }

    protected function handleRazorpay(Request $request): array
    {
        $secrets = app(SecretsService::class);
        $provider = new RazorpayProvider($secrets, 'production'); // Use production for webhooks
        
        $paymentData = $provider->verifyWebhook($request);
        
        if (!$paymentData) {
            return ['success' => false, 'message' => 'Invalid signature'];
        }

        // Find order by payment_id or order_id
        $order = Order::where('payment_transaction_id', $paymentData['payment_id'])
            ->orWhere('order_number', $paymentData['order_id'])
            ->first();

        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        // Update order payment status
        DB::transaction(function () use ($order, $paymentData) {
            $order->payment_status = match($paymentData['status']) {
                'authorized', 'captured' => 'paid',
                'failed' => 'failed',
                'refunded' => 'refunded',
                default => 'pending',
            };
            $order->payment_transaction_id = $paymentData['payment_id'];
            $order->save();
        });

        return ['success' => true, 'message' => 'Payment status updated'];
    }

    protected function handleStripe(Request $request): array
    {
        $secrets = app(SecretsService::class);
        $provider = new \App\Core\Providers\Payment\StripeProvider($secrets, 'production');
        
        $paymentData = $provider->verifyWebhook($request);
        
        if (!$paymentData) {
            return ['success' => false, 'message' => 'Invalid signature'];
        }

        // Find order
        $order = Order::where('order_number', $paymentData['order_id'])
            ->orWhere('id', $paymentData['order_id'])
            ->first();

        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        // Update order payment status
        DB::transaction(function () use ($order, $paymentData) {
            $order->payment_status = match($paymentData['status']) {
                'paid' => 'paid',
                'failed' => 'failed',
                'refunded' => 'refunded',
                default => 'pending',
            };
            $order->payment_transaction_id = $paymentData['payment_id'];
            $order->save();
        });

        return ['success' => true, 'message' => 'Payment status updated'];
    }

    protected function handleCashfree(Request $request): array
    {
        $secrets = app(SecretsService::class);
        $provider = new \App\Core\Providers\Payment\CashfreeProvider($secrets, 'production');
        
        $paymentData = $provider->verifyWebhook($request);
        
        if (!$paymentData) {
            return ['success' => false, 'message' => 'Invalid signature'];
        }

        $order = Order::where('order_number', $paymentData['order_id'])->first();

        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        DB::transaction(function () use ($order, $paymentData) {
            $order->payment_status = match($paymentData['status']) {
                'paid' => 'paid',
                'failed' => 'failed',
                'refunded' => 'refunded',
                default => 'pending',
            };
            $order->payment_transaction_id = $paymentData['payment_id'];
            $order->save();
        });

        return ['success' => true, 'message' => 'Payment status updated'];
    }

    protected function handlePhonePe(Request $request): array
    {
        $secrets = app(SecretsService::class);
        $provider = new \App\Core\Providers\Payment\PhonePeProvider($secrets, 'production');
        
        $paymentData = $provider->verifyWebhook($request);
        
        if (!$paymentData) {
            return ['success' => false, 'message' => 'Invalid checksum'];
        }

        $order = Order::where('order_number', $paymentData['order_id'])->first();

        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        DB::transaction(function () use ($order, $paymentData) {
            $order->payment_status = match($paymentData['status']) {
                'paid' => 'paid',
                'failed' => 'failed',
                'refunded' => 'refunded',
                default => 'pending',
            };
            $order->payment_transaction_id = $paymentData['payment_id'];
            $order->save();
        });

        return ['success' => true, 'message' => 'Payment status updated'];
    }

    protected function handlePayU(Request $request): array
    {
        $secrets = app(SecretsService::class);
        $provider = new \App\Core\Providers\Payment\PayUProvider($secrets, 'production');
        
        $paymentData = $provider->verifyWebhook($request);
        
        if (!$paymentData) {
            return ['success' => false, 'message' => 'Invalid hash'];
        }

        $order = Order::where('order_number', $paymentData['order_id'])->first();

        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        DB::transaction(function () use ($order, $paymentData) {
            $order->payment_status = match($paymentData['status']) {
                'paid' => 'paid',
                'failed' => 'failed',
                default => 'pending',
            };
            $order->payment_transaction_id = $paymentData['payment_id'];
            $order->save();
        });

        return ['success' => true, 'message' => 'Payment status updated'];
    }

    protected function handleShiprocket(Request $request): array
    {
        // TODO: Implement Shiprocket webhook
        return ['success' => false, 'message' => 'Not implemented'];
    }

    protected function logWebhook(string $provider, Request $request): NotificationLog
    {
        return NotificationLog::create([
            'channel' => 'webhook',
            'provider_key' => $provider,
            'event_key' => 'webhook_received',
            'recipient' => $request->ip(),
            'payload' => [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
            ],
            'status' => 'queued',
            'created_by' => null,
        ]);
    }
}
