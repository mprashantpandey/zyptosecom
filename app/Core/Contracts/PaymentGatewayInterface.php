<?php

namespace App\Core\Contracts;

use App\Models\Order;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Create a payment order/transaction
     * 
     * @param Order $order
     * @param array $options Additional options (return_url, etc.)
     * @return array ['payment_id', 'redirect_url', 'status', 'metadata']
     */
    public function createOrder(Order $order, array $options = []): array;

    /**
     * Verify webhook signature and extract payment data
     * 
     * @param Request $request
     * @return array|null Payment data if valid, null if invalid
     */
    public function verifyWebhook(Request $request): ?array;

    /**
     * Capture/confirm a payment
     * 
     * @param string $paymentId
     * @param float $amount Optional amount to capture (for partial)
     * @return array ['status', 'transaction_id', 'metadata']
     */
    public function capture(string $paymentId, ?float $amount = null): array;

    /**
     * Refund a payment
     * 
     * @param string $paymentId
     * @param float $amount Refund amount (null for full refund)
     * @param string $reason Refund reason
     * @return array ['refund_id', 'status', 'amount', 'metadata']
     */
    public function refund(string $paymentId, ?float $amount = null, string $reason = ''): array;

    /**
     * Fetch payment status
     * 
     * @param string $paymentId
     * @return array ['status', 'amount', 'currency', 'metadata']
     */
    public function fetchStatus(string $paymentId): array;
}
