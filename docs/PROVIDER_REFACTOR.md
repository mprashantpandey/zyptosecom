# Provider System Refactor - Registry-Managed Providers

## Overview
Refactored the provider system to be fully registry-managed, eliminating manual provider creation and ensuring all providers are synced from `ProviderRegistry`.

## Changes Made

### A) Disabled Manual Provider Creation

1. **ProviderResource** (`app/Filament/Resources/ProviderResource.php`):
   - `canCreate()` returns `false`
   - `canDeleteAny()` returns `false`
   - `canDelete()` returns `false`
   - Form fields are disabled (read-only) except for `is_enabled`, `environment`, and `priority`
   - Added info placeholder: "Providers are managed via ProviderRegistry"

2. **CreateProvider Page** (`app/Filament/Resources/ProviderResource/Pages/CreateProvider.php`):
   - `mount()` method aborts with 403 error
   - Message: "Providers are managed via ProviderRegistry. Run `php artisan providers:sync` to sync from registry."

3. **ListProviders Page** (`app/Filament/Resources/ProviderResource/Pages/ListProviders.php`):
   - Removed "Create" button
   - Added "Sync from Registry" button
   - Added "Test Integrations" button linking to IntegrationsTester

4. **EditProvider Page** (`app/Filament/Resources/ProviderResource/Pages/EditProvider.php`):
   - Removed "Delete" action
   - Added "Configure Credentials" action

5. **ProviderResource Table**:
   - Removed bulk delete actions
   - Added "Configure Credentials" action (primary)
   - Added "Toggle Enable/Disable" action
   - Empty state message: "Run `php artisan providers:sync` to sync providers from registry"

### B) Provider Sync Command

**Created**: `app/Console/Commands/SyncProvidersCommand.php`

**Command**: `php artisan providers:sync`

**Functionality**:
- Reads all providers from `ProviderRegistry::all()`
- Upserts into `providers` table by `type` + `name` (unique key)
- Sets:
  - `type` (category)
  - `name` (provider key)
  - `label` (display name)
  - `description`
  - `driver_class` (auto-assigned based on category/key)
  - `is_enabled` (default: true)
  - `environment` (default: sandbox)
  - `config_schema` (from registry)
  - `metadata` (supports_env, test_action)
  - `priority` (category-based defaults)
- Marks providers not in registry (but doesn't disable them - safer)

**Auto-run**: Added to `DatabaseSeeder` to run automatically on `php artisan db:seed`

### C) Firebase Push Schema (FCM HTTP v1)

**Updated**: `app/Core/Providers/ProviderRegistry.php`

**Provider**: `firebase_fcm_v1`

**Changes**:
- ✅ Uses `service_account_json` (file upload, REQUIRED, secret)
- ✅ `project_id` (text, optional, auto-extracted from JSON)
- ❌ Removed: `server_key` (legacy, not supported)
- ❌ Removed: Legacy sender key fields

**Help Text**: "Use Firebase service account JSON (FCM HTTP v1). Legacy server keys are not supported."

### D) Firebase Auth Schema (Modern FlutterFire)

**Updated**: `app/Core/Providers/ProviderRegistry.php`

**Provider**: `firebase_auth`

**Changes**:
- ✅ `firebase_project_id` (text, required)
- ✅ `web_api_key` (text, required) - for Flutter client initialization
- ✅ `service_account_json` (file_json, optional) - only for server-side ID token verification
- ❌ Removed: `server_key` references

**Help Text**: Updated to reflect modern FlutterFire implementation

### E) Navigation Updates

1. **ProviderCredentialsPage**:
   - Now visible in navigation (was hidden)
   - Navigation Group: "Providers"
   - Navigation Label: "Provider Credentials"
   - Sort: 1 (first in group)

2. **ProviderResource**:
   - Still visible in navigation (for viewing/managing)
   - But creation is disabled

## Acceptance Criteria ✅

- ✅ `/admin/providers/create` returns 403 (CreateProvider::mount() aborts)
- ✅ Providers in DB are synced via `providers:sync` command
- ✅ Firebase push credentials UI shows SERVICE ACCOUNT JSON upload, NOT server key
- ✅ NotificationService push uses FCM HTTP v1 (already implemented)
- ✅ CodeCanyon UX: admin only configures credentials + tests, never creates providers
- ✅ ProviderCredentialsPage is main entry point for configuring credentials
- ✅ Sync command runs automatically in DatabaseSeeder

## Testing

1. **Test Provider Sync**:
   ```bash
   php artisan providers:sync
   ```
   Should sync 17 providers from registry.

2. **Test Create Block**:
   - Navigate to `/admin/providers/create`
   - Should see 403 error

3. **Test Firebase Credentials**:
   - Go to Providers → Select "Firebase FCM (HTTP v1)"
   - Click "Configure Credentials"
   - Should see "Service Account JSON" file upload field
   - Should NOT see "server_key" field

4. **Test Navigation**:
   - "Provider Credentials" should be visible in Providers group
   - "Providers" resource should be visible but read-only

## Files Changed

- `app/Filament/Resources/ProviderResource.php` - Made read-only
- `app/Filament/Resources/ProviderResource/Pages/CreateProvider.php` - Abort 403
- `app/Filament/Resources/ProviderResource/Pages/ListProviders.php` - Sync button
- `app/Filament/Resources/ProviderResource/Pages/EditProvider.php` - Remove delete
- `app/Filament/Resources/ProviderResource/Pages/ViewProvider.php` - Added actions
- `app/Filament/Pages/ProviderCredentialsPage.php` - Made visible in nav
- `app/Core/Providers/ProviderRegistry.php` - Updated Firebase schemas
- `app/Console/Commands/SyncProvidersCommand.php` - New command
- `database/seeders/DatabaseSeeder.php` - Added providers:sync call

