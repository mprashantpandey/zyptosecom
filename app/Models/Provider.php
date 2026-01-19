<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Provider extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'driver_class',
        'label',
        'description',
        'is_enabled',
        'environment',
        'config_schema',
        'metadata',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'config_schema' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Check if provider has credentials configured
     */
    public function hasCredentials(): bool
    {
        return DB::table('secrets')
            ->where('provider_type', $this->type)
            ->where('provider_name', $this->name)
            ->where('environment', $this->environment)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get last test status: 'success', 'failed', or 'never'
     */
    public function lastTestStatus(): string
    {
        $lastTest = $this->integrationTests()
            ->latest('tested_at')
            ->first();

        if (!$lastTest) {
            return 'never';
        }

        return $lastTest->status;
    }

    /**
     * Get last tested timestamp
     */
    public function lastTestedAt(): ?\Illuminate\Support\Carbon
    {
        $lastTest = $this->integrationTests()
            ->latest('tested_at')
            ->first();

        return $lastTest?->tested_at;
    }

    /**
     * Check if provider supports webhooks
     */
    public function supportsWebhooks(): bool
    {
        // Payment providers typically support webhooks
        return in_array($this->type, ['payment']);
    }

    /**
     * Check if provider supports environment switching
     */
    public function supportsEnvironment(): bool
    {
        $registry = \App\Core\Providers\ProviderRegistry::get($this->name);
        if (!$registry) {
            return false;
        }

        return !empty($registry['supports_env'] ?? []);
    }

    /**
     * Get category icon based on provider type
     */
    public function getCategoryIcon(): string
    {
        return match($this->type) {
            'payment' => 'heroicon-o-credit-card',
            'shipping' => 'heroicon-o-truck',
            'email' => 'heroicon-o-envelope',
            'sms' => 'heroicon-o-chat-bubble-left-right',
            'whatsapp' => 'heroicon-o-chat-bubble-oval-left-ellipsis',
            'push' => 'heroicon-o-bell',
            'auth' => 'heroicon-o-shield-check',
            'storage' => 'heroicon-o-server',
            default => 'heroicon-o-puzzle-piece',
        };
    }

    /**
     * Get category label
     */
    public function getCategoryLabel(): string
    {
        return match($this->type) {
            'payment' => 'Payments',
            'shipping' => 'Shipping',
            'email' => 'Email',
            'sms' => 'SMS',
            'whatsapp' => 'WhatsApp',
            'push' => 'Push Notifications',
            'auth' => 'Authentication',
            'storage' => 'Storage',
            default => ucfirst($this->type),
        };
    }

    /**
     * Get integration status: 'not_configured', 'configured', 'active', 'needs_attention'
     */
    public function getStatus(): string
    {
        if (!$this->hasCredentials()) {
            return 'not_configured';
        }

        $lastTest = $this->lastTestStatus();
        
        if ($lastTest === 'never') {
            return 'configured';
        }

        if ($lastTest === 'failed') {
            return 'needs_attention';
        }

        if ($this->is_enabled && $lastTest === 'success') {
            return 'active';
        }

        return 'configured';
    }

    /**
     * Get status label for display
     */
    public function getStatusLabel(): string
    {
        return match($this->getStatus()) {
            'not_configured' => 'Not Configured',
            'configured' => 'Configured',
            'active' => 'Active',
            'needs_attention' => 'Needs Attention',
            default => 'Unknown',
        };
    }

    /**
     * Integration tests relationship
     */
    public function integrationTests(): HasMany
    {
        return $this->hasMany(IntegrationTest::class);
    }
}

