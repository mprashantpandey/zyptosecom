<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds all permissions from admin-inventory.json configuration
     */
    public function run(): void
    {
        // Load inventory config
        $inventoryPath = config_path('admin-inventory.json');
        if (!file_exists($inventoryPath)) {
            $this->command->warn('admin-inventory.json not found. Creating basic permissions...');
            $this->createBasicPermissions();
            return;
        }

        $inventory = json_decode(file_get_contents($inventoryPath), true);

        // Generate permissions from inventory modules
        $permissions = $this->generatePermissionsFromInventory($inventory);

        // Create permissions
        $this->command->info('Creating permissions...');
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        $this->command->info('✅ Created ' . count($permissions) . ' permissions');

        // Create default roles
        $this->createDefaultRoles($permissions);
    }

    /**
     * Generate comprehensive permissions from inventory
     */
    protected function generatePermissionsFromInventory(array $inventory): array
    {
        $permissions = [];

        // Permissions defined in inventory
        foreach ($inventory['permissions'] ?? [] as $perm) {
            $permissions[] = $perm['name'];
        }

        // Generate module-based permissions for each module
        foreach ($inventory['modules'] ?? [] as $module) {
            $moduleId = $module['id'] ?? strtolower(str_replace(' ', '_', $module['name']));

            // Resource permissions
            foreach ($module['resources'] ?? [] as $resource) {
                $resourceName = strtolower(str_replace('Resource', '', $resource['name']));
                $permissions[] = "{$resourceName}.view";
                $permissions[] = "{$resourceName}.create";
                $permissions[] = "{$resourceName}.edit";
                $permissions[] = "{$resourceName}.delete";
            }

            // Page permissions
            foreach ($module['pages'] ?? [] as $page) {
                $pageName = strtolower(str_replace('Page', '', $page['name']));
                $pageName = str_replace('Manager', '', $pageName);
                $permissions[] = "{$pageName}.view";
                $permissions[] = "{$pageName}.edit";
            }
        }

        // Standard CRUD permissions for common resources
        $commonResources = ['product', 'order', 'user', 'category', 'brand', 'coupon', 'cms_page', 'content_string', 'home_section'];
        foreach ($commonResources as $resource) {
            $permissions[] = "{$resource}.view";
            $permissions[] = "{$resource}.create";
            $permissions[] = "{$resource}.edit";
            $permissions[] = "{$resource}.delete";
        }

        // Special permissions
        $specialPermissions = [
            'dashboard.view',
            'settings.view',
            'settings.edit',
            'settings.feature_flags',
            'integrations.view',
            'integrations.edit',
            'integrations.test',
            'integrations.sync',
            'branding.edit',
            'branding.theme.edit',
            'branding.app_versions',
            'branding.maintenance',
            'branding.kill_switch',
            'users.segments',
            'users.wallet.adjust',
            'roles.assign',
            'orders.workflow',
            'orders.batch',
            'orders.invoices',
            'inventory.adjust',
            'inventory.low_stock',
            'coupons.view',
            'coupons.create',
            'coupons.edit',
            'coupons.delete',
            'coupons.rules',
            'coupons.test',
            'deals.view',
            'deals.create',
            'deals.edit',
            'deals.delete',
            'sales.calendar',
            'sales.conflicts',
            'checkout.rules',
            'checkout.pincodes',
            'returns.reasons',
            'returns.rules',
            'returns.approve',
            'wallet.view',
            'loyalty.rules',
            'loyalty.referrals',
            'loyalty.fraud',
            'reviews.moderate',
            'notifications.view',
            'notifications.edit',
            'notifications.test',
            'notifications.events',
            'notifications.logs.view',
            'notifications.logs.retry',
            'email.templates.view',
            'email.templates.edit',
            'email.templates.test',
            'email.view',
            'email.credentials',
            'email.preview',
            'system.health.view',
            'system.tools.run',
            'system.cron.view',
            'system.cron.test',
            'system.alerts',
            'system.alert_rules',
            'system.tools.view',
            'system.developer_tools.view',
            'settings.storage.view',
            'settings.storage.edit',
            'settings.languages.view',
            'settings.languages.edit',
            'settings.translations.view',
            'settings.translations.edit',
            'settings.translations.import',
            'settings.translations.export',
            'settings.currencies.view',
            'settings.currencies.edit',
            'settings.exchange_rates.edit',
            'integrations.test',
            'webhooks.view',
            'reports.view',
            'reports.export',
            'audit.view',
            'audit.diff.view',
        ];

        $permissions = array_merge($permissions, $specialPermissions);

        // Remove duplicates and sort
        return array_unique($permissions);
    }

    /**
     * Create basic permissions if inventory not found
     */
    protected function createBasicPermissions(): void
    {
        $basicPermissions = [
            'dashboard.view',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',
            'orders.view',
            'orders.edit',
            'settings.view',
            'settings.edit',
        ];

        foreach ($basicPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->command->info('✅ Created ' . count($basicPermissions) . ' basic permissions');
    }

    /**
     * Create default roles and assign permissions
     */
    protected function createDefaultRoles(array $permissions): void
    {
        $this->command->info('Creating default roles...');

        // Super Admin - all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($permissions);
        $this->command->info('  ✅ Super Admin (all permissions)');

        // Manager - all except security
        $manager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $managerPermissions = array_filter($permissions, fn($p) => !str_starts_with($p, 'roles.') && !str_starts_with($p, 'permissions.'));
        $manager->syncPermissions($managerPermissions);
        $this->command->info('  ✅ Manager (' . count($managerPermissions) . ' permissions)');

        // Support - orders, customers, refunds
        $support = Role::firstOrCreate(['name' => 'Support', 'guard_name' => 'web']);
        $supportPermissions = array_filter($permissions, fn($p) => 
            str_starts_with($p, 'orders.') || 
            str_starts_with($p, 'user.') || 
            str_starts_with($p, 'returns.') ||
            $p === 'dashboard.view'
        );
        $support->syncPermissions($supportPermissions);
        $this->command->info('  ✅ Support (' . count($supportPermissions) . ' permissions)');

        // Content Editor - CMS, content, home builder
        $contentEditor = Role::firstOrCreate(['name' => 'Content Editor', 'guard_name' => 'web']);
        $contentPermissions = array_filter($permissions, fn($p) => 
            str_starts_with($p, 'cms_page.') || 
            str_starts_with($p, 'content_string.') || 
            str_starts_with($p, 'home_section.') ||
            $p === 'dashboard.view'
        );
        $contentEditor->syncPermissions($contentPermissions);
        $this->command->info('  ✅ Content Editor (' . count($contentPermissions) . ' permissions)');

        // Inventory Staff - products, inventory, warehouse
        $inventoryStaff = Role::firstOrCreate(['name' => 'Inventory Staff', 'guard_name' => 'web']);
        $inventoryPermissions = array_filter($permissions, fn($p) => 
            str_starts_with($p, 'product.') || 
            str_starts_with($p, 'category.') || 
            str_starts_with($p, 'brand.') || 
            str_starts_with($p, 'inventory.') ||
            $p === 'dashboard.view'
        );
        $inventoryStaff->syncPermissions($inventoryPermissions);
        $this->command->info('  ✅ Inventory Staff (' . count($inventoryPermissions) . ' permissions)');
    }
}
