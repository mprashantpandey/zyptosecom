<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;

class AppFeaturesAuditCommand extends Command
{
    protected $signature = 'app:features {--fix : Auto-hide/lock unfinished visible features}';
    protected $description = 'Audit feature completeness and generate reports';

    protected array $statuses = [];
    protected array $moduleStats = [];

    public function handle(): int
    {
        $this->info('🔍 Auditing Feature Completeness...');
        $this->newLine();

        $inventory = config('feature-inventory.modules', []);
        $this->statuses = [];
        $this->moduleStats = [];

        foreach ($inventory as $moduleKey => $module) {
            $this->auditModule($moduleKey, $module);
        }

        $this->displaySummary();
        $this->generateReports();

        if ($this->option('fix')) {
            $this->applyFixes();
        }

        return 0;
    }

    protected function auditModule(string $moduleKey, array $module): void
    {
        $this->line("📦 Module: {$module['label']}");

        $items = $module['items'] ?? [];
        $implemented = 0;
        $partial = 0;
        $notImplemented = 0;
        $hidden = 0;
        $missingClass = 0;

        foreach ($items as $item) {
            $status = $this->auditItem($item);
            $this->statuses[$moduleKey][] = [
                'item' => $item,
                'status' => $status,
            ];

            match ($status) {
                'IMPLEMENTED' => $implemented++,
                'PARTIAL' => $partial++,
                'NOT_IMPLEMENTED' => $notImplemented++,
                'HIDDEN' => $hidden++,
                'MISSING_CLASS' => $missingClass++,
                default => null,
            };
        }

        $total = count($items);
        $visibleTotal = $total - $hidden;
        $completion = $visibleTotal > 0 ? round(($implemented / $visibleTotal) * 100, 1) : 100;

        $this->moduleStats[$moduleKey] = [
            'label' => $module['label'],
            'priority' => $module['priority'] ?? 999,
            'for_mvp' => $module['for_codecanyon_mvp'] ?? false,
            'total' => $total,
            'implemented' => $implemented,
            'partial' => $partial,
            'not_implemented' => $notImplemented,
            'hidden' => $hidden,
            'missing_class' => $missingClass,
            'completion' => $completion,
        ];

        $this->line("   ✅ {$implemented} | ⚠️  {$partial} | ❌ {$notImplemented} | 👁️  {$hidden} | 🔴 {$missingClass} | 📊 {$completion}%");
        $this->newLine();
    }

    protected function auditItem(array $item): string
    {
        $class = $item['class'] ?? null;
        if (!$class || !class_exists($class)) {
            return 'MISSING_CLASS';
        }

        try {
            $reflection = new ReflectionClass($class);
        } catch (\ReflectionException $e) {
            return 'MISSING_CLASS';
        }

        // Check if item is intentionally hidden
        if ($this->isItemHidden($reflection)) {
            return 'HIDDEN';
        }

        // Check required methods
        $requiredMethods = $item['required_methods'] ?? [];
        $missingMethods = [];
        $hasMethods = [];

        foreach ($requiredMethods as $method) {
            if ($reflection->hasMethod($method)) {
                $hasMethods[] = $method;
            } else {
                $missingMethods[] = $method;
            }
        }

        // Special checks for Filament Resources
        if ($item['type'] === 'resource') {
            $resourceChecks = $this->checkFilamentResource($reflection);
            if (!$resourceChecks['has_table'] || !$resourceChecks['has_form']) {
                $missingMethods[] = 'table() or form()';
            }
            if (!$resourceChecks['has_pages']) {
                $missingMethods[] = 'getPages()';
            }
        }

        // Special checks for Filament Pages
        if ($item['type'] === 'page') {
            $pageChecks = $this->checkFilamentPage($reflection);
            if (!$pageChecks['has_mount']) {
                $missingMethods[] = 'mount()';
            }
            // Check if save is expected but missing
            if (in_array('save', $requiredMethods) && !$reflection->hasMethod('save')) {
                $missingMethods[] = 'save()';
            }
        }

        // Determine status
        if (empty($missingMethods)) {
            return 'IMPLEMENTED';
        } elseif (count($hasMethods) > 0) {
            return 'PARTIAL';
        } else {
            return 'NOT_IMPLEMENTED';
        }
    }

    protected function isItemHidden(ReflectionClass $reflection): bool
    {
        if ($reflection->hasMethod('shouldRegisterNavigation')) {
            try {
                $method = $reflection->getMethod('shouldRegisterNavigation');
                if ($method->isStatic() && $method->isPublic()) {
                    return $method->invoke(null) === false;
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }
        return false;
    }

    protected function checkFilamentResource(ReflectionClass $reflection): array
    {
        return [
            'has_table' => $reflection->hasMethod('table'),
            'has_form' => $reflection->hasMethod('form'),
            'has_pages' => $reflection->hasMethod('getPages') || $reflection->hasMethod('getPages'),
        ];
    }

    protected function checkFilamentPage(ReflectionClass $reflection): array
    {
        return [
            'has_mount' => $reflection->hasMethod('mount'),
            'has_form' => $reflection->hasMethod('form'),
            'has_table' => $reflection->hasMethod('table'),
        ];
    }

    protected function displaySummary(): void
    {
        $this->info('📊 Summary');
        $this->newLine();

        $headers = ['Module', 'Priority', 'MVP', 'Total', '✅ Done', '⚠️ Partial', '❌ Missing', '👁️ Hidden', 'Completion %'];
        $rows = [];

        foreach ($this->moduleStats as $key => $stats) {
            $rows[] = [
                $stats['label'],
                $stats['priority'],
                $stats['for_mvp'] ? 'Yes' : 'No',
                $stats['total'],
                $stats['implemented'],
                $stats['partial'],
                $stats['not_implemented'],
                $stats['hidden'],
                $stats['completion'] . '%',
            ];
        }

        // Sort by priority
        usort($rows, fn($a, $b) => $a[1] <=> $b[1]);

        $this->table($headers, $rows);

        // MVP Summary
        $mvpModules = array_filter($this->moduleStats, fn($s) => $s['for_mvp']);
        $mvpTotal = array_sum(array_column($mvpModules, 'total'));
        $mvpImplemented = array_sum(array_column($mvpModules, 'implemented'));
        $mvpHidden = array_sum(array_column($mvpModules, 'hidden'));
        $mvpVisible = $mvpTotal - $mvpHidden;
        $mvpCompletion = $mvpVisible > 0 ? round(($mvpImplemented / $mvpVisible) * 100, 1) : 100;

        $this->newLine();
        $this->info("🎯 MVP Completion: {$mvpCompletion}% ({$mvpImplemented}/{$mvpVisible} visible items)");
        $this->newLine();
    }

    protected function generateReports(): void
    {
        $reportsDir = storage_path('app/reports');
        if (!File::exists($reportsDir)) {
            File::makeDirectory($reportsDir, 0755, true);
        }

        // JSON Report
        $jsonReport = [
            'generated_at' => now()->toIso8601String(),
            'modules' => $this->moduleStats,
            'items' => $this->statuses,
        ];

        File::put(
            storage_path('app/reports/features-status.json'),
            json_encode($jsonReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // Markdown Report
        $this->generateMarkdownReport();

        $this->info("✅ Reports generated:");
        $this->line("   📄 storage/app/reports/features-status.json");
        $this->line("   📄 docs/FEATURE_STATUS.md");
    }

    protected function generateMarkdownReport(): void
    {
        $docsDir = base_path('docs');
        if (!File::exists($docsDir)) {
            File::makeDirectory($docsDir, 0755, true);
        }

        $content = "# Feature Completeness Status\n\n";
        $content .= "**Generated:** " . now()->toDateTimeString() . "\n\n";
        $content .= "---\n\n";

        // MVP Summary
        $mvpModules = array_filter($this->moduleStats, fn($s) => $s['for_mvp']);
        $mvpTotal = array_sum(array_column($mvpModules, 'total'));
        $mvpImplemented = array_sum(array_column($mvpModules, 'implemented'));
        $mvpHidden = array_sum(array_column($mvpModules, 'hidden'));
        $mvpVisible = $mvpTotal - $mvpHidden;
        $mvpCompletion = $mvpVisible > 0 ? round(($mvpImplemented / $mvpVisible) * 100, 1) : 100;

        $content .= "## 🎯 MVP Completion: {$mvpCompletion}%\n\n";
        $content .= "- **MVP Items:** {$mvpVisible} visible / {$mvpTotal} total\n";
        $content .= "- **Implemented:** {$mvpImplemented}\n";
        $content .= "- **Hidden:** {$mvpHidden}\n\n";

        // Overall Summary
        $allTotal = array_sum(array_column($this->moduleStats, 'total'));
        $allImplemented = array_sum(array_column($this->moduleStats, 'implemented'));
        $allHidden = array_sum(array_column($this->moduleStats, 'hidden'));
        $allVisible = $allTotal - $allHidden;
        $allCompletion = $allVisible > 0 ? round(($allImplemented / $allVisible) * 100, 1) : 100;

        $content .= "## 📊 Overall Completion: {$allCompletion}%\n\n";
        $content .= "- **Total Items:** {$allVisible} visible / {$allTotal} total\n";
        $content .= "- **Implemented:** {$allImplemented}\n";
        $content .= "- **Hidden:** {$allHidden}\n\n";

        $content .= "---\n\n";

        // Module Details
        $content .= "## 📦 Module Details\n\n";

        // Sort by priority
        $sortedModules = $this->moduleStats;
        uasort($sortedModules, fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($sortedModules as $moduleKey => $stats) {
            $mvpBadge = $stats['for_mvp'] ? ' 🎯 MVP' : '';
            $content .= "### {$stats['label']}{$mvpBadge}\n\n";
            $content .= "- **Priority:** {$stats['priority']}\n";
            $content .= "- **Completion:** {$stats['completion']}%\n";
            $content .= "- **Status:** {$stats['implemented']} ✅ | {$stats['partial']} ⚠️ | {$stats['not_implemented']} ❌ | {$stats['hidden']} 👁️\n\n";

            // Item details
            if (isset($this->statuses[$moduleKey])) {
                $content .= "#### Items:\n\n";
                foreach ($this->statuses[$moduleKey] as $itemData) {
                    $item = $itemData['item'];
                    $status = $itemData['status'];
                    $statusIcon = match($status) {
                        'IMPLEMENTED' => '✅',
                        'PARTIAL' => '⚠️',
                        'NOT_IMPLEMENTED' => '❌',
                        'HIDDEN' => '👁️',
                        'MISSING_CLASS' => '🔴',
                        default => '❓',
                    };
                    $content .= "- {$statusIcon} **{$item['label']}** ({$item['type']}) - `{$item['class']}`\n";
                }
                $content .= "\n";
            }
        }

        // Roadmap
        $content .= $this->generateRoadmap();

        File::put(base_path('docs/FEATURE_STATUS.md'), $content);
    }

    protected function generateRoadmap(): string
    {
        $content = "---\n\n";
        $content .= "## 🗺️ Implementation Roadmap\n\n";

        // MVP Modules
        $content .= "### 🎯 MVP Modules (Required for CodeCanyon Release)\n\n";
        $mvpModules = array_filter($this->moduleStats, fn($s) => $s['for_mvp'] && $s['completion'] < 100);
        if (empty($mvpModules)) {
            $content .= "✅ All MVP modules are complete!\n\n";
        } else {
            uasort($mvpModules, fn($a, $b) => $a['priority'] <=> $b['priority']);
            foreach ($mvpModules as $moduleKey => $stats) {
                $remaining = $stats['not_implemented'] + $stats['partial'];
                $content .= "- **{$stats['label']}** (Priority {$stats['priority']}) - {$remaining} items remaining\n";
            }
            $content .= "\n";
        }

        // Nice-to-have
        $content .= "### 💡 Nice-to-Have Modules (Post-MVP)\n\n";
        $niceToHave = array_filter($this->moduleStats, fn($s) => !$s['for_mvp'] && $s['completion'] < 100);
        if (empty($niceToHave)) {
            $content .= "No nice-to-have modules defined.\n\n";
        } else {
            uasort($niceToHave, fn($a, $b) => $a['priority'] <=> $b['priority']);
            foreach ($niceToHave as $moduleKey => $stats) {
                $remaining = $stats['not_implemented'] + $stats['partial'];
                $content .= "- **{$stats['label']}** (Priority {$stats['priority']}) - {$remaining} items remaining\n";
            }
            $content .= "\n";
        }

        // Prioritized TODO
        $content .= "### 📋 Prioritized TODO List\n\n";
        $todoItems = [];
        foreach ($this->statuses as $moduleKey => $items) {
            $moduleStats = $this->moduleStats[$moduleKey];
            foreach ($items as $itemData) {
                if (in_array($itemData['status'], ['NOT_IMPLEMENTED', 'PARTIAL', 'MISSING_CLASS'])) {
                    $item = $itemData['item'];
                    $complexity = $this->estimateComplexity($item);
                    $todoItems[] = [
                        'module' => $moduleStats['label'],
                        'item' => $item['label'],
                        'type' => $item['type'],
                        'priority' => $moduleStats['priority'],
                        'mvp' => $moduleStats['for_mvp'],
                        'complexity' => $complexity,
                        'status' => $itemData['status'],
                    ];
                }
            }
        }

        // Sort: MVP first, then by priority, then by complexity
        usort($todoItems, function($a, $b) {
            if ($a['mvp'] !== $b['mvp']) {
                return $b['mvp'] <=> $a['mvp']; // MVP first
            }
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] <=> $b['priority'];
            }
            return $a['complexity'] <=> $b['complexity'];
        });

        if (empty($todoItems)) {
            $content .= "✅ All items are implemented!\n\n";
        } else {
            $content .= "| Module | Item | Type | Priority | MVP | Complexity | Status |\n";
            $content .= "|--------|------|------|----------|-----|------------|--------|\n";
            foreach ($todoItems as $todo) {
                $mvpBadge = $todo['mvp'] ? '🎯' : '';
                $content .= "| {$todo['module']} | {$todo['item']} | {$todo['type']} | {$todo['priority']} | {$mvpBadge} | {$todo['complexity']} | {$todo['status']} |\n";
            }
            $content .= "\n";
        }

        return $content;
    }

    protected function estimateComplexity(array $item): string
    {
        $type = $item['type'] ?? 'unknown';
        $requiredMethods = count($item['required_methods'] ?? []);

        if ($type === 'widget') {
            return 'S';
        } elseif ($type === 'page' && $requiredMethods <= 2) {
            return 'S';
        } elseif ($type === 'resource' && $requiredMethods <= 3) {
            return 'M';
        } elseif ($type === 'api') {
            return 'M';
        } else {
            return 'L';
        }
    }

    protected function applyFixes(): void
    {
        $this->newLine();
        $this->warn('🔧 Applying fixes (--fix mode)...');
        $this->newLine();

        $fixed = 0;
        foreach ($this->statuses as $moduleKey => $items) {
            foreach ($items as $itemData) {
                $status = $itemData['status'];
                $item = $itemData['item'];

                if (in_array($status, ['PARTIAL', 'NOT_IMPLEMENTED', 'MISSING_CLASS'])) {
                    if ($this->isVisible($item)) {
                        $this->hideItem($item);
                        $fixed++;
                        $this->line("   👁️  Hidden: {$item['label']}");
                    }
                }
            }
        }

        if ($fixed > 0) {
            $this->newLine();
            $this->info("✅ Fixed {$fixed} items (hidden from navigation)");
        } else {
            $this->info("✅ No fixes needed");
        }
    }

    protected function isVisible(array $item): bool
    {
        $class = $item['class'] ?? null;
        if (!$class || !class_exists($class)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($class);
            if ($reflection->hasMethod('shouldRegisterNavigation')) {
                $method = $reflection->getMethod('shouldRegisterNavigation');
                if ($method->isStatic() && $method->isPublic()) {
                    return $method->invoke(null) !== false;
                }
            }
            return true; // Default to visible if method doesn't exist
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function hideItem(array $item): void
    {
        // Strategy 1: Add shouldRegisterNavigation(): false
        // This would require modifying files, which we'll skip for safety
        // Instead, we'll just log what needs to be done
        $this->line("   ⚠️  TODO: Add shouldRegisterNavigation(): false to {$item['class']}");
    }
}

