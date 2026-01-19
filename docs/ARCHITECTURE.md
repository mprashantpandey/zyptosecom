# System Architecture

## Overview

ZyptoseComm is built on a **modular, provider-agnostic architecture** where **NOTHING is hardcoded**. Every aspect of the system can be configured dynamically from the Admin Panel.

## Core Principles

1. **Dynamic Configuration** - All settings, credentials, and behavior controlled via Admin Panel
2. **Provider Pattern** - Interchangeable providers for payments, shipping, notifications, auth
3. **Remote Config** - App and web fetch configuration at runtime
4. **Feature Flags** - Module-level and rule-based feature toggles
5. **Audit Logging** - Complete audit trail for sensitive operations
6. **Modular Design** - Self-contained modules that can be enabled/disabled

## Architecture Layers

### 1. Core Layer (`app/Core/`)

**Services:**
- `SettingsService` - Centralized settings management with caching
- `SecretsService` - Encrypted credential storage and retrieval
- `ModuleService` - Module enable/disable and rule management
- `RemoteConfigService` - Aggregates all config for app/web consumption

**Traits:**
- `HasAuditLog` - Automatic audit logging for models
- `Encryptable` - Encryption helper for sensitive data

**Contracts:**
- `PaymentProviderInterface` - Payment provider abstraction
- `ShippingProviderInterface` - Shipping provider abstraction
- `AuthProviderInterface` - Authentication provider abstraction
- `NotificationProviderInterface` - Notification channel abstraction

### 2. Module Layer (`app/Modules/`)

Each module is self-contained with:
- Models (database entities)
- Controllers (API + Admin)
- Services (business logic)
- Providers (driver implementations)
- Requests (validation)

**Module Structure:**
```
app/Modules/{ModuleName}/
├── Models/
├── Controllers/
│   ├── Api/
│   └── Admin/
├── Services/
├── Providers/
└── Requests/
```

### 3. API Layer (`app/Http/Controllers/Api/`)

Versioned REST APIs:
- `/api/v1/*` - Current stable version
- `/api/v2/*` - Future version (prepared)

All APIs follow RESTful conventions with proper error handling.

### 4. Admin Layer (`app/Http/Controllers/Admin/`)

Admin panel controllers for:
- Branding & App Management
- Module Management
- Provider Configuration
- Settings Management
- Content Management
- Analytics & Reports

## Database Design

### Core Tables

**Configuration:**
- `settings` - General settings (public + private)
- `secrets` - Encrypted credentials (payment, shipping, etc.)
- `modules` - Module definitions and status
- `module_rules` - Module-specific rules and feature flags

**Branding & UI:**
- `brands` - Brand information
- `themes` - Theme configurations (colors, fonts, etc.)
- `app_versions` - Version management and updates
- `home_sections` - Home layout sections (JSON config)

**Providers:**
- `providers` - Provider definitions and metadata
- `provider_credentials` - Stored in `secrets` table

**Content:**
- `content_strings` - Multi-language text strings
- `cms_pages` - Static pages (Privacy, Terms, etc.)

**Audit:**
- `audit_logs` - Complete audit trail

### Business Tables

- `users` - User accounts (admin, customer, vendor)
- `products` - Product catalog
- `categories` - Category hierarchy
- `orders` - Orders and fulfillment
- `order_items` - Order line items
- `payments` - Payment transactions
- `coupons` - Promotional codes
- `wallets` - User wallets
- `wallet_transactions` - Wallet transaction history
- `notifications` - Notification queue and history
- `webhooks` - Webhook processing log

## Remote Configuration Flow

```
App/Web Request → RemoteConfigService
                ↓
    ┌─────────────────────────┐
    │   Aggregates from:      │
    ├─────────────────────────┤
    │ • SettingsService       │
    │ • ModuleService         │
    │ • Theme Model           │
    │ • AppVersion Model      │
    │ • HomeSection Model     │
    └─────────────────────────┘
                ↓
        JSON Response
```

**Response Structure:**
```json
{
  "branding": { /* app name, logos, etc. */ },
  "theme": { /* colors, fonts, etc. */ },
  "modules": { /* enabled modules */ },
  "app_management": { /* version, update, maintenance */ },
  "feature_flags": { /* module rules */ },
  "home_layout": [ /* sections array */ ],
  "content_strings": { /* translations */ },
  "timestamp": "2024-01-01T00:00:00Z"
}
```

## Provider Pattern

### Payment Providers

All payment providers implement `PaymentProviderInterface`:

```php
interface PaymentProviderInterface {
    public function initiate(array $paymentData): array;
    public function verify(string $transactionId): array;
    public function refund(string $transactionId, float $amount): array;
    public function handleWebhook(array $payload): array;
}
```

**Example Providers:**
- `RazorpayProvider`
- `PayUProvider`
- `StripeProvider`
- `CashfreeProvider`
- `PhonePeProvider`
- `CodProvider` (internal)

**Selection Logic:**
1. Check `providers` table for enabled payment provider
2. Load credentials from `secrets` table
3. Instantiate provider class
4. Execute operation

### Shipping Providers

Similar pattern for shipping:
- `ShipRocketProvider`
- Other India-based providers

## Feature Flags & Module Rules

**Module Level:**
- Enable/disable entire module
- Platform-based (web, app, all)
- Version-based (min_app_version)
- Time-based (enabled_at, disabled_at)

**Rule Level:**
- Module-specific rules (e.g., `cod.max_amount`)
- Condition-based rules
- Time-based rules

**Example:**
```php
// Check if COD is enabled and within limit
$moduleService->isEnabled('payments', 'app', '1.2.0');
$codLimit = $moduleService->getRule('payments', 'cod_max_amount', 5000);
```

## Security

### Secrets Management

All sensitive credentials stored in `secrets` table with:
- Encryption using Laravel's `Crypt` facade
- Environment-based separation (sandbox/production)
- Audit logging on create/update/delete

### Audit Logging

All sensitive operations logged via `HasAuditLog` trait:
- Setting changes
- Credential changes
- Price changes
- Module toggles
- Order status changes

**Audit Log Fields:**
- User who made change
- Event type (created, updated, deleted)
- Action type (setting_change, credential_change, etc.)
- Old and new values
- IP address and user agent
- Timestamp

## Caching Strategy

**Settings Cache:**
- Key: `setting:{key}`
- TTL: 3600 seconds (1 hour)
- Invalidated on update

**Module Cache:**
- Key: `module:enabled:{name}:{platform}:{version}`
- TTL: 3600 seconds
- Invalidated on module toggle

**Remote Config Cache:**
- Key: `remote_config:{platform}:{version}`
- TTL: 300 seconds (5 minutes)
- Aggregated from multiple sources

## API Versioning

APIs are versioned in URL path:
- `/api/v1/*` - Current version
- `/api/v2/*` - Future version

Controllers organized as:
```
app/Http/Controllers/Api/
├── v1/
│   ├── RemoteConfigController.php
│   ├── AuthController.php
│   └── ...
└── v2/
    └── ... (future)
```

## Error Handling

All APIs return consistent JSON structure:

**Success:**
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional message"
}
```

**Error:**
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human readable message",
    "details": { ... }
  }
}
```

## Queue & Jobs

Long-running operations use Laravel Queues:
- Payment processing
- Shipping label generation
- Email/SMS sending
- Webhook processing
- Report generation

Managed via Laravel Horizon for production.

## Webhooks

External providers send webhooks to:
- `/api/webhooks/{provider_name}`

Webhooks are:
1. Received and validated (signature verification)
2. Stored in `webhooks` table
3. Processed asynchronously via queue
4. Logged for debugging

## Multi-tenancy Ready

Architecture supports multi-tenancy (future enhancement):
- Tenant-aware settings
- Tenant-specific providers
- Data isolation

## Scalability Considerations

- Database indexes on frequently queried fields
- Caching layer for configuration
- Queue-based async processing
- Stateless API design
- CDN-ready for static assets
- Redis for sessions and cache (production)

