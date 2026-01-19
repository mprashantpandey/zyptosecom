<?php

namespace Database\Seeders;

use App\Models\NotificationEvent;
use Illuminate\Database\Seeder;

class NotificationEventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            ['key' => 'otp', 'name' => 'OTP', 'description' => 'One-time password for login/verification', 'is_system' => true, 'is_critical' => true],
            ['key' => 'welcome', 'name' => 'Welcome', 'description' => 'Welcome message for new users', 'is_system' => true, 'is_critical' => false],
            ['key' => 'order_placed', 'name' => 'Order Placed', 'description' => 'Notification when order is placed', 'is_system' => true, 'is_critical' => false],
            ['key' => 'order_confirmed', 'name' => 'Order Confirmed', 'description' => 'Notification when order is confirmed', 'is_system' => true, 'is_critical' => false],
            ['key' => 'order_shipped', 'name' => 'Order Shipped', 'description' => 'Notification when order is shipped', 'is_system' => true, 'is_critical' => false],
            ['key' => 'out_for_delivery', 'name' => 'Out for Delivery', 'description' => 'Notification when order is out for delivery', 'is_system' => true, 'is_critical' => false],
            ['key' => 'order_delivered', 'name' => 'Order Delivered', 'description' => 'Notification when order is delivered', 'is_system' => true, 'is_critical' => false],
            ['key' => 'order_cancelled', 'name' => 'Order Cancelled', 'description' => 'Notification when order is cancelled', 'is_system' => true, 'is_critical' => false],
            ['key' => 'refund_initiated', 'name' => 'Refund Initiated', 'description' => 'Notification when refund is initiated', 'is_system' => true, 'is_critical' => false],
            ['key' => 'refund_completed', 'name' => 'Refund Completed', 'description' => 'Notification when refund is completed', 'is_system' => true, 'is_critical' => false],
            ['key' => 'wallet_adjusted', 'name' => 'Wallet Adjusted', 'description' => 'Notification when wallet balance is adjusted', 'is_system' => true, 'is_critical' => false],
            ['key' => 'low_stock_alert', 'name' => 'Low Stock Alert', 'description' => 'Admin alert for low stock', 'is_system' => true, 'is_critical' => false],
            ['key' => 'payment_webhook_failed', 'name' => 'Payment Webhook Failed', 'description' => 'Admin alert for payment webhook failures', 'is_system' => true, 'is_critical' => false],
            ['key' => 'shipping_api_failed', 'name' => 'Shipping API Failed', 'description' => 'Admin alert for shipping API failures', 'is_system' => true, 'is_critical' => false],
        ];

        foreach ($events as $event) {
            NotificationEvent::updateOrCreate(
                ['key' => $event['key']],
                $event
            );
        }
    }
}
