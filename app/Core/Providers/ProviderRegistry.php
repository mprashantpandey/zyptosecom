<?php

namespace App\Core\Providers;

class ProviderRegistry
{
    /**
     * Get all registered providers
     */
    public static function all(): array
    {
        return array_merge(
            self::paymentProviders(),
            self::shippingProviders(),
            self::emailProviders(),
            self::smsProviders(),
            self::whatsappProviders(),
            self::pushProviders(),
            self::authProviders(),
            self::storageProviders()
        );
    }

    /**
     * Get provider by key
     */
    public static function get(string $providerKey): ?array
    {
        foreach (self::all() as $provider) {
            if ($provider['key'] === $providerKey) {
                return $provider;
            }
        }
        return null;
    }

    /**
     * Get providers by category
     */
    public static function getByCategory(string $category): array
    {
        return array_filter(self::all(), fn($p) => $p['category'] === $category);
    }

    /**
     * Payment providers
     */
    protected static function paymentProviders(): array
    {
        return [
            [
                'key' => 'razorpay',
                'category' => 'payment',
                'display_name' => 'Razorpay',
                'description' => 'Razorpay payment gateway for India',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'key_id',
                        'label' => 'Key ID',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Your Razorpay Key ID from dashboard',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'key_secret',
                        'label' => 'Key Secret',
                        'type' => 'password',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'Your Razorpay Key Secret (keep secure)',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'webhook_secret',
                        'label' => 'Webhook Secret',
                        'type' => 'password',
                        'required' => false,
                        'is_secret' => true,
                        'help_text' => 'Webhook secret for verifying webhook signatures',
                        'validation' => 'nullable|string|max:255',
                    ],
                ],
                'test_action' => [
                    'type' => 'create_test_order',
                    'inputs' => ['amount' => 'number'],
                ],
            ],
            [
                'key' => 'stripe',
                'category' => 'payment',
                'display_name' => 'Stripe',
                'description' => 'Stripe payment gateway (international)',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'publishable_key',
                        'label' => 'Publishable Key',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Stripe publishable key (starts with pk_)',
                        'validation' => 'required|string|starts_with:pk_',
                    ],
                    [
                        'key' => 'secret_key',
                        'label' => 'Secret Key',
                        'type' => 'password',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'Stripe secret key (starts with sk_)',
                        'validation' => 'required|string|starts_with:sk_',
                    ],
                    [
                        'key' => 'webhook_secret',
                        'label' => 'Webhook Secret',
                        'type' => 'password',
                        'required' => false,
                        'is_secret' => true,
                        'help_text' => 'Webhook signing secret (whsec_...)',
                        'validation' => 'nullable|string|starts_with:whsec_',
                    ],
                ],
                'test_action' => [
                    'type' => 'create_test_payment_intent',
                    'inputs' => ['amount' => 'number', 'currency' => 'text'],
                ],
            ],
            [
                'key' => 'cashfree',
                'category' => 'payment',
                'display_name' => 'Cashfree',
                'description' => 'Cashfree payment gateway for India',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'app_id',
                        'label' => 'App ID',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Cashfree App ID',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'secret_key',
                        'label' => 'Secret Key',
                        'type' => 'password',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'Cashfree Secret Key',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'webhook_secret',
                        'label' => 'Webhook Secret',
                        'type' => 'password',
                        'required' => false,
                        'is_secret' => true,
                        'help_text' => 'Webhook secret for verification',
                        'validation' => 'nullable|string|max:255',
                    ],
                ],
                'test_action' => [
                    'type' => 'create_test_payment',
                    'inputs' => ['amount' => 'number'],
                ],
            ],
            [
                'key' => 'phonepe',
                'category' => 'payment',
                'display_name' => 'PhonePe',
                'description' => 'PhonePe payment gateway',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'merchant_id',
                        'label' => 'Merchant ID',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'PhonePe Merchant ID',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'salt_key',
                        'label' => 'Salt Key',
                        'type' => 'password',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'PhonePe Salt Key (keep secure)',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'salt_index',
                        'label' => 'Salt Index',
                        'type' => 'number',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Salt Index (usually 1)',
                        'validation' => 'required|integer|min:1|max:10',
                    ],
                ],
                'test_action' => [
                    'type' => 'create_test_payment',
                    'inputs' => ['amount' => 'number'],
                ],
            ],
            [
                'key' => 'payu',
                'category' => 'payment',
                'display_name' => 'PayU',
                'description' => 'PayU payment gateway',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'merchant_key',
                        'label' => 'Merchant Key',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'PayU Merchant Key',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'merchant_salt',
                        'label' => 'Merchant Salt',
                        'type' => 'password',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'PayU Merchant Salt',
                        'validation' => 'required|string|max:255',
                    ],
                ],
                'test_action' => [
                    'type' => 'create_test_payment',
                    'inputs' => ['amount' => 'number'],
                ],
            ],
        ];
    }

    /**
     * Shipping providers
     */
    protected static function shippingProviders(): array
    {
        return [
            [
                'key' => 'shiprocket',
                'category' => 'shipping',
                'display_name' => 'Shiprocket',
                'description' => 'Shiprocket shipping provider for India',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'email',
                        'label' => 'Email',
                        'type' => 'email',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Shiprocket account email',
                        'validation' => 'required|email|max:255',
                    ],
                    [
                        'key' => 'password',
                        'label' => 'Password',
                        'type' => 'password',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'Shiprocket account password',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'pickup_location_name',
                        'label' => 'Default Pickup Location Name',
                        'type' => 'text',
                        'required' => false,
                        'is_secret' => false,
                        'help_text' => 'Name of your default pickup location',
                        'validation' => 'nullable|string|max:255',
                    ],
                ],
                'test_action' => [
                    'type' => 'test_authentication',
                    'inputs' => [],
                ],
            ],
        ];
    }

    /**
     * Email providers
     */
    protected static function emailProviders(): array
    {
        return [
            [
                'key' => 'smtp',
                'category' => 'email',
                'display_name' => 'SMTP',
                'description' => 'SMTP email server',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'host',
                        'label' => 'SMTP Host',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'SMTP server hostname (e.g., smtp.gmail.com)',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'port',
                        'label' => 'Port',
                        'type' => 'number',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'SMTP port (587 for TLS, 465 for SSL)',
                        'validation' => 'required|integer|min:1|max:65535',
                    ],
                    [
                        'key' => 'username',
                        'label' => 'Username',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'SMTP username (usually your email)',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'password',
                        'label' => 'Password',
                        'type' => 'password',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'SMTP password or app password',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'encryption',
                        'label' => 'Encryption',
                        'type' => 'select',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Connection encryption type',
                        'validation' => 'required|in:tls,ssl,none',
                        'options' => [
                            'tls' => 'TLS',
                            'ssl' => 'SSL',
                            'none' => 'None',
                        ],
                    ],
                    [
                        'key' => 'from_email',
                        'label' => 'From Email',
                        'type' => 'email',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Default sender email address',
                        'validation' => 'required|email|max:255',
                    ],
                    [
                        'key' => 'from_name',
                        'label' => 'From Name',
                        'type' => 'text',
                        'required' => false,
                        'is_secret' => false,
                        'help_text' => 'Default sender name',
                        'validation' => 'nullable|string|max:255',
                    ],
                ],
                'test_action' => [
                    'type' => 'send_test_email',
                    'inputs' => ['recipient' => 'email'],
                ],
            ],
            [
                'key' => 'sendgrid',
                'category' => 'email',
                'display_name' => 'SendGrid',
                'description' => 'SendGrid email service',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'api_key',
                        'label' => 'API Key',
                        'type' => 'password',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'SendGrid API Key',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'from_email',
                        'label' => 'From Email',
                        'type' => 'email',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Verified sender email',
                        'validation' => 'required|email|max:255',
                    ],
                    [
                        'key' => 'from_name',
                        'label' => 'From Name',
                        'type' => 'text',
                        'required' => false,
                        'is_secret' => false,
                        'help_text' => 'Default sender name',
                        'validation' => 'nullable|string|max:255',
                    ],
                ],
                'test_action' => [
                    'type' => 'send_test_email',
                    'inputs' => ['recipient' => 'email'],
                ],
            ],
            [
                'key' => 'mailgun',
                'category' => 'email',
                'display_name' => 'Mailgun',
                'description' => 'Mailgun email service',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'api_key',
                        'label' => 'API Key',
                        'type' => 'password',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'Mailgun API Key',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'domain',
                        'label' => 'Domain',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Mailgun domain',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'from_email',
                        'label' => 'From Email',
                        'type' => 'email',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Default sender email',
                        'validation' => 'required|email|max:255',
                    ],
                ],
                'test_action' => [
                    'type' => 'send_test_email',
                    'inputs' => ['recipient' => 'email'],
                ],
            ],
        ];
    }

    /**
     * SMS providers
     */
    protected static function smsProviders(): array
    {
        return [
            [
                'key' => 'msg91',
                'category' => 'sms',
                'display_name' => 'MSG91',
                'description' => 'MSG91 SMS provider for India',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'auth_key',
                        'label' => 'Auth Key',
                        'type' => 'password',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'MSG91 Auth Key',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'sender_id',
                        'label' => 'Sender ID',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => '6-character sender ID (e.g., ZYPTOS)',
                        'validation' => 'required|string|size:6',
                    ],
                    [
                        'key' => 'route',
                        'label' => 'Route',
                        'type' => 'select',
                        'required' => false,
                        'is_secret' => false,
                        'help_text' => 'SMS route type',
                        'validation' => 'nullable|in:1,4',
                        'options' => [
                            '1' => 'Promotional',
                            '4' => 'Transactional',
                        ],
                        'default' => '4',
                    ],
                ],
                'test_action' => [
                    'type' => 'send_test_sms',
                    'inputs' => ['recipient' => 'phone', 'message' => 'text'],
                ],
            ],
            [
                'key' => 'twilio',
                'category' => 'sms',
                'display_name' => 'Twilio',
                'description' => 'Twilio SMS service',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'account_sid',
                        'label' => 'Account SID',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Twilio Account SID',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'auth_token',
                        'label' => 'Auth Token',
                        'type' => 'password',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'Twilio Auth Token',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'from_number',
                        'label' => 'From Number',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Twilio phone number (E.164 format)',
                        'validation' => 'required|string|max:20',
                    ],
                ],
                'test_action' => [
                    'type' => 'send_test_sms',
                    'inputs' => ['recipient' => 'phone', 'message' => 'text'],
                ],
            ],
        ];
    }

    /**
     * WhatsApp providers
     */
    protected static function whatsappProviders(): array
    {
        return [
            [
                'key' => 'interakt',
                'category' => 'whatsapp',
                'display_name' => 'Interakt',
                'description' => 'Interakt WhatsApp Business API',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'api_key',
                        'label' => 'API Key',
                        'type' => 'password',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'Interakt API Key',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'phone_number_id',
                        'label' => 'Phone Number ID',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Interakt Phone Number ID',
                        'validation' => 'required|string|max:255',
                    ],
                ],
                'test_action' => [
                    'type' => 'send_test_whatsapp',
                    'inputs' => ['recipient' => 'phone', 'message' => 'text'],
                ],
            ],
            [
                'key' => 'wati',
                'category' => 'whatsapp',
                'display_name' => 'Wati',
                'description' => 'Wati WhatsApp Business API',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'api_token',
                        'label' => 'API Token',
                        'type' => 'password',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'Wati API Token',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'whatsapp_instance_id',
                        'label' => 'WhatsApp Instance ID',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Wati WhatsApp Instance ID',
                        'validation' => 'required|string|max:255',
                    ],
                ],
                'test_action' => [
                    'type' => 'send_test_whatsapp',
                    'inputs' => ['recipient' => 'phone', 'message' => 'text'],
                ],
            ],
            [
                'key' => 'gupshup',
                'category' => 'whatsapp',
                'display_name' => 'Gupshup',
                'description' => 'Gupshup WhatsApp Business API',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'api_key',
                        'label' => 'API Key',
                        'type' => 'password',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'Gupshup API Key',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'app_name',
                        'label' => 'App Name',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Gupshup App Name',
                        'validation' => 'required|string|max:255',
                    ],
                ],
                'test_action' => [
                    'type' => 'send_test_whatsapp',
                    'inputs' => ['recipient' => 'phone', 'message' => 'text'],
                ],
            ],
        ];
    }

    /**
     * Push notification providers
     */
    protected static function pushProviders(): array
    {
        return [
            [
                'key' => 'firebase_fcm_v1',
                'category' => 'push',
                'display_name' => 'Firebase FCM (HTTP v1)',
                'description' => 'Firebase Cloud Messaging HTTP v1 API (OAuth2 service account). Legacy server keys are not supported.',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'service_account_json',
                        'label' => 'Service Account JSON',
                        'type' => 'file_json',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'Upload Firebase service account JSON file for FCM HTTP v1. This will be encrypted and stored securely. Legacy server keys are not supported.',
                        'validation' => 'required',
                    ],
                    [
                        'key' => 'project_id',
                        'label' => 'Project ID',
                        'type' => 'text',
                        'required' => false,
                        'is_secret' => false,
                        'help_text' => 'Auto-extracted from service account JSON (editable). Used for FCM HTTP v1 API endpoint.',
                        'validation' => 'nullable|string|max:255',
                    ],
                ],
                'test_action' => [
                    'type' => 'send_test_push',
                    'inputs' => ['device_token' => 'text', 'title' => 'text', 'body' => 'text'],
                ],
            ],
        ];
    }

    /**
     * Auth providers
     */
    protected static function authProviders(): array
    {
        return [
            [
                'key' => 'firebase_auth',
                'category' => 'auth',
                'display_name' => 'Firebase Auth',
                'description' => 'Firebase Authentication (Phone/Email) - Modern FlutterFire implementation',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'firebase_project_id',
                        'label' => 'Firebase Project ID',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Firebase Project ID (same as FCM project)',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'web_api_key',
                        'label' => 'Web API Key',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'Firebase Web API Key (for Flutter client initialization)',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'service_account_json',
                        'label' => 'Service Account JSON (Server-side Verification)',
                        'type' => 'file_json',
                        'required' => false,
                        'is_secret' => true,
                        'help_text' => 'Optional: For server-side ID token verification. Only needed if you verify Firebase ID tokens on backend.',
                        'validation' => 'nullable',
                    ],
                ],
                'test_action' => [
                    'type' => 'verify_test_token',
                    'inputs' => ['id_token' => 'text'],
                ],
            ],
        ];
    }

    /**
     * Storage providers
     */
    protected static function storageProviders(): array
    {
        return [
            [
                'key' => 's3',
                'category' => 'storage',
                'display_name' => 'AWS S3',
                'description' => 'Amazon S3 or S3-compatible storage',
                'supports_env' => ['sandbox', 'live'],
                'credential_schema' => [
                    [
                        'key' => 'access_key_id',
                        'label' => 'Access Key ID',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'S3 Access Key ID',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'secret_access_key',
                        'label' => 'Secret Access Key',
                        'type' => 'password',
                        'required' => true,
                        'is_secret' => true,
                        'help_text' => 'S3 Secret Access Key',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'bucket',
                        'label' => 'Bucket Name',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'S3 bucket name',
                        'validation' => 'required|string|max:255',
                    ],
                    [
                        'key' => 'region',
                        'label' => 'Region',
                        'type' => 'text',
                        'required' => true,
                        'is_secret' => false,
                        'help_text' => 'S3 region (e.g., us-east-1)',
                        'validation' => 'required|string|max:50',
                    ],
                    [
                        'key' => 'endpoint',
                        'label' => 'Endpoint (Optional)',
                        'type' => 'url',
                        'required' => false,
                        'is_secret' => false,
                        'help_text' => 'Custom endpoint for S3-compatible services',
                        'validation' => 'nullable|url|max:255',
                    ],
                ],
                'test_action' => [
                    'type' => 'test_connection',
                    'inputs' => [],
                ],
            ],
        ];
    }
}

