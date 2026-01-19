<?php

namespace App\Core\Services;

class SampleDataFactory
{
    /**
     * Get sample data by type
     */
    public function getSample(string $type): array
    {
        return match($type) {
            'order' => $this->sampleOrder(),
            'otp' => $this->sampleOtp(),
            'wallet' => $this->sampleWalletAdjustment(),
            'refund' => $this->sampleRefund(),
            default => $this->sampleOrder(),
        };
    }

    /**
     * Sample order data
     */
    public function sampleOrder(): array
    {
        return [
            'order_id' => 'ORD-12345',
            'order_number' => 'ORD-12345',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'total_amount' => '₹1,250.00',
            'items_count' => '3',
            'delivery_address' => '123 Main St, City, State - 123456',
            'tracking_number' => 'TRACK123456',
            'estimated_delivery' => now()->addDays(3)->format('M d, Y'),
            'payment_method' => 'Razorpay',
            'order_date' => now()->format('M d, Y H:i'),
        ];
    }

    /**
     * Sample OTP data
     */
    public function sampleOtp(): array
    {
        return [
            'otp' => '123456',
            'customer_name' => 'John Doe',
            'expires_in' => '5 minutes',
        ];
    }

    /**
     * Sample wallet adjustment data
     */
    public function sampleWalletAdjustment(): array
    {
        return [
            'customer_name' => 'John Doe',
            'amount' => '₹500.00',
            'balance_before' => '₹1,000.00',
            'balance_after' => '₹1,500.00',
            'transaction_type' => 'Credit',
            'reason' => 'Refund for order #ORD-12345',
            'transaction_date' => now()->format('M d, Y H:i'),
        ];
    }

    /**
     * Sample refund data
     */
    public function sampleRefund(): array
    {
        return [
            'refund_id' => 'REF-12345',
            'order_id' => 'ORD-12345',
            'order_number' => 'ORD-12345',
            'customer_name' => 'John Doe',
            'refund_amount' => '₹1,250.00',
            'refund_method' => 'Original Payment Method',
            'refund_date' => now()->format('M d, Y H:i'),
            'reason' => 'Customer requested cancellation',
        ];
    }
}

