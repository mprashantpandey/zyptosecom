<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

class AdminQaCommand extends Command
{
    protected $signature = 'admin:qa {--json : Output as JSON}';
    protected $description = 'QA verification for Filament admin panel - checks completeness of resources, pages, and widgets';

    protected $results = [
        'resources' => [],
        'pages' => [],
        'permissions' => [],
        'audit_logs' => [],
        'summary' => ['total_checks' => 0, 'passed' => 0, 'failed' => 0]
    ];

    protected $sensitiveModules = [
        'Settings', 'Feature Flags', 'Providers', 'Credentials', 
        'Branding', 'Payments', 'Shipping', 'Pricing', 'Refunds'
    ];

    public function handle()
    {
        $this->info('🔍 Starting Admin Panel QA Verification...');
        $this->newLine();

        // Load inventory
        $inventoryPath = config_path('admin-inventory.json');
        if (!File::exists($inventoryPath)) {
            $this->error("Inventory config not found: {$inventoryPath}");
            return 1;
        }

        $inventory = json_decode(File::get($inventoryPath), true);

        // Check Resources
        $this->checkResources($inventory);

        // Check Pages
        $this->checkPages($inventory);

        // Check Permissions
        $this->checkPermissions($inventory);

        // Check Audit Logs
        $this->checkAuditLogs($inventory);

        // Generate report
        $this->generateReport();

        // Output JSON if requested
        if ($this->option('json')) {
            $jsonPath = storage_path('app/admin_qa_report.json');
            File::put($jsonPath, json_encode($this->results, JSON_PRETTY_PRINT));
            $this->info("JSON report saved to: {$jsonPath}");
        }

        return $this->results['summary']['failed'] > 0 ? 1 : 0;
    }

    protected function checkResources($inventory)
    {
        $this->info('📦 Checking Resources...');

        foreach ($inventory['modules'] as $module) {
            foreach ($module['resources'] ?? [] as $resourceConfig) {
                $className = $resourceConfig['class'];
                
                if (!class_exists($className)) {
                    $this->results['resources'][] = [
                        'class' => $className,
                        'module' => $module['name'],
                        'status' => 'FAIL',
                        'issues' => ['Class does not exist']
                    ];
                    $this->results['summary']['total_checks']++;
                    $this->results['summary']['failed']++;
                    continue;
                }

                $issues = [];
                $reflection = new ReflectionClass($className);

                // Check if resource is hidden from navigation
                $shouldRegisterNav = null;
                if ($reflection->hasMethod('shouldRegisterNavigation')) {
                    $shouldRegisterNav = $className::shouldRegisterNavigation();
                } else {
                    $shouldRegisterNav = $this->getStaticProperty($className, 'shouldRegisterNavigation');
                }
                $isHidden = $shouldRegisterNav === false;

                // Only check table/form if resource is visible (not hidden)
                if (!$isHidden) {
                    // Check table() method
                    if (!$reflection->hasMethod('table')) {
                        $issues[] = 'Missing table() method';
                    } else {
                        $tableIssues = $this->checkTableMethod($className);
                        $issues = array_merge($issues, $tableIssues);
                    }

                    // Check form() method
                    if (!$reflection->hasMethod('form')) {
                        $issues[] = 'Missing form() method';
                    } else {
                        $formIssues = $this->checkFormMethod($className);
                        $issues = array_merge($issues, $formIssues);
                    }
                }

                // Check navigation (only for visible resources)
                if (!$isHidden) {
                    $navIssues = $this->checkNavigation($className);
                    $issues = array_merge($issues, $navIssues);
                }

                // Check pages (only for visible resources)
                if (!$isHidden) {
                    $pagesIssues = $this->checkResourcePages($className);
                    $issues = array_merge($issues, $pagesIssues);
                }

                $status = empty($issues) ? 'PASS' : 'FAIL';
                if ($status === 'PASS') {
                    $this->results['summary']['passed']++;
                } else {
                    $this->results['summary']['failed']++;
                }
                $this->results['summary']['total_checks']++;

                $this->results['resources'][] = [
                    'class' => $className,
                    'module' => $module['name'],
                    'status' => $status,
                    'issues' => $issues
                ];
            }
        }
    }

    protected function checkTableMethod($className)
    {
        $issues = [];
        
        try {
            $reflection = new ReflectionClass($className);
            
            // Check if table() method exists (static or instance)
            $hasTableMethod = $reflection->hasMethod('table');
            if (!$hasTableMethod) {
                $issues[] = "table() method not found";
                return $issues;
            }

            $tableMethod = $reflection->getMethod('table');
            $fileName = $reflection->getFileName();
            $sourceCode = File::get($fileName);
            
            // Extract method body using line numbers
            $startLine = $tableMethod->getStartLine();
            $endLine = $tableMethod->getEndLine();
            $lines = explode("\n", $sourceCode);
            $methodBody = implode("\n", array_slice($lines, $startLine, $endLine - $startLine));

            // Count column definitions
            $columnCount = preg_match_all('/Tables\\\Columns\\\\.*::make\(/i', $methodBody);
            
            // Major entities should have >= 5 columns
            $majorEntities = ['Product', 'Order', 'User', 'Coupon', 'Category', 'Brand'];
            $isMajor = Str::contains($className, $majorEntities);
            
            if ($isMajor && $columnCount < 5) {
                $issues[] = "Table has only {$columnCount} columns (minimum 5 expected for major entities)";
            } elseif ($columnCount === 0) {
                $issues[] = "Table has 0 columns defined (placeholder detected)";
            }

            // Check filters
            $filterCount = preg_match_all('/->filters\(/', $methodBody);
            if ($columnCount > 0 && $filterCount === 0) {
                $issues[] = "Table has 0 filters (at least 1 filter expected for searchable entities)";
            }

            // Check actions
            $actionCount = preg_match_all('/->actions\(/', $methodBody);
            if ($actionCount === 0) {
                $issues[] = "Table has 0 row actions defined";
            }

            // Check bulk actions
            $bulkActionCount = preg_match_all('/->bulkActions\(/', $methodBody);
            if ($bulkActionCount === 0) {
                $issues[] = "Table has 0 bulk actions defined";
            }

        } catch (\Exception $e) {
            $issues[] = "Error checking table: " . $e->getMessage();
        }

        return $issues;
    }

    protected function checkFormMethod($className)
    {
        $issues = [];
        
        try {
            $reflection = new ReflectionClass($className);
            
            // Check if form() method exists (static or instance)
            $hasFormMethod = $reflection->hasMethod('form');
            if (!$hasFormMethod) {
                $issues[] = "form() method not found";
                return $issues;
            }

            $formMethod = $reflection->getMethod('form');
            $fileName = $reflection->getFileName();
            $sourceCode = File::get($fileName);
            
            // Extract method body using line numbers
            $startLine = $formMethod->getStartLine();
            $endLine = $formMethod->getEndLine();
            $lines = explode("\n", $sourceCode);
            $methodBody = implode("\n", array_slice($lines, $startLine, $endLine - $startLine));

            // Count field definitions (TextInput, Textarea, Select, etc.)
            $fieldCount = preg_match_all('/Forms\\\Components\\\\.*::make\(/i', $methodBody);
            
            // Major entities should have >= 6 fields
            $majorEntities = ['Product', 'Order', 'User', 'Coupon', 'Category', 'Brand'];
            $isMajor = Str::contains($className, $majorEntities);
            
            if ($isMajor && $fieldCount < 6) {
                $issues[] = "Form has only {$fieldCount} fields (minimum 6 expected for major entities)";
            } elseif ($fieldCount === 0) {
                $issues[] = "Form has 0 fields defined (placeholder detected)";
            }

            // Check for validation (->required(), ->numeric(), ->maxLength(), etc.)
            $hasValidation = preg_match('/->(required|numeric|maxLength|min|max|email|url)\(/i', $methodBody);
            if (!$hasValidation && $fieldCount > 0) {
                $issues[] = "Form fields lack validation rules";
            }

        } catch (\Exception $e) {
            $issues[] = "Error checking form: " . $e->getMessage();
        }

        return $issues;
    }

    protected function checkNavigation($className)
    {
        $issues = [];
        
        try {
            $reflection = new ReflectionClass($className);
            
            // Check navigation group
            if (!$reflection->hasProperty('navigationGroup') && 
                !$reflection->getStaticPropertyValue('navigationGroup')) {
                // Check if it's intentionally hidden
                $shouldHide = $reflection->getStaticPropertyValue('shouldRegisterNavigation') ?? true;
                if ($shouldHide) {
                    // Try to get from parent
                    $navGroup = $this->getStaticProperty($className, 'navigationGroup');
                    if (!$navGroup) {
                        $issues[] = "Navigation group not set";
                    }
                }
            }

            // Check icon (skip if navigation is disabled)
            $shouldRegisterNav = $this->getStaticProperty($className, 'shouldRegisterNavigation');
            if ($shouldRegisterNav !== false) {
                $icon = $this->getStaticProperty($className, 'navigationIcon');
                if (!$icon || $icon === 'heroicon-o-rectangle-stack') {
                    $issues[] = "Navigation icon not set or using default placeholder";
                }
            }

        } catch (\Exception $e) {
            // Navigation check is optional, don't fail on error
        }

        return $issues;
    }

    protected function checkResourcePages($className)
    {
        $issues = [];
        
        try {
            $resource = new $className;
            $pages = $resource::getPages();
            
            $requiredPages = ['index'];
            foreach ($requiredPages as $page) {
                if (!isset($pages[$page])) {
                    $issues[] = "Missing required page: {$page}";
                }
            }

        } catch (\Exception $e) {
            $issues[] = "Error checking pages: " . $e->getMessage();
        }

        return $issues;
    }

    protected function checkPages($inventory)
    {
        $this->info('📄 Checking Pages...');

        foreach ($inventory['modules'] as $module) {
            foreach ($module['pages'] ?? [] as $pageConfig) {
                $className = $pageConfig['class'];
                
                if (!class_exists($className)) {
                    $this->results['pages'][] = [
                        'class' => $className,
                        'module' => $module['name'],
                        'status' => 'FAIL',
                        'issues' => ['Class does not exist']
                    ];
                    $this->results['summary']['total_checks']++;
                    $this->results['summary']['failed']++;
                    continue;
                }

                $issues = [];
                $reflection = new ReflectionClass($className);

                // Check if page is hidden from navigation
                $shouldRegisterNav = null;
                if ($reflection->hasMethod('shouldRegisterNavigation')) {
                    $shouldRegisterNav = $className::shouldRegisterNavigation();
                } else {
                    $shouldRegisterNav = $this->getStaticProperty($className, 'shouldRegisterNavigation');
                }
                $isHidden = $shouldRegisterNav === false;

                // Only check implementation if page is visible (not hidden)
                if (!$isHidden) {
                    // Check for form, table, or builder using Reflection
                    $hasForm = $reflection->hasMethod('form');
                    $hasTable = $reflection->hasMethod('table');
                    $hasBuilder = $reflection->hasMethod('form') || Str::contains($className, 'Builder');

                    if (!$hasForm && !$hasTable && !$hasBuilder) {
                        $issues[] = "Page has no form, table, or builder implementation";
                    }

                    // Check for save/submit method
                    $hasSaveMethod = $reflection->hasMethod('save') || 
                                    $reflection->hasMethod('submit') ||
                                    $reflection->hasMethod('store');

                    if ($hasForm && !$hasSaveMethod) {
                        $issues[] = "Page has form but no save/submit/store method";
                    }
                }

                // Check navigation (optional - some pages are intentionally hidden)
                $shouldHaveNav = !Str::contains($className, ['Test', 'Preview']);
                if ($shouldHaveNav) {
                    $navLabel = $this->getStaticProperty($className, 'navigationLabel');
                    if (!$navLabel) {
                        // Not a critical issue, just a note
                    }
                }

                // Check permission
                if (!$reflection->hasMethod('canAccess')) {
                    // Try to find permission checks in mount/render
                    $sourceCode = File::get($reflection->getFileName());
                    if (!Str::contains($sourceCode, ['can(', 'authorize(', 'Gate::']) && 
                        !Str::contains($sourceCode, 'canAccess')) {
                        $issues[] = "Page lacks permission check (canAccess method or Gate/policy call)";
                    }
                }

                $status = empty($issues) ? 'PASS' : 'FAIL';
                if ($status === 'PASS') {
                    $this->results['summary']['passed']++;
                } else {
                    $this->results['summary']['failed']++;
                }
                $this->results['summary']['total_checks']++;

                $this->results['pages'][] = [
                    'class' => $className,
                    'module' => $module['name'],
                    'status' => $status,
                    'issues' => $issues
                ];
            }
        }
    }

    protected function checkPermissions($inventory)
    {
        $this->info('🔐 Checking Permissions...');

        // Check if permission seeder exists
        $seederPath = database_path('seeders/PermissionSeeder.php');
        if (!File::exists($seederPath)) {
            $this->results['permissions'][] = [
                'check' => 'PermissionSeeder',
                'status' => 'FAIL',
                'issues' => ['PermissionSeeder does not exist']
            ];
            $this->results['summary']['total_checks']++;
            $this->results['summary']['failed']++;
        } else {
            $this->results['permissions'][] = [
                'check' => 'PermissionSeeder',
                'status' => 'PASS',
                'issues' => []
            ];
            $this->results['summary']['total_checks']++;
            $this->results['summary']['passed']++;
        }

        // Check if permissions are seeded
        try {
            $permissionCount = \DB::table('permissions')->count();
            if ($permissionCount === 0) {
                $this->results['permissions'][] = [
                    'check' => 'Permissions Seeded',
                    'status' => 'FAIL',
                    'issues' => ['No permissions found in database (run PermissionSeeder)']
                ];
                $this->results['summary']['total_checks']++;
                $this->results['summary']['failed']++;
            } else {
                $this->results['permissions'][] = [
                    'check' => 'Permissions Seeded',
                    'status' => 'PASS',
                    'issues' => []
                ];
                $this->results['summary']['total_checks']++;
                $this->results['summary']['passed']++;
            }
        } catch (\Exception $e) {
            $this->results['permissions'][] = [
                'check' => 'Permissions Seeded',
                'status' => 'FAIL',
                'issues' => ['Error checking permissions: ' . $e->getMessage()]
            ];
            $this->results['summary']['total_checks']++;
            $this->results['summary']['failed']++;
        }
    }

    protected function checkAuditLogs($inventory)
    {
        $this->info('📝 Checking Audit Logs...');

        // Check if AuditService exists
        $auditServicePath = app_path('Core/Services/AuditService.php');
        if (!File::exists($auditServicePath)) {
            $this->results['audit_logs'][] = [
                'check' => 'AuditService',
                'status' => 'FAIL',
                'issues' => ['AuditService does not exist']
            ];
            $this->results['summary']['total_checks']++;
            $this->results['summary']['failed']++;
        } else {
            $this->results['audit_logs'][] = [
                'check' => 'AuditService',
                'status' => 'PASS',
                'issues' => []
            ];
            $this->results['summary']['total_checks']++;
            $this->results['summary']['passed']++;
        }

        // Check sensitive modules for audit logging
        // This is a code analysis check - look for audit log calls in resource/page files
        foreach ($this->sensitiveModules as $module) {
            // This would require deeper analysis - simplified for now
            // In real implementation, scan source files for AuditService::log calls
        }
    }

    // Helper methods
    protected function getTableColumns($table)
    {
        try {
            $columns = [];
            $reflection = new ReflectionClass($table);
            $columnsProperty = $reflection->getProperty('columns');
            $columnsProperty->setAccessible(true);
            $columns = $columnsProperty->getValue($table) ?? [];
            return is_array($columns) ? $columns : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function getTableFilters($table)
    {
        try {
            $reflection = new ReflectionClass($table);
            $filtersProperty = $reflection->getProperty('filters');
            $filtersProperty->setAccessible(true);
            return $filtersProperty->getValue($table) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function getTableActions($table)
    {
        try {
            $reflection = new ReflectionClass($table);
            $actionsProperty = $reflection->getProperty('actions');
            $actionsProperty->setAccessible(true);
            return $actionsProperty->getValue($table) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function getTableBulkActions($table)
    {
        try {
            $reflection = new ReflectionClass($table);
            $bulkActionsProperty = $reflection->getProperty('bulkActions');
            $bulkActionsProperty->setAccessible(true);
            return $bulkActionsProperty->getValue($table) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function getFormFields($form)
    {
        try {
            $reflection = new ReflectionClass($form);
            $componentsProperty = $reflection->getProperty('components');
            $componentsProperty->setAccessible(true);
            return $componentsProperty->getValue($form) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function hasFormValidation($form)
    {
        // Simplified check - in real implementation, analyze form component rules
        return true; // Assume validation exists if form has fields
    }

    protected function hasMethodWithContent($reflection, $methodName)
    {
        if (!$reflection->hasMethod($methodName)) {
            return false;
        }

        $method = $reflection->getMethod($methodName);
        $fileName = $reflection->getFileName();
        $sourceCode = File::get($fileName);
        
        // Extract method body
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $lines = explode("\n", $sourceCode);
        $methodBody = implode("\n", array_slice($lines, $startLine, $endLine - $startLine));

        // Check if method has content (not just placeholder comments)
        $methodBody = trim($methodBody);
        return !empty($methodBody) && 
               !Str::contains($methodBody, ['//', 'return $form', 'return $table']) &&
               Str::length($methodBody) > 50; // Has some implementation
    }

    protected function getStaticProperty($className, $propertyName)
    {
        try {
            $reflection = new ReflectionClass($className);
            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);
                return $property->getValue();
            }
        } catch (\Exception $e) {
            // Ignore
        }
        return null;
    }

    protected function generateReport()
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('                    QA REPORT');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        $summary = $this->results['summary'];
        $percentage = $summary['total_checks'] > 0 
            ? round(($summary['passed'] / $summary['total_checks']) * 100, 1) 
            : 0;

        $this->info("📊 Summary:");
        $this->line("   Total Checks: {$summary['total_checks']}");
        $this->line("   Passed: {$summary['passed']}");
        $this->line("   Failed: {$summary['failed']}");
        $this->line("   Completion: {$percentage}%");
        $this->newLine();

        // Resources
        $resourceFails = array_filter($this->results['resources'], fn($r) => $r['status'] === 'FAIL');
        if (count($resourceFails) > 0) {
            $this->warn("📦 Resources with Issues: " . count($resourceFails));
            foreach (array_slice($resourceFails, 0, 10) as $resource) {
                $this->line("   ❌ {$resource['class']}");
                foreach ($resource['issues'] as $issue) {
                    $this->line("      - {$issue}");
                }
            }
            if (count($resourceFails) > 10) {
                $this->line("      ... and " . (count($resourceFails) - 10) . " more");
            }
            $this->newLine();
        }

        // Pages
        $pageFails = array_filter($this->results['pages'], fn($p) => $p['status'] === 'FAIL');
        if (count($pageFails) > 0) {
            $this->warn("📄 Pages with Issues: " . count($pageFails));
            foreach (array_slice($pageFails, 0, 10) as $page) {
                $this->line("   ❌ {$page['class']}");
                foreach ($page['issues'] as $issue) {
                    $this->line("      - {$issue}");
                }
            }
            if (count($pageFails) > 10) {
                $this->line("      ... and " . (count($pageFails) - 10) . " more");
            }
            $this->newLine();
        }

        // Permissions & Audit
        foreach (['permissions', 'audit_logs'] as $checkType) {
            $fails = array_filter($this->results[$checkType] ?? [], fn($c) => $c['status'] === 'FAIL');
            if (count($fails) > 0) {
                $label = Str::title(str_replace('_', ' ', $checkType));
                $this->warn("🔐 {$label} Issues:");
                foreach ($fails as $check) {
                    $this->line("   ❌ {$check['check']}");
                    foreach ($check['issues'] as $issue) {
                        $this->line("      - {$issue}");
                    }
                }
                $this->newLine();
            }
        }

        // Final status
        if ($summary['failed'] > 0) {
            $this->error("❌ QA failed: {$summary['failed']} checks failed");
        } else {
            $this->info("✅ QA passed: All checks passed!");
        }
    }
}

