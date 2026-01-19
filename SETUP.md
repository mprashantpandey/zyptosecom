# Quick Setup Guide - MySQL & Run App

## Current Status
✅ Composer dependencies installed  
✅ Database created: `zyptosecomm`  
⚠️ Laravel core files missing (need artisan, bootstrap, config, etc.)

## Option 1: Fresh Laravel Install (Recommended)

Since this appears to be a new project structure without Laravel core files, the easiest way is:

```bash
# 1. Install Laravel in a temp location
cd /tmp
composer create-project laravel/laravel:^11.0 temp-laravel

# 2. Copy your custom files to the new Laravel project
cd /Users/prashant/Desktop/ZyptoseComm

# 3. Copy Laravel core files from temp
cp /tmp/temp-laravel/artisan .
cp -r /tmp/temp-laravel/bootstrap .
cp -r /tmp/temp-laravel/config .
cp -r /tmp/temp-laravel/public .
cp -r /tmp/temp-laravel/storage .
cp -r /tmp/temp-laravel/tests .
cp /tmp/temp-laravel/.gitignore .

# 4. Clean up
rm -rf /tmp/temp-laravel

# 5. Continue with setup below
```

## Option 2: Complete Setup (If you have Laravel core files)

Once Laravel core files are in place, run these commands:

```bash
cd /Users/prashant/Desktop/ZyptoseComm

# 1. Generate application key
php artisan key:generate

# 2. Run migrations
php artisan migrate

# 3. Seed database
php artisan db:seed

# 4. Create storage link
php artisan storage:link

# 5. Install npm dependencies (if not done)
npm install

# 6. Build assets (development)
npm run dev
# OR for production:
# npm run build

# 7. Start development server
php artisan serve
```

## Verify Setup

1. **Check Database**: 
   ```bash
   mysql -u root -e "SHOW DATABASES LIKE 'zyptosecomm';"
   ```

2. **Check Tables**:
   ```bash
   mysql -u root zyptosecomm -e "SHOW TABLES;"
   ```

3. **Access Application**:
   - Admin Panel: http://localhost:8000/admin
   - API: http://localhost:8000/api/v1/config

4. **Default Admin Login**:
   - Email: admin@zyptosecomm.com
   - Password: password

## Troubleshooting

If you get "Could not open input file: artisan":
- Laravel core files are missing. Use Option 1 above.

If migrations fail:
```bash
php artisan migrate:fresh --seed
```

If you need to reset everything:
```bash
php artisan migrate:fresh --seed
php artisan cache:clear
php artisan config:clear
```

