<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AdminAuditCommand extends Command
{
    protected $signature = 'admin:audit {--json : Output as JSON}';
    protected $description = 'Audit Filament admin panel - verify all required resources, pages, and widgets exist';

    protected $inventory;
    protected $results = [
        'resources' => ['found' => [], 'missing' => []],
        'pages' => ['found' => [], 'missing' => []],
        'widgets' => ['found' => [], 'missing' => []],
        'summary' => ['total_required' => 0, 'total_found' => 0, 'total_missing' => 0]
    ];

    public function handle()
    {
        $this->info('🔍 Starting Admin Panel Audit...');
        $this->newLine();

        // Load inventory config
        $inventoryPath = config_path('admin-inventory.json');
        if (!File::exists($inventoryPath)) {
            $this->error("Inventory config not found: {$inventoryPath}");
            return 1;
        }

        $this->inventory = json_decode(File::get($inventoryPath), true);

        // Scan Filament resources
        $this->scanResources();
        
        // Scan Filament pages
        $this->scanPages();
        
        // Scan Filament widgets
        $this->scanWidgets();

        // Generate report
        $this->generateReport();

        return $this->results['summary']['total_missing'] > 0 ? 1 : 0;
    }

    protected function scanResources()
    {
        $this->info('📦 Scanning Resources...');
        
        $resourcesPath = app_path('Filament/Resources');
        if (!File::isDirectory($resourcesPath)) {
            File::makeDirectory($resourcesPath, 0755, true);
        }

        $foundResources = [];
        foreach (File::files($resourcesPath) as $file) {
            if (Str::endsWith($file->getFilename(), 'Resource.php')) {
                $className = 'App\\Filament\\Resources\\' . $file->getBasename('.php');
                $foundResources[] = $className;
                $this->results['resources']['found'][] = $className;
            }
        }

        // Check required resources from inventory
        foreach ($this->inventory['modules'] as $module) {
            foreach ($module['resources'] ?? [] as $resource) {
                $this->results['summary']['total_required']++;
                
                if ($resource['required'] ?? false) {
                    $class = $resource['class'];
                    if (!in_array($class, $foundResources)) {
                        $this->results['resources']['missing'][] = [
                            'module' => $module['name'],
                            'class' => $class,
                            'name' => $resource['name']
                        ];
                        $this->results['summary']['total_missing']++;
                    }
                }
            }
        }
    }

    protected function scanPages()
    {
        $this->info('📄 Scanning Pages...');
        
        $pagesPath = app_path('Filament/Pages');
        if (!File::isDirectory($pagesPath)) {
            File::makeDirectory($pagesPath, 0755, true);
        }

        $foundPages = [];
        foreach (File::files($pagesPath) as $file) {
            if (Str::endsWith($file->getFilename(), '.php')) {
                $className = 'App\\Filament\\Pages\\' . $file->getBasename('.php');
                $foundPages[] = $className;
                $this->results['pages']['found'][] = $className;
            }
        }

        // Check required pages from inventory
        foreach ($this->inventory['modules'] as $module) {
            foreach ($module['pages'] ?? [] as $page) {
                $this->results['summary']['total_required']++;
                
                if ($page['required'] ?? false) {
                    $class = $page['class'];
                    if (!in_array($class, $foundPages)) {
                        $this->results['pages']['missing'][] = [
                            'module' => $module['name'],
                            'class' => $class,
                            'name' => $page['name'],
                            'route' => $page['route'] ?? null
                        ];
                        $this->results['summary']['total_missing']++;
                    }
                }
            }
        }
    }

    protected function scanWidgets()
    {
        $this->info('📊 Scanning Widgets...');
        
        $widgetsPath = app_path('Filament/Widgets');
        if (!File::isDirectory($widgetsPath)) {
            File::makeDirectory($widgetsPath, 0755, true);
        }

        $foundWidgets = [];
        foreach (File::files($widgetsPath) as $file) {
            // All PHP files in Widgets directory are widgets
            if (Str::endsWith($file->getFilename(), '.php')) {
                $className = 'App\\Filament\\Widgets\\' . $file->getBasename('.php');
                $foundWidgets[] = $className;
                $this->results['widgets']['found'][] = $className;
            }
        }

        // Check required widgets from inventory
        foreach ($this->inventory['modules'] as $module) {
            foreach ($module['widgets'] ?? [] as $widget) {
                if ($widget['required'] ?? false) {
                    $this->results['summary']['total_required']++;
                    $class = $widget['class'];
                    if (!in_array($class, $foundWidgets)) {
                        $this->results['widgets']['missing'][] = [
                            'module' => $module['name'],
                            'class' => $class,
                            'name' => $widget['name']
                        ];
                        $this->results['summary']['total_missing']++;
                    }
                }
            }
        }
    }

    protected function generateReport()
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('                    AUDIT REPORT');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        // Summary
        $summary = $this->results['summary'];
        $found = $summary['total_required'] - $summary['total_missing'];
        $percentage = $summary['total_required'] > 0 
            ? round(($found / $summary['total_required']) * 100, 1) 
            : 0;

        $this->info("📊 Summary:");
        $this->line("   Total Required: {$summary['total_required']}");
        $this->line("   Found: {$found}");
        $this->line("   Missing: {$summary['total_missing']}");
        $this->line("   Completion: {$percentage}%");
        $this->newLine();

        // Resources
        $this->info("📦 Resources:");
        $this->line("   Found: " . count($this->results['resources']['found']));
        $this->line("   Missing: " . count($this->results['resources']['missing']));
        
        if (count($this->results['resources']['missing']) > 0) {
            $this->warn("   Missing Resources:");
            foreach ($this->results['resources']['missing'] as $missing) {
                $this->line("     ❌ {$missing['class']} ({$missing['module']})");
            }
        }
        $this->newLine();

        // Pages
        $this->info("📄 Pages:");
        $this->line("   Found: " . count($this->results['pages']['found']));
        $this->line("   Missing: " . count($this->results['pages']['missing']));
        
        if (count($this->results['pages']['missing']) > 0) {
            $this->warn("   Missing Pages:");
            foreach ($this->results['pages']['missing'] as $missing) {
                $this->line("     ❌ {$missing['class']} ({$missing['module']})");
                if ($missing['route']) {
                    $this->line("        Route: {$missing['route']}");
                }
            }
        }
        $this->newLine();

        // Widgets
        $this->info("📊 Widgets:");
        $this->line("   Found: " . count($this->results['widgets']['found']));
        $this->line("   Missing: " . count($this->results['widgets']['missing']));
        
        if (count($this->results['widgets']['missing']) > 0) {
            $this->warn("   Missing Widgets:");
            foreach ($this->results['widgets']['missing'] as $missing) {
                $this->line("     ❌ {$missing['class']} ({$missing['module']})");
            }
        }
        $this->newLine();

        // Output JSON if requested
        if ($this->option('json')) {
            $this->line(json_encode($this->results, JSON_PRETTY_PRINT));
        }

        // Final status
        if ($summary['total_missing'] > 0) {
            $this->error("❌ Audit failed: {$summary['total_missing']} items missing");
        } else {
            $this->info("✅ Audit passed: All required items found!");
        }
    }
}

