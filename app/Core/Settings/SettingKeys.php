<?php

namespace App\Core\Settings;

/**
 * Registry of all setting keys used in the application.
 * Keys are internal only - UI uses friendly labels.
 */
class SettingKeys
{
    // General Settings
    public const STORE_NAME = 'store_name';
    public const STORE_PHONE = 'store_phone';
    public const STORE_EMAIL = 'store_email';
    public const STORE_ADDRESS = 'store_address';
    public const STORE_CITY = 'store_city';
    public const STORE_STATE = 'store_state';
    public const STORE_PINCODE = 'store_pincode';
    public const CURRENCY = 'currency';
    public const TIMEZONE = 'timezone';
    public const GSTIN = 'gstin';
    public const INVOICE_PREFIX = 'invoice_prefix';
    public const INVOICE_START_NUMBER = 'invoice_start_number';
    public const WEB_URL = 'web_url';

    // Auth Settings
    public const AUTH_METHOD = 'auth_method';
    public const FIREBASE_PROJECT_ID = 'firebase_project_id';
    public const FIREBASE_API_KEY = 'firebase_api_key';
    public const OTP_PROVIDER = 'otp_provider';
    public const OTP_CREDENTIAL_ID = 'otp_credential_id';
    public const ENABLE_EMAIL_LOGIN = 'enable_email_login';

    // Payment Settings
    public const ENABLE_COD = 'enable_cod';
    public const COD_MAX_AMOUNT = 'cod_max_amount';
    public const COD_PINCODES = 'cod_pincodes';
    public const RAZORPAY_ENABLED = 'razorpay_enabled';
    public const RAZORPAY_ENVIRONMENT = 'razorpay_environment';
    public const RAZORPAY_CREDENTIAL_ID = 'razorpay_credential_id';
    public const PAYU_ENABLED = 'payu_enabled';
    public const PAYU_ENVIRONMENT = 'payu_environment';
    public const PAYU_CREDENTIAL_ID = 'payu_credential_id';
    public const STRIPE_ENABLED = 'stripe_enabled';
    public const STRIPE_ENVIRONMENT = 'stripe_environment';
    public const STRIPE_CREDENTIAL_ID = 'stripe_credential_id';
    public const CASHFREE_ENABLED = 'cashfree_enabled';
    public const CASHFREE_ENVIRONMENT = 'cashfree_environment';
    public const CASHFREE_CREDENTIAL_ID = 'cashfree_credential_id';
    public const PHONEPE_ENABLED = 'phonepe_enabled';
    public const PHONEPE_ENVIRONMENT = 'phonepe_environment';
    public const PHONEPE_CREDENTIAL_ID = 'phonepe_credential_id';

    // Shipping Settings
    public const ACTIVE_SHIPPING_PROVIDER = 'active_shipping_provider';
    public const SHIPPING_ENVIRONMENT = 'shipping_environment';
    public const SHIPPING_CREDENTIAL_ID = 'shipping_credential_id';
    public const DEFAULT_PICKUP_ADDRESS = 'default_pickup_address';
    public const SERVICEABILITY_MODE = 'serviceability_mode';

    // Notification Settings
    public const QUIET_HOURS_ENABLED = 'quiet_hours_enabled';
    public const QUIET_HOURS_START = 'quiet_hours_start';
    public const QUIET_HOURS_END = 'quiet_hours_end';
    public const PUSH_ENABLED = 'push_enabled';
    public const FIREBASE_SENDER_ID = 'firebase_sender_id';
    public const SMS_PROVIDER = 'sms_provider';
    public const SMS_CREDENTIAL_ID = 'sms_credential_id';
    public const WHATSAPP_PROVIDER = 'whatsapp_provider';
    public const WHATSAPP_CREDENTIAL_ID = 'whatsapp_credential_id';

    // Email Settings
    public const EMAIL_PROVIDER = 'email_provider';
    public const SMTP_HOST = 'smtp_host';
    public const SMTP_PORT = 'smtp_port';
    public const SMTP_USERNAME = 'smtp_username';
    public const SMTP_PASSWORD = 'smtp_password';
    public const SMTP_ENCRYPTION = 'smtp_encryption';
    public const EMAIL_FROM_ADDRESS = 'email_from_address';
    public const EMAIL_FROM_NAME = 'email_from_name';
    public const SENDGRID_CREDENTIAL_ID = 'sendgrid_credential_id';
    public const MAILGUN_CREDENTIAL_ID = 'mailgun_credential_id';

    // Storage Settings
    public const STORAGE_DRIVER = 'storage.driver';
    public const STORAGE_S3_CREDENTIAL_ID = 'storage.s3.credential_id';
    public const STORAGE_S3_BUCKET = 'storage.s3.bucket';
    public const STORAGE_S3_REGION = 'storage.s3.region';
    public const STORAGE_S3_BASE_URL = 'storage.s3.base_url';
}

