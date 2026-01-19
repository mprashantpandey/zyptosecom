# ZyptoseComm - Advanced Ecommerce Platform

**Production-ready, single-vendor advanced ecommerce platform with dynamic configuration**

## 🎯 Core Principle

**NOTHING is hardcoded. Everything is configurable from the Admin Panel without code changes or app rebuilds.**

## 🏗️ Architecture Overview

### Tech Stack
- **Backend**: Laravel 11 LTS + MySQL
- **Admin Panel**: Laravel Web (Modern UI, Responsive, Role-based)
- **Web Storefront**: Next.js (SSR for SEO)
- **Mobile App**: Flutter (Android + iOS)
- **APIs**: REST, Versioned (v1, v2 ready)
- **Auth**: Laravel Sanctum (Token-based)
- **Queues**: Laravel Queues + Horizon
- **Storage**: Local + S3 Compatible
- **Config**: Database-driven + Remote Config APIs

### Key Features

1. **Dynamic Branding & App Management** - Complete control from admin panel
2. **Feature Flags & Module Manager** - Enable/disable features per platform/version
3. **Pluggable Auth System** - Firebase, Custom OTP, Email/Password
4. **Provider-Agnostic Payments** - Razorpay, PayU, Stripe, Cashfree, PhonePe, COD
5. **India-Focused Shipping** - ShipRocket and other providers
6. **Complete Catalog & Inventory** - Multi-warehouse ready
7. **Flexible Order Workflow** - Admin-defined custom statuses
8. **Promotions & Loyalty** - Coupons, Wallet, Cashback, Points, Referrals
9. **Multi-Channel Notifications** - Push, SMS, Email, WhatsApp
10. **CMS & Content Management** - Multi-language support
11. **Home Layout Builder** - Drag-drop JSON-based sections
12. **Role-Based Access Control** - Granular permissions + Audit logs
13. **Analytics & Monitoring** - Sales reports, system health, webhook logs

## 📁 Project Structure

```
ZyptoseComm/
├── app/
│   ├── Core/
│   │   ├── Contracts/          # Interfaces for provider patterns
│   │   ├── Services/           # Core services (Settings, Secrets, RemoteConfig)
│   │   ├── Traits/             # Reusable traits (HasAuditLog, Encryptable)
│   │   └── Exceptions/         # Custom exceptions
│   ├── Modules/
│   │   ├── Branding/           # Branding & App Management
│   │   ├── Auth/               # Pluggable Auth
│   │   ├── Payments/           # Payment providers
│   │   ├── Shipping/           # Shipping providers
│   │   ├── Catalog/            # Products, Categories, Inventory
│   │   ├── Orders/             # Orders & Fulfillment
│   │   ├── Promotions/         # Coupons, Wallet, Loyalty
│   │   ├── Notifications/      # Multi-channel notifications
│   │   ├── CMS/                # Content management
│   │   ├── HomeBuilder/        # Layout builder
│   │   └── Analytics/          # Reports & monitoring
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── v1/         # Version 1 APIs
│   │   │   │   └── v2/         # Version 2 APIs (future)
│   │   │   └── Admin/          # Admin panel controllers
│   │   ├── Middleware/
│   │   ├── Requests/           # Form requests
│   │   └── Resources/          # API resources
│   └── Models/
│       └── ...                 # Models organized by module
├── database/
│   ├── migrations/             # All migrations
│   └── seeders/               # Database seeders
├── resources/
│   ├── views/
│   │   └── admin/             # Admin panel views
│   └── js/
│       └── admin/             # Admin panel JS
├── routes/
│   ├── api.php                # API routes (versioned)
│   ├── web.php                # Web routes (admin panel)
│   └── channels.php           # Broadcasting channels
└── config/                     # Configuration files

```

## 🚀 Installation

### Prerequisites
- PHP 8.2+
- Composer
- MySQL 8.0+
- Node.js 18+ (for admin panel assets)
- Redis (recommended for queues/cache)

### Setup Steps

1. **Clone and Install Dependencies**
   ```bash
   composer install
   npm install
   ```

2. **Environment Configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   php artisan secrets:generate-key  # For secrets vault encryption
   ```

3. **Database Setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

4. **Storage & Permissions**
   ```bash
   php artisan storage:link
   chmod -R 775 storage bootstrap/cache
   ```

5. **Queue Worker (Production)**
   ```bash
   php artisan horizon
   # Or for simple queue: php artisan queue:work
   ```

6. **Admin Panel Assets**
   ```bash
   npm run build
   # Or for dev: npm run dev
   ```

## 🔐 Initial Admin Access

After seeding, default admin credentials:
- **Email**: admin@zyptosecomm.com
- **Password**: password (change immediately)

## 📚 Documentation

- [API Documentation](./docs/API.md)
- [Module Development Guide](./docs/MODULES.md)
- [Admin Panel Guide](./docs/ADMIN.md)
- [Flutter Integration](./docs/FLUTTER.md)
- [Next.js Integration](./docs/NEXTJS.md)

## 🔧 Configuration

All configuration is managed through the Admin Panel. Key areas:

- **Branding**: App name, logos, colors, fonts
- **Modules**: Enable/disable features
- **Providers**: Payment, shipping, notification providers
- **Secrets**: Encrypted credentials for providers
- **Feature Flags**: Platform/version-based toggles
- **Content**: Multi-language strings, pages

## 🧪 Testing

```bash
php artisan test
```

## 📝 License

Proprietary - For marketplace distribution

## 🤝 Support

For support and documentation, visit the admin panel's help section.

# zyptosecom
