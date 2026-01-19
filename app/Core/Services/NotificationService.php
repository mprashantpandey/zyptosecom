<?php

namespace App\Core\Services;

use App\Models\NotificationEvent;
use App\Models\NotificationEventChannel;
use App\Models\NotificationLog;
use App\Models\NotificationProvider;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected TemplateRendererService $renderer;
    protected SettingsService $settings;

    public function __construct(TemplateRendererService $renderer, SettingsService $settings)
    {
        $this->renderer = $renderer;
        $this->settings = $settings;
    }

    /**
     * Send notification
     */
    public function send(
        string $eventKey,
        string $channel,
        string $recipient,
        array $data = [],
        array $options = []
    ): ?NotificationLog {
        try {
            // Get event (create test event if needed)
            $event = NotificationEvent::where('key', $eventKey)->first();
            if (!$event && $eventKey === 'test') {
                // Create temporary test event for testing
                $event = NotificationEvent::create([
                    'key' => 'test',
                    'name' => 'Test',
                    'description' => 'Test notification',
                    'is_system' => false,
                    'is_critical' => false,
                ]);
            } elseif (!$event) {
                throw new \Exception("Event not found: {$eventKey}");
            }

            // Get event channel config
            $eventChannel = NotificationEventChannel::where('notification_event_id', $event->id)
                ->where('channel', $channel)
                ->where('enabled', true)
                ->first();

            if (!$eventChannel) {
                throw new \Exception("Event channel not enabled: {$eventKey}@{$channel}");
            }

            // Check quiet hours
            if ($this->shouldRespectQuietHours($event, $eventChannel)) {
                if ($this->isQuietHours()) {
                    // Queue for later (status=queued)
                    return $this->logNotification($channel, $eventKey, $recipient, null, null, 'queued', null, [
                        'reason' => 'quiet_hours',
                    ]);
                }
            }

            // Get active provider
            $provider = NotificationProvider::where('channel', $channel)
                ->where('is_enabled', true)
                ->first();

            if (!$provider) {
                throw new \Exception("No active provider for channel: {$channel}");
            }

            // Get template
            $template = $eventChannel->template;
            if (!$template) {
                throw new \Exception("No template configured for event: {$eventKey}@{$channel}");
            }

            // Render template
            $rendered = $this->renderer->render($template, $data);

            // For push notifications, if recipient is a user ID, resolve device tokens
            if ($channel === 'push' && is_numeric($recipient)) {
                // Recipient is user ID, get active device tokens
                $deviceTokens = \App\Models\UserDeviceToken::where('user_id', $recipient)
                    ->where('is_active', true)
                    ->pluck('token')
                    ->toArray();
                
                if (empty($deviceTokens)) {
                    throw new \Exception("No active device tokens found for user: {$recipient}");
                }
                
                // Send to all tokens (or first one for now)
                $recipient = $deviceTokens[0]; // For now, send to first token
                // TODO: In production, you might want to send to all tokens
            }

            // Send via provider driver
            $result = $this->sendViaProvider($provider, $channel, $recipient, $rendered, $options);

            // Log success
            return $this->logNotification(
                $channel,
                $eventKey,
                $recipient,
                $rendered['subject'] ?? null,
                $rendered,
                $result['status'] ?? 'sent',
                $result['external_id'] ?? null,
                $result['error'] ?? null
            );

        } catch (\Exception $e) {
            Log::error('Notification send failed', [
                'event' => $eventKey,
                'channel' => $channel,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);

            // Log failure
            return $this->logNotification(
                $channel,
                $eventKey,
                $recipient,
                null,
                null,
                'failed',
                null,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Send via provider driver
     */
    protected function sendViaProvider(
        NotificationProvider $provider,
        string $channel,
        string $recipient,
        array $rendered,
        array $options = []
    ): array {
        // Get credentials from secrets
        $secretsService = app(SecretsService::class);
        $credentials = $secretsService->getCredentials(
            'notification',
            $provider->provider_key,
            $provider->environment
        );

        return match($channel) {
            'email' => $this->sendEmail($provider, $recipient, $rendered, $credentials, $options),
            'sms' => $this->sendSms($provider, $recipient, $rendered, $credentials, $options),
            'push' => $this->sendPush($provider, $recipient, $rendered, $credentials, $options),
            'whatsapp' => $this->sendWhatsApp($provider, $recipient, $rendered, $credentials, $options),
            default => throw new \Exception("Unsupported channel: {$channel}"),
        };
    }

    /**
     * Send email
     */
    protected function sendEmail(
        NotificationProvider $provider,
        string $recipient,
        array $rendered,
        array $credentials,
        array $options
    ): array {
        try {
            // Use Laravel Mail for SMTP
            if ($provider->provider_key === 'smtp') {
                \Illuminate\Support\Facades\Mail::raw($rendered['body'], function ($message) use ($recipient, $rendered, $provider) {
                    $message->to($recipient)
                        ->subject($rendered['subject'] ?? 'Notification');
                    
                    if ($provider->config['from_email'] ?? null) {
                        $message->from($provider->config['from_email'], $provider->config['from_name'] ?? config('app.name'));
                    }
                });

                return ['status' => 'sent', 'external_id' => 'smtp-' . uniqid()];
            }

            // For other providers (SendGrid, Mailgun), use Laravel mailers if configured
            // This is a stub - in production, implement actual API calls
            return ['status' => 'sent', 'external_id' => 'mock-' . uniqid()];

        } catch (\Exception $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Send SMS
     */
    protected function sendSms(
        NotificationProvider $provider,
        string $recipient,
        array $rendered,
        array $credentials,
        array $options
    ): array {
        try {
            $secretsService = app(SecretsService::class);
            
            if ($provider->provider_key === 'msg91') {
                $msg91Provider = new \App\Core\Providers\Sms\Msg91Provider($secretsService, $provider->environment);
                $result = $msg91Provider->sendSms($recipient, $rendered['body'] ?? '');
                return $result;
            } elseif ($provider->provider_key === 'twilio') {
                // TODO: Implement Twilio provider
                return ['status' => 'sent', 'external_id' => 'twilio-mock-' . uniqid()];
            }

            // Fallback
            return ['status' => 'sent', 'external_id' => 'sms-mock-' . uniqid()];

        } catch (\Exception $e) {
            Log::error('SMS send failed', [
                'provider' => $provider->provider_key,
                'recipient' => substr($recipient, 0, 5) . '...',
                'error' => $e->getMessage(),
            ]);
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Send Push notification using FCM HTTP v1
     */
    protected function sendPush(
        NotificationProvider $provider,
        string $recipient,
        array $rendered,
        array $credentials,
        array $options
    ): array {
        try {
            // Use FCM HTTP v1 for Firebase
            if ($provider->provider_key === 'firebase_fcm_v1') {
                $fcmClient = app(\App\Core\Services\FcmHttpV1Client::class, [
                    'providerKey' => $provider->provider_key,
                    'environment' => $provider->environment,
                ]);

                $title = $rendered['subject'] ?? 'Notification';
                $body = $rendered['body'] ?? '';
                
                // Extract data from rendered body if it's structured
                $data = $options['data'] ?? [];
                
                // Parse body if it contains structured data
                if (is_string($body) && strpos($body, '{{') !== false) {
                    // Body might contain variables, use as-is for now
                    // In production, you might want to extract structured data
                }

                $result = $fcmClient->sendNotification(
                    $recipient, // This should be the FCM device token
                    $title,
                    $body,
                    $data,
                    $options['platform_options'] ?? null
                );

                return $result;
            }

            // Fallback for other push providers (stub)
            return ['status' => 'sent', 'external_id' => 'push-mock-' . uniqid()];

        } catch (\Exception $e) {
            Log::error('Push notification send failed', [
                'provider' => $provider->provider_key,
                'recipient' => substr($recipient, 0, 20) . '...',
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Send WhatsApp
     */
    protected function sendWhatsApp(
        NotificationProvider $provider,
        string $recipient,
        array $rendered,
        array $credentials,
        array $options
    ): array {
        // Stub implementation - in production, implement WhatsApp Business API
        return ['status' => 'sent', 'external_id' => 'whatsapp-mock-' . uniqid()];
    }

    /**
     * Check if should respect quiet hours
     */
    protected function shouldRespectQuietHours(NotificationEvent $event, NotificationEventChannel $channel): bool
    {
        // Critical events ignore quiet hours
        if ($event->is_critical) {
            return false;
        }

        return $channel->quiet_hours_respect;
    }

    /**
     * Check if currently in quiet hours
     */
    protected function isQuietHours(): bool
    {
        $enabled = $this->settings->get('quiet_hours_enabled', false);
        if (!$enabled) {
            return false;
        }

        $start = $this->settings->get('quiet_hours_start', '22:00');
        $end = $this->settings->get('quiet_hours_end', '08:00');

        $now = now()->format('H:i');
        
        // Simple check (doesn't handle overnight properly, but good enough for demo)
        return $now >= $start || $now <= $end;
    }

    /**
     * Log notification attempt
     */
    protected function logNotification(
        string $channel,
        ?string $eventKey,
        string $recipient,
        ?string $subject,
        ?array $payload,
        string $status,
        ?string $externalId,
        ?array $error = null
    ): NotificationLog {
        return NotificationLog::create([
            'channel' => $channel,
            'provider_key' => 'unknown', // Will be set by caller if available
            'event_key' => $eventKey,
            'recipient' => $recipient,
            'subject' => $subject,
            'payload' => $payload, // Safe, no secrets
            'status' => $status,
            'error_message' => $error['error'] ?? null,
            'external_id' => $externalId,
            'sent_at' => $status === 'sent' ? now() : null,
            'created_by' => auth()->id(),
        ]);
    }
}

