<?php

namespace App\Filament\Pages;

use App\Core\Services\AuditService;
use App\Core\Services\NotificationService;
use App\Core\Services\SecretsService;
use App\Models\NotificationProvider;
use App\Models\Provider;
use App\Models\Secret;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class NotificationProviders extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static string $view = 'filament.pages.notification-providers';
    protected static ?string $navigationGroup = 'Notifications';
    protected static ?string $navigationLabel = 'Notification Providers';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'notifications/providers';

    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('notifications.view'), 403);
        $this->loadProviders();
    }

    protected function loadProviders(): void
    {
        $channels = ['email', 'sms', 'push', 'whatsapp'];
        
        foreach ($channels as $channel) {
            $provider = NotificationProvider::where('channel', $channel)->first();
            
            if ($provider) {
                $this->data["{$channel}_enabled"] = $provider->is_enabled;
                $this->data["{$channel}_provider_key"] = $provider->provider_key;
                $this->data["{$channel}_environment"] = $provider->environment;
                $this->data["{$channel}_secret_id"] = $provider->secret_id;
                $this->data["{$channel}_from_name"] = $provider->config['from_name'] ?? '';
                $this->data["{$channel}_sender_id"] = $provider->config['sender_id'] ?? '';
            } else {
                $this->data["{$channel}_enabled"] = false;
                $this->data["{$channel}_provider_key"] = '';
                $this->data["{$channel}_environment"] = 'sandbox';
                $this->data["{$channel}_secret_id"] = null;
                $this->data["{$channel}_from_name"] = '';
                $this->data["{$channel}_sender_id"] = '';
            }
        }
        
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Channels')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Email')
                            ->icon('heroicon-o-envelope')
                            ->schema($this->getChannelSchema('email')),
                        Forms\Components\Tabs\Tab::make('SMS')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema($this->getChannelSchema('sms')),
                        Forms\Components\Tabs\Tab::make('Push')
                            ->icon('heroicon-o-device-phone-mobile')
                            ->schema($this->getChannelSchema('push')),
                        Forms\Components\Tabs\Tab::make('WhatsApp')
                            ->icon('heroicon-o-chat-bubble-oval-left-ellipsis')
                            ->schema($this->getChannelSchema('whatsapp')),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getChannelSchema(string $channel): array
    {
        $providerOptions = $this->getProviderOptions($channel);
        
        return [
            Forms\Components\Section::make("{$channel} Configuration")
                ->schema([
                    Forms\Components\Toggle::make("{$channel}_enabled")
                        ->label('Enable ' . ucfirst($channel))
                        ->helperText("Enable {$channel} notifications"),
                    Forms\Components\Select::make("{$channel}_provider_key")
                        ->label('Provider')
                        ->options($providerOptions)
                        ->required(fn ($get) => $get("{$channel}_enabled"))
                        ->visible(fn ($get) => $get("{$channel}_enabled"))
                        ->live()
                        ->helperText("Select the provider for {$channel} notifications"),
                    Forms\Components\Select::make("{$channel}_environment")
                        ->label('Environment')
                        ->options([
                            'sandbox' => 'Sandbox/Test',
                            'production' => 'Production',
                        ])
                        ->required(fn ($get) => $get("{$channel}_enabled"))
                        ->visible(fn ($get) => $get("{$channel}_enabled"))
                        ->default('sandbox'),
                    Forms\Components\Select::make("{$channel}_secret_id")
                        ->label('Credentials')
                        ->options(fn () => $this->getSecretOptions($channel))
                        ->searchable()
                        ->visible(fn ($get) => $get("{$channel}_enabled"))
                        ->helperText("Select credentials for this provider"),
                    Forms\Components\TextInput::make("{$channel}_from_name")
                        ->label('From Name')
                        ->maxLength(255)
                        ->visible(fn ($get) => $get("{$channel}_enabled") && $channel === 'email')
                        ->helperText('Sender name for emails'),
                    Forms\Components\TextInput::make("{$channel}_sender_id")
                        ->label('Sender ID')
                        ->maxLength(20)
                        ->visible(fn ($get) => $get("{$channel}_enabled") && in_array($channel, ['sms', 'whatsapp']))
                        ->helperText('Sender ID for SMS/WhatsApp'),
                ])
                ->columns(2),
            Forms\Components\Section::make('Test')
                ->schema([
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make("test_{$channel}")
                            ->label("Send Test {$channel}")
                            ->icon('heroicon-o-paper-airplane')
                            ->color('info')
                            ->form([
                                Forms\Components\TextInput::make('recipient')
                                    ->label('Recipient')
                                    ->required()
                                    ->helperText(fn () => match($channel) {
                                        'email' => 'Email address',
                                        'sms', 'whatsapp' => 'Phone number',
                                        'push' => 'FCM device token (from Flutter app)',
                                        default => 'Recipient',
                                    }),
                                Forms\Components\TextInput::make('title')
                                    ->label('Title')
                                    ->maxLength(100)
                                    ->default('Test Notification')
                                    ->visible(fn () => $channel === 'push')
                                    ->helperText('Notification title'),
                                Forms\Components\Textarea::make('body')
                                    ->label('Body')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->default('This is a test push notification')
                                    ->visible(fn () => $channel === 'push')
                                    ->helperText('Notification body'),
                            ])
                            ->action(function (array $testData) use ($channel) {
                                abort_unless(auth()->user()->can('notifications.test'), 403);
                                
                                try {
                                    $notificationService = app(NotificationService::class);
                                    $log = $notificationService->send(
                                        'test',
                                        $channel,
                                        $testData['recipient'],
                                        ['test' => true]
                                    );
                                    
                                    if ($log->status === 'sent') {
                                        Notification::make()
                                            ->title("Test {$channel} sent successfully")
                                            ->body("Check notification logs for details")
                                            ->success()
                                            ->send();
                                    } else {
                                        Notification::make()
                                            ->title("Test {$channel} failed")
                                            ->body($log->error_message ?? 'Unknown error')
                                            ->danger()
                                            ->send();
                                    }
                                    
                                    AuditService::log('notifications.test_sent', null, [], [
                                        'channel' => $channel,
                                        'recipient' => $testData['recipient'],
                                    ], ['module' => 'notifications']);
                                    
                                } catch (\Exception $e) {
                                    Notification::make()
                                        ->title('Test failed')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            })
                            ->visible(fn ($get) => $get("{$channel}_enabled")),
                    ]),
                ])
                ->visible(fn ($get) => $get("{$channel}_enabled")),
        ];
    }

    protected function getProviderOptions(string $channel): array
    {
        return match($channel) {
            'email' => [
                'smtp' => 'SMTP',
                'sendgrid' => 'SendGrid',
                'mailgun' => 'Mailgun',
            ],
            'sms' => [
                'twilio' => 'Twilio',
                'msg91' => 'MSG91',
                'mock' => 'Mock (Demo)',
            ],
            'push' => [
                'firebase_fcm_v1' => 'Firebase FCM (HTTP v1)',
                'mock' => 'Mock (Demo)',
            ],
            'whatsapp' => [
                'gupshup' => 'Gupshup',
                'wati' => 'Wati',
                'interakt' => 'Interakt',
                'mock' => 'Mock (Demo)',
            ],
            default => [],
        };
    }

    protected function getSecretOptions(string $channel): array
    {
        // Get secrets for notification providers
        // Filter by provider_name that matches the channel's available providers
        $channelProviders = match($channel) {
            'email' => ['smtp', 'sendgrid', 'mailgun'],
            'sms' => ['twilio', 'msg91'],
            'push' => ['firebase_fcm_v1'],
            'whatsapp' => ['gupshup', 'wati', 'interakt'],
            default => [],
        };
        
        if (empty($channelProviders)) {
            return [];
        }
        
        // Get unique provider_name + environment combinations
        $secrets = Secret::where('provider_type', 'notification')
            ->whereIn('provider_name', $channelProviders)
            ->where('is_active', true)
            ->select('provider_name', 'environment')
            ->distinct()
            ->get();
        
        $options = [];
        foreach ($secrets as $secret) {
            // Create a composite key for the option
            // Since Secret model stores individual keys, we'll use provider_name + environment as identifier
            $key = "{$secret->provider_name}:{$secret->environment}";
            $label = ucfirst($secret->provider_name);
            if ($secret->environment) {
                $label .= " ({$secret->environment})";
            }
            $options[$key] = $label;
        }
        
        return $options;
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('notifications.edit'), 403);

        $data = $this->form->getState();
        $channels = ['email', 'sms', 'push', 'whatsapp'];

        DB::transaction(function () use ($data, $channels) {
            foreach ($channels as $channel) {
                $enabled = $data["{$channel}_enabled"] ?? false;
                
                if ($enabled) {
                    $provider = NotificationProvider::updateOrCreate(
                        ['channel' => $channel],
                        [
                            'provider_key' => $data["{$channel}_provider_key"] ?? '',
                            'name' => ucfirst($channel) . ' Provider',
                            'is_enabled' => true,
                            'environment' => $data["{$channel}_environment"] ?? 'sandbox',
                            'secret_id' => $data["{$channel}_secret_id"] ?? null,
                            'config' => [
                                'from_name' => $data["{$channel}_from_name"] ?? '',
                                'sender_id' => $data["{$channel}_sender_id"] ?? '',
                            ],
                        ]
                    );
                    
                    AuditService::log('notifications.provider_updated', $provider, [], [
                        'channel' => $channel,
                        'provider_key' => $provider->provider_key,
                    ], ['module' => 'notifications']);
                } else {
                    NotificationProvider::where('channel', $channel)->update(['is_enabled' => false]);
                }
            }
        });

        Notification::make()
            ->title('Providers saved successfully')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Save Providers')
                ->submit('save')
                ->color('primary'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('notifications.view') ?? false;
    }
}
