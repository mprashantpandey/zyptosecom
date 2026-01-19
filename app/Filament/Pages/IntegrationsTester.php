<?php

namespace App\Filament\Pages;

use App\Core\Providers\ProviderRegistry;
use App\Core\Services\AuditService;
use App\Core\Services\SecretsService;
use App\Models\Secret;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

class IntegrationsTester extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';
    protected static string $view = 'filament.pages.integrations-tester';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Integrations Tester';
    protected static ?int $navigationSort = 10;
    protected static ?string $slug = 'system/integrations-tester';

    public ?array $data = [];
    public ?string $selectedCategory = null;
    public ?string $selectedProvider = null;
    public ?int $selectedCredentialId = null;
    public ?array $testResult = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('system.tools.view'), 403);
    }

    public function form(Form $form): Form
    {
        $categories = ['payment', 'shipping', 'email', 'sms', 'whatsapp', 'push', 'auth', 'storage'];
        $providers = $this->selectedCategory 
            ? ProviderRegistry::getByCategory($this->selectedCategory)
            : [];

        $providerOptions = [];
        foreach ($providers as $provider) {
            $providerOptions[$provider['key']] = $provider['display_name'];
        }

        $testAction = null;
        if ($this->selectedProvider) {
            $providerSchema = ProviderRegistry::get($this->selectedProvider);
            $testAction = $providerSchema['test_action'] ?? null;
        }

        $testInputs = [];
        if ($testAction) {
            foreach ($testAction['inputs'] ?? [] as $key => $type) {
                $testInputs[] = match($type) {
                    'email' => Forms\Components\TextInput::make("test_inputs.{$key}")
                        ->label(ucfirst(str_replace('_', ' ', $key)))
                        ->email()
                        ->required(),
                    'phone' => Forms\Components\TextInput::make("test_inputs.{$key}")
                        ->label(ucfirst(str_replace('_', ' ', $key)))
                        ->tel()
                        ->required(),
                    'number' => Forms\Components\TextInput::make("test_inputs.{$key}")
                        ->label(ucfirst(str_replace('_', ' ', $key)))
                        ->numeric()
                        ->required(),
                    'text' => Forms\Components\TextInput::make("test_inputs.{$key}")
                        ->label(ucfirst(str_replace('_', ' ', $key)))
                        ->required(),
                    default => Forms\Components\TextInput::make("test_inputs.{$key}")
                        ->label(ucfirst(str_replace('_', ' ', $key)))
                        ->required(),
                };
            }
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Select Integration')
                    ->schema([
                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->options(array_combine($categories, array_map('ucfirst', $categories)))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->selectedCategory = $state;
                                $this->selectedProvider = null;
                                $this->selectedCredentialId = null;
                            }),
                        Forms\Components\Select::make('provider')
                            ->label('Provider')
                            ->options($providerOptions)
                            ->required()
                            ->live()
                            ->visible(fn () => !empty($providerOptions))
                            ->afterStateUpdated(function ($state) {
                                $this->selectedProvider = $state;
                                $this->selectedCredentialId = null;
                            }),
                        Forms\Components\Select::make('credential_id')
                            ->label('Credential Record')
                            ->options(function () {
                                if (!$this->selectedProvider) {
                                    return [];
                                }
                                return Secret::where('provider_name', $this->selectedProvider)
                                    ->where('is_active', true)
                                    ->get()
                                    ->mapWithKeys(fn ($secret) => [
                                        $secret->id => "{$secret->provider_type} / {$secret->environment}",
                                    ])
                                    ->toArray();
                            })
                            ->required()
                            ->visible(fn () => $this->selectedProvider)
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->selectedCredentialId = $state;
                            }),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Test Inputs')
                    ->schema($testInputs)
                    ->visible(fn () => !empty($testInputs)),

                Forms\Components\Section::make('Test Result')
                    ->schema([
                        Forms\Components\Placeholder::make('result')
                            ->label('Result')
                            ->content(fn () => $this->formatTestResult($this->testResult))
                            ->visible(fn () => $this->testResult !== null),
                    ])
                    ->visible(fn () => $this->testResult !== null),
            ])
            ->statePath('data');
    }

    public function runTest(): void
    {
        abort_unless(auth()->user()->can('integrations.test'), 403);

        $data = $this->form->getState();
        $category = $data['category'] ?? null;
        $providerKey = $data['provider'] ?? null;
        $credentialId = $data['credential_id'] ?? null;
        $testInputs = $data['test_inputs'] ?? [];

        if (!$category || !$providerKey || !$credentialId) {
            Notification::make()
                ->title('Missing required fields')
                ->body('Please select category, provider, and credential record')
                ->danger()
                ->send();
            return;
        }

        try {
            $credential = Secret::findOrFail($credentialId);
            $secretsService = app(SecretsService::class);
            $providerSchema = ProviderRegistry::get($providerKey);

            if (!$providerSchema) {
                throw new \Exception('Provider schema not found');
            }

            $result = $this->executeTest($category, $providerKey, $credential, $testInputs, $secretsService);
            
            $this->testResult = [
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? '',
                'data' => $result['data'] ?? [],
            ];

            Notification::make()
                ->title($result['success'] ? 'Test successful' : 'Test failed')
                ->body($result['message'] ?? '')
                ->color($result['success'] ? 'success' : 'danger')
                ->send();

            // Audit log
            AuditService::log('integrations.test_ran', null, [], [
                'category' => $category,
                'provider' => $providerKey,
                'success' => $result['success'],
                'message' => $result['message'],
            ], ['module' => 'integrations']);

        } catch (\Exception $e) {
            $this->testResult = [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ];

            Notification::make()
                ->title('Test failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            Log::error('Integration test failed', [
                'category' => $category,
                'provider' => $providerKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function executeTest(string $category, string $providerKey, Secret $credential, array $testInputs, SecretsService $secretsService): array
    {
        return match($category) {
            'payment' => $this->testPaymentProvider($providerKey, $credential, $testInputs, $secretsService),
            'shipping' => $this->testShippingProvider($providerKey, $credential, $testInputs, $secretsService),
            'email' => $this->testEmailProvider($providerKey, $credential, $testInputs, $secretsService),
            'sms' => $this->testSmsProvider($providerKey, $credential, $testInputs, $secretsService),
            'whatsapp' => $this->testWhatsAppProvider($providerKey, $credential, $testInputs, $secretsService),
            'push' => $this->testPushProvider($providerKey, $credential, $testInputs, $secretsService),
            default => ['success' => false, 'message' => 'Category not supported for testing'],
        };
    }

    protected function testPaymentProvider(string $providerKey, Secret $credential, array $testInputs, SecretsService $secretsService): array
    {
        $amount = $testInputs['amount'] ?? 100;
        
        try {
            $provider = match($providerKey) {
                'razorpay' => new \App\Core\Providers\Payment\RazorpayProvider($secretsService, $credential->environment),
                'stripe' => new \App\Core\Providers\Payment\StripeProvider($secretsService, $credential->environment),
                'cashfree' => new \App\Core\Providers\Payment\CashfreeProvider($secretsService, $credential->environment),
                default => null,
            };

            if (!$provider) {
                return ['success' => false, 'message' => 'Provider not implemented'];
            }

            // Test by fetching status or creating a test order
            $testOrderId = 'TEST_' . time();
            $result = $provider->fetchStatus($testOrderId);
            
            return [
                'success' => true,
                'message' => 'Provider connection successful',
                'data' => ['status' => 'connected'],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function testShippingProvider(string $providerKey, Secret $credential, array $testInputs, SecretsService $secretsService): array
    {
        try {
            if ($providerKey === 'shiprocket') {
                $provider = new \App\Core\Providers\Shipping\ShiprocketProvider($secretsService, $credential->environment);
                $pincode = $testInputs['pincode'] ?? '110001';
                $result = $provider->checkServiceability($pincode);
                
                return [
                    'success' => $result['serviceable'] ?? false,
                    'message' => $result['serviceable'] ? 'Pincode is serviceable' : 'Pincode not serviceable',
                    'data' => $result,
                ];
            }

            return ['success' => false, 'message' => 'Provider not implemented'];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function testEmailProvider(string $providerKey, Secret $credential, array $testInputs, SecretsService $secretsService): array
    {
        $recipient = $testInputs['recipient'] ?? null;
        
        if (!$recipient) {
            return ['success' => false, 'message' => 'Recipient email required'];
        }

        try {
            // Use NotificationService to send test email
            $notificationService = app(\App\Core\Services\NotificationService::class);
            $log = $notificationService->send(
                'welcome',
                'email',
                $recipient,
                ['test' => true],
                ['is_critical' => true]
            );

            return [
                'success' => $log->status === 'sent',
                'message' => $log->status === 'sent' ? 'Test email sent successfully' : 'Failed to send email',
                'data' => ['log_id' => $log->id],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function testSmsProvider(string $providerKey, Secret $credential, array $testInputs, SecretsService $secretsService): array
    {
        $recipient = $testInputs['recipient'] ?? null;
        
        if (!$recipient) {
            return ['success' => false, 'message' => 'Recipient phone number required'];
        }

        try {
            if ($providerKey === 'msg91') {
                $provider = new \App\Core\Providers\Sms\Msg91Provider($secretsService, $credential->environment);
                $result = $provider->sendSms($recipient, 'Test SMS from Integrations Tester');
                
                return [
                    'success' => $result['status'] === 'sent',
                    'message' => $result['message'] ?? 'SMS sent',
                    'data' => $result,
                ];
            }

            return ['success' => false, 'message' => 'Provider not implemented'];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function testWhatsAppProvider(string $providerKey, Secret $credential, array $testInputs, SecretsService $secretsService): array
    {
        $recipient = $testInputs['recipient'] ?? null;
        
        if (!$recipient) {
            return ['success' => false, 'message' => 'Recipient phone number required'];
        }

        try {
            if ($providerKey === 'interakt') {
                $provider = new \App\Core\Providers\WhatsApp\InteraktProvider($secretsService, $credential->environment);
                $result = $provider->sendWhatsApp($recipient, 'Test WhatsApp message from Integrations Tester');
                
                return [
                    'success' => $result['status'] === 'sent',
                    'message' => $result['message'] ?? 'WhatsApp sent',
                    'data' => $result,
                ];
            }

            return ['success' => false, 'message' => 'Provider not implemented'];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function testPushProvider(string $providerKey, Secret $credential, array $testInputs, SecretsService $secretsService): array
    {
        $deviceToken = $testInputs['device_token'] ?? null;
        $title = $testInputs['title'] ?? 'Test Notification';
        $body = $testInputs['body'] ?? 'Test push notification';
        
        if (!$deviceToken) {
            return ['success' => false, 'message' => 'Device token required'];
        }

        try {
            if ($providerKey === 'firebase_fcm_v1') {
                $fcmClient = new \App\Core\Services\FcmHttpV1Client(
                    app(\App\Core\Services\FcmAccessTokenService::class),
                    $providerKey,
                    $credential->environment
                );
                
                $result = $fcmClient->sendNotification($deviceToken, $title, $body);
                
                return [
                    'success' => $result['status'] === 'sent',
                    'message' => $result['status'] === 'sent' ? 'Push notification sent' : ($result['error'] ?? 'Failed'),
                    'data' => $result,
                ];
            }

            return ['success' => false, 'message' => 'Provider not implemented'];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function formatTestResult(?array $result): string
    {
        if (!$result) {
            return '';
        }

        $status = $result['success'] ? '✅ Success' : '❌ Failed';
        $message = $result['message'] ?? '';
        $data = $result['data'] ?? [];

        $output = "{$status}\n\n{$message}";
        
        if (!empty($data)) {
            $output .= "\n\nData: " . json_encode($data, JSON_PRETTY_PRINT);
        }

        return $output;
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('run_test')
                ->label('Run Test')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->action('runTest'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('system.tools.view') ?? false;
    }
}
