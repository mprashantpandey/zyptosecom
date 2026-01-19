<?php

namespace App\Core\Contracts;

interface PaymentProviderInterface
{
    /**
     * Initialize a payment transaction
     */
    public function initiate(array $paymentData): array;

    /**
     * Verify payment status
     */
    public function verify(string $transactionId, array $data = []): array;

    /**
     * Process refund
     */
    public function refund(string $transactionId, float $amount, ?string $reason = null): array;

    /**
     * Get payment status
     */
    public function getStatus(string $transactionId): array;

    /**
     * Handle webhook
     */
    public function handleWebhook(array $payload): array;
}

