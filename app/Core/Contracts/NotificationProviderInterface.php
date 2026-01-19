<?php

namespace App\Core\Contracts;

interface NotificationProviderInterface
{
    /**
     * Send push notification
     */
    public function sendPush(string $token, array $payload): array;

    /**
     * Send SMS
     */
    public function sendSms(string $phone, string $message, ?array $options = []): array;

    /**
     * Send email
     */
    public function sendEmail(string $to, string $subject, string $body, ?array $options = []): array;

    /**
     * Send WhatsApp message
     */
    public function sendWhatsApp(string $phone, string $message, ?array $options = []): array;
}

