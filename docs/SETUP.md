# Setup Guide

## Prerequisites

- PHP 8.2 or higher
- Composer 2.x
- MySQL 8.0 or higher
- Node.js 18+ and npm (for admin panel assets)
- Redis (recommended for queues and cache)
- Git

## Installation Steps

### 1. Clone and Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies (for admin panel)
npm install
```

### 2. Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Generate secrets encryption key
php artisan key:generate --show
# Copy the output and set SECRETS_ENCRYPTION_KEY in .env
```

Edit `.env` file:

```env
APP_NAME=ZyptoseComm
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=zyptosecomm
DB_USERNAME=root
DB_PASSWORD=your_password

# Redis (optional but recommended)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue Configuration
QUEUE_CONNECTION=redis  # or 'database' for simple setup

# Cache Configuration
CACHE_STORE=redis  # or 'database'

# Mail Configuration (for notifications)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_FROM_ADDRESS=noreply@zyptosecomm.com
MAIL_FROM_NAME="${APP_NAME}"

# AWS S3 (optional, for file storage)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

# Encryption Key for Secrets Vault
SECRETS_ENCRYPTION_KEY=your_generated_key_here
```

### 3. Database Setup

```bash
# Create database
mysql -u root -p
CREATE DATABASE zyptosecomm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;

# Run migrations
php artisan migrate

# Seed initial data
php artisan db:seed
```

### 4. Storage Setup

```bash
# Create storage link
php artisan storage:link

# Set permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache  # Linux
# Or for Mac/Local: skip chown
```

### 5. Queue Worker Setup

For development:
```bash
php artisan queue:work
```

For production (with Horizon):
```bash
# Install Horizon assets
php artisan horizon:install
php artisan migrate

# Start Horizon
php artisan horizon
```

### 6. Admin Panel Assets

```bash
# Development
npm run dev

# Production
npm run build
```

### 7. Test Configuration

```bash
# Run tests
php artisan test
```

## Initial Setup After Installation

### 1. Create Admin User

After seeding, default admin credentials:
- **Email**: admin@zyptosecomm.com
- **Password**: password

**⚠️ IMPORTANT: Change password immediately!**

### 2. Configure First Settings

1. Login to admin panel: `http://localhost/admin`
2. Go to **Branding & Settings**
3. Configure:
   - App name and company name
   - Logo and icons
   - Primary theme colors
   - Default currency

### 3. Enable Modules

1. Go to **Modules** in admin panel
2. Enable required modules:
   - Payments
   - Shipping
   - Notifications
   - Catalog
   - Orders

### 4. Configure Providers

#### Payment Providers

1. Go to **Providers** → **Payments**
2. Select and enable a provider (e.g., Razorpay)
3. Add credentials:
   - Go to **Secrets** section
   - Add `api_key` and `secret_key`
   - Select environment (sandbox/production)

#### Shipping Providers

1. Go to **Providers** → **Shipping**
2. Select ShipRocket (or other)
3. Add credentials in **Secrets**

#### Notification Providers

1. Go to **Providers** → **Notifications**
2. Configure:
   - Email (SMTP settings)
   - Push notifications (Firebase)
   - SMS (Twilio or similar)

### 5. Configure App Versions

1. Go to **App Management**
2. Add app versions for Android/iOS
3. Set update policies (force/optional)
4. Add maintenance mode if needed

### 6. Setup Home Layout

1. Go to **Home Builder**
2. Create sections:
   - Banner carousel
   - Featured products
   - Categories
3. Set order and visibility

## Verification

### 1. Test Remote Config API

```bash
curl http://localhost/api/v1/config?platform=app
```

Should return JSON with branding, theme, modules, etc.

### 2. Test Admin Panel

Visit: `http://localhost/admin`

Should show dashboard (login required).

### 3. Test Feature Flags

```bash
# Check if module is enabled
php artisan tinker
>>> app(\App\Core\Services\ModuleService::class)->isEnabled('payments', 'app');
```

## Troubleshooting

### Migration Errors

```bash
# Reset database (WARNING: deletes all data)
php artisan migrate:fresh --seed
```

### Permission Errors

```bash
# Fix storage permissions
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

### Cache Issues

```bash
# Clear all cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Queue Not Processing

```bash
# Check queue connection in .env
# Make sure QUEUE_CONNECTION is set correctly

# Restart queue worker
php artisan queue:restart
```

### Secrets Encryption Error

Make sure `SECRETS_ENCRYPTION_KEY` is set in `.env`:

```bash
# Generate a new key
php artisan key:generate --show
# Copy and paste into .env
```

## Production Deployment

### 1. Environment Setup

```bash
# Set APP_ENV=production
# Set APP_DEBUG=false
# Use production database credentials
# Configure Redis for cache and queues
```

### 2. Optimize

```bash
# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev
```

### 3. Queue Worker

Use Supervisor or systemd to keep queue worker running:

**Supervisor Configuration** (`/etc/supervisor/conf.d/zyptosecomm-worker.conf`):
```ini
[program:zyptosecomm-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/worker.log
stopwaitsecs=3600
```

### 4. Horizon (Production Queues)

```bash
# Install Horizon
php artisan horizon:install

# Configure Supervisor for Horizon
# See Laravel Horizon documentation
```

### 5. Cron Jobs

Add to crontab:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### 6. Web Server Configuration

#### Nginx

```nginx
server {
    listen 80;
    server_name api.example.com;
    root /path/to/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Security Checklist

- [ ] Change default admin password
- [ ] Set `APP_DEBUG=false` in production
- [ ] Use strong `APP_KEY` and `SECRETS_ENCRYPTION_KEY`
- [ ] Configure HTTPS/SSL
- [ ] Set secure session configuration
- [ ] Enable CSRF protection
- [ ] Configure rate limiting
- [ ] Use Redis with password in production
- [ ] Regular backups of database
- [ ] Keep dependencies updated
- [ ] Use environment variables for all secrets
- [ ] Configure firewall rules
- [ ] Enable audit logging

## Support

For issues and questions:
1. Check documentation in `/docs` folder
2. Review error logs in `storage/logs/`
3. Check API documentation in `/docs/API.md`
4. Review architecture in `/docs/ARCHITECTURE.md`

