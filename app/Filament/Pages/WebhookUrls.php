<?php

namespace App\Filament\Pages;

use App\Core\Providers\ProviderRegistry;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class WebhookUrls extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static string $view = 'filament.pages.webhook-urls';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Webhook URLs';
    protected static ?int $navigationSort = 11;
    protected static ?string $slug = 'system/webhook-urls';

    public array $webhookProviders = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('system.tools.view'), 403);
        $this->loadWebhookProviders();
    }

    protected function loadWebhookProviders(): void
    {
        $allProviders = ProviderRegistry::all();
        $webhookProviders = [];

        foreach ($allProviders as $provider) {
            // Only show providers that support webhooks (payments, shipping)
            if (in_array($provider['category'], ['payment', 'shipping'])) {
                $webhookUrl = route('api.webhooks', ['provider' => $provider['key']], true);
                
                // Get last webhook log
                $lastWebhook = DB::table('webhook_logs')
                    ->where('provider', $provider['key'])
                    ->orderBy('created_at', 'desc')
                    ->first();

                $webhookProviders[] = [
                    'key' => $provider['key'],
                    'name' => $provider['display_name'],
                    'category' => $provider['category'],
                    'url' => $webhookUrl,
                    'last_received' => $lastWebhook?->created_at,
                    'last_status' => $lastWebhook?->status ?? 'never',
                    'status_ok' => $lastWebhook && 
                        $lastWebhook->status === 'processed' && 
                        now()->diffInDays($lastWebhook->created_at) < 7, // OK if received in last 7 days
                ];
            }
        }

        $this->webhookProviders = $webhookProviders;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('system.tools.view') ?? false;
    }
}
