<?php

namespace App\Console\Commands;

use App\Core\Providers\ProviderRegistry;
use App\Models\Provider;
use Illuminate\Console\Command;

class SyncProvidersCommand extends Command
{
    protected $signature = 'providers:sync';
    protected $description = 'Sync providers from ProviderRegistry to database';

    public function handle(): int
    {
        $this->info('Syncing providers from ProviderRegistry...');

        $registryProviders = ProviderRegistry::all();
        $synced = 0;
        $disabled = 0;

        foreach ($registryProviders as $registryProvider) {
            $provider = Provider::updateOrCreate(
                [
                    'type' => $registryProvider['category'],
                    'name' => $registryProvider['key'],
                ],
                [
                    'label' => $registryProvider['display_name'],
                    'description' => $registryProvider['description'] ?? null,
                    'driver_class' => $this->getDriverClass($registryProvider['category'], $registryProvider['key']),
                    'is_enabled' => true, // Enable by default
                    'environment' => 'sandbox', // Default environment
                    'config_schema' => $registryProvider['credential_schema'] ?? [],
                    'metadata' => [
                        'supports_env' => $registryProvider['supports_env'] ?? ['sandbox', 'live'],
                        'test_action' => $registryProvider['test_action'] ?? null,
                    ],
                    'priority' => $this->getDefaultPriority($registryProvider['category']),
                ]
            );

            $synced++;
            $this->line("  ✓ Synced: {$registryProvider['display_name']} ({$registryProvider['category']})");
        }

        // Optionally disable providers not in registry (safer: keep but mark as not from registry)
        $registryKeys = array_column($registryProviders, 'key');
        $dbProviders = Provider::whereNotIn('name', $registryKeys)->get();
        
        foreach ($dbProviders as $dbProvider) {
            // Mark as not from registry but don't disable (safer)
            $dbProvider->update([
                'metadata' => array_merge($dbProvider->metadata ?? [], ['from_registry' => false]),
            ]);
            $disabled++;
        }

        $this->info("✅ Synced {$synced} providers from registry");
        if ($disabled > 0) {
            $this->warn("⚠️  Found {$disabled} providers not in registry (marked but not disabled)");
        }

        return Command::SUCCESS;
    }

    protected function getDriverClass(string $category, string $key): string
    {
        return match($category) {
            'payment' => "App\\Core\\Providers\\Payment\\" . $this->getProviderClassName($key),
            'shipping' => "App\\Core\\Providers\\Shipping\\" . $this->getProviderClassName($key),
            'sms' => "App\\Core\\Providers\\Sms\\" . $this->getProviderClassName($key),
            'whatsapp' => "App\\Core\\Providers\\WhatsApp\\" . $this->getProviderClassName($key),
            'push' => "App\\Core\\Services\\FcmHttpV1Client", // Special case for FCM
            'auth' => "App\\Core\\Providers\\Auth\\" . $this->getProviderClassName($key),
            'storage' => "App\\Core\\Providers\\Storage\\" . $this->getProviderClassName($key),
            default => "App\\Core\\Providers\\" . $this->getProviderClassName($key),
        };
    }

    protected function getProviderClassName(string $key): string
    {
        // Convert snake_case to PascalCase
        $parts = explode('_', $key);
        $className = implode('', array_map('ucfirst', $parts)) . 'Provider';
        return $className;
    }

    protected function getDefaultPriority(string $category): int
    {
        return match($category) {
            'payment' => 10,
            'shipping' => 10,
            'email' => 10,
            'sms' => 20,
            'whatsapp' => 20,
            'push' => 20,
            'auth' => 30,
            'storage' => 40,
            default => 0,
        };
    }
}
