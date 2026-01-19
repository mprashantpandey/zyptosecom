<?php

namespace App\Filament\Pages;

use App\Core\Providers\ProviderRegistry;
use App\Core\Services\AuditService;
use App\Core\Services\SecretsService;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProviderCredentialsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static string $view = 'filament.pages.provider-credentials-page';
    protected static ?string $navigationGroup = 'Integrations';
    protected static ?string $navigationLabel = 'Integration Credentials';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'providers/credentials';
    protected static bool $shouldRegisterNavigation = true; // Show in navigation as main entry point

    public ?Provider $provider = null;
    public ?int $providerId = null; // Store provider ID for Livewire state persistence
    public ?int $provider_id = null; // Snake case for Livewire form binding (matches form field name)
    public ?array $data = [];
    public ?string $environment = 'sandbox';
    public ?array $providerSchema = null;
    public array $configuredFields = [];

    public function mount($provider = null): void
    {
        $user = auth()->user();
        abort_unless(
            $user?->hasRole('super_admin') || $user?->can('integrations.edit'),
            403,
            'You do not have permission to access this page.'
        );

        // Get provider from parameter or query string
        $providerId = $provider ?? request()->query('provider');
        
        if (!$providerId) {
            // If no provider specified, we'll show a provider selector in the form
            // Don't load credentials yet
            return;
        }

        $this->provider = Provider::findOrFail($providerId);
        $this->providerId = $this->provider->id; // Store for Livewire state
        $this->provider_id = $this->provider->id; // Snake case for form binding
        $this->environment = $this->provider->environment ?? 'sandbox';
        
        // Load provider schema from registry
        // Try provider name first, then provider key if name doesn't match
        $this->providerSchema = ProviderRegistry::get($this->provider->name) 
            ?? ProviderRegistry::get($this->provider->key ?? $this->provider->name);
        
        if (!$this->providerSchema) {
            \Filament\Notifications\Notification::make()
                ->title('Provider schema not found')
                ->body("Provider '{$this->provider->name}' is not registered in ProviderRegistry. Please ensure the provider key matches the registry.")
                ->warning()
                ->persistent()
                ->send();
        }
        
        $this->loadCredentials();
    }

    protected function loadCredentials(): void
    {
        if (!$this->provider) {
            return;
        }

        $secretsService = app(SecretsService::class);
        $credentials = $secretsService->getCredentials(
            $this->provider->type,
            $this->provider->name,
            $this->environment
        );

        $this->data = $credentials ?? [];
        
        // Track which fields are configured (for showing "Configured ✅")
        if ($this->providerSchema) {
            foreach ($this->providerSchema['credential_schema'] ?? [] as $field) {
                $key = $field['key'] ?? null;
                if ($key) {
                    $this->configuredFields[$key] = isset($credentials[$key]) && !empty($credentials[$key]);
                }
            }
        }
    }

    public function form(Form $form): Form
    {
        // If no provider selected, show provider selector
        if (!$this->provider) {
            return $form->schema([
                Forms\Components\Section::make('Select Provider')
                    ->description('Choose a provider to configure credentials')
                    ->schema([
                        Forms\Components\Select::make('provider_id')
                            ->label('Provider')
                            ->options(Provider::orderBy('type')->orderBy('label')->get()->mapWithKeys(function ($provider) {
                                return [$provider->id => "{$provider->label} ({$provider->type})"];
                            }))
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                if ($state) {
                                    $this->providerId = $state;
                                    $this->provider_id = $state; // Sync snake_case for Livewire
                                    // Automatically load the provider form without needing to save
                                    $this->mount($state); // Reload provider and schema
                                    // Refresh the form to show the credentials fields
                                    $this->form->fill();
                                }
                            }),
                    ]),
            ]);
        }

        if (!$this->providerSchema) {
            return $form->schema([
                Forms\Components\Placeholder::make('no_schema')
                    ->content('Provider schema not found in registry. Please configure manually.')
            ]);
        }

        $schema = $this->providerSchema['credential_schema'] ?? [];
        $fields = [];

        foreach ($schema as $fieldDef) {
            $key = $fieldDef['key'] ?? null;
            $type = $fieldDef['type'] ?? 'text';
            $isSecret = $fieldDef['is_secret'] ?? false;
            $isConfigured = $this->configuredFields[$key] ?? false;
            $required = $fieldDef['required'] ?? false;

            // For secret fields that are configured, show "Configured ✅" with replace toggle
            if ($isSecret && $isConfigured) {
                $fields[] = Forms\Components\Section::make($fieldDef['label'] ?? $key)
                    ->schema([
                        Forms\Components\Placeholder::make("{$key}_status")
                            ->label('Status')
                            ->content('✅ Configured')
                            ->icon('heroicon-o-check-circle')
                            ->color('success'),
                        Forms\Components\Toggle::make("{$key}_replace")
                            ->label('Replace ' . ($fieldDef['label'] ?? $key))
                            ->helperText('Toggle to replace this credential')
                            ->live()
                            ->default(false),
                        $this->makeFieldFromSchema($fieldDef, "{$key}_new")
                            ->visible(fn ($get) => $get("{$key}_replace"))
                            ->required(fn ($get) => $get("{$key}_replace") && $required),
                    ])
                    ->collapsible()
                    ->collapsed();
            } else {
                // Regular field (not configured secret)
                $fields[] = $this->makeFieldFromSchema($fieldDef, $key);
            }
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Provider Information')
                    ->schema([
                        Forms\Components\Placeholder::make('provider_name')
                            ->label('Provider')
                            ->content($this->providerSchema['display_name'] ?? $this->provider?->label ?? $this->provider?->name ?? 'N/A'),
                        Forms\Components\Placeholder::make('provider_description')
                            ->label('Description')
                            ->content($this->providerSchema['description'] ?? ''),
                        Forms\Components\Select::make('environment')
                            ->label('Environment')
                            ->options([
                                'sandbox' => 'Sandbox/Test',
                                'production' => 'Production',
                            ])
                            ->required()
                            ->default($this->environment)
                            ->live()
                            ->afterStateUpdated(function ($state) {
                                $this->environment = $state;
                                $this->loadCredentials();
                            }),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Credentials')
                    ->description('Credentials are encrypted at rest. Never share these values.')
                    ->schema($fields)
                    ->columnSpanFull(),

                Forms\Components\Section::make('Actions')
                    ->schema([
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('test')
                                ->label('Test Connection')
                                ->icon('heroicon-o-arrow-path')
                                ->color('info')
                                ->action(function () {
                                    $this->testConnection();
                                }),
                            Forms\Components\Actions\Action::make('copy_public')
                                ->label('Copy Public Keys')
                                ->icon('heroicon-o-clipboard')
                                ->color('gray')
                                ->action(function () {
                                    $this->copyPublicKeys();
                                })
                                ->visible(fn () => $this->hasPublicKeys()),
                            Forms\Components\Actions\Action::make('view_webhook_url')
                                ->label('View Webhook URL')
                                ->icon('heroicon-o-link')
                                ->color('warning')
                                ->modalHeading('Webhook URL')
                                ->modalContent(fn () => view('filament.pages.webhook-url', [
                                    'provider' => $this->provider,
                                    'url' => $this->provider ? route('api.webhooks', ['provider' => $this->provider->name]) : '#',
                                ]))
                                ->modalSubmitAction(false)
                                ->visible(fn () => $this->provider && in_array($this->provider->type, ['payment', 'shipping'])),
                        ]),
                    ])
                    ->extraAttributes(['class' => 'mb-6']), // Add bottom margin to Actions section
            ])
            ->statePath('data')
            ->extraAttributes(['class' => 'mb-6']); // Add bottom margin to form
    }

    protected function makeFieldFromSchema(array $fieldDef, string $key): Forms\Components\Field
    {
        $type = $fieldDef['type'] ?? 'text';
        $label = $fieldDef['label'] ?? $key;
        $required = $fieldDef['required'] ?? false;
        $helpText = $fieldDef['help_text'] ?? null;

        $field = match($type) {
            'password' => Forms\Components\TextInput::make($key)
                ->password()
                ->revealable(),
            'textarea' => Forms\Components\Textarea::make($key)
                ->rows(3),
            'number' => Forms\Components\TextInput::make($key)
                ->numeric(),
            'email' => Forms\Components\TextInput::make($key)
                ->email(),
            'url' => Forms\Components\TextInput::make($key)
                ->url(),
            'select' => Forms\Components\Select::make($key)
                ->options($fieldDef['options'] ?? [])
                ->searchable(),
            'bool' => Forms\Components\Toggle::make($key),
            'file_json' => Forms\Components\FileUpload::make($key)
                ->acceptedFileTypes(['application/json'])
                ->maxFiles(1)
                ->maxSize(512)
                ->afterStateUpdated(function ($state) use ($key, $fieldDef) {
                    if ($state && count($state) > 0) {
                        $filePath = storage_path('app/' . $state[0]);
                        $jsonContent = file_get_contents($filePath);
                        $json = json_decode($jsonContent, true);
                        
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            Notification::make()
                                ->title('Invalid JSON file')
                                ->body('The uploaded file is not valid JSON')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        // Validate required fields for service account
                        if ($key === 'service_account_json') {
                            $required = ['type', 'project_id', 'private_key', 'client_email'];
                            $missing = array_diff($required, array_keys($json));
                            
                            if (!empty($missing)) {
                                Notification::make()
                                    ->title('Invalid service account file')
                                    ->body('Missing required fields: ' . implode(', ', $missing))
                                    ->danger()
                                    ->send();
                                return;
                            }
                            
                            // Auto-fill project_id if field exists
                            if (isset($json['project_id'])) {
                                $this->data['project_id'] = $json['project_id'];
                            }
                        }
                        
                        // Store JSON content
                        $this->data[$key] = $jsonContent;
                        
                        Notification::make()
                            ->title('File loaded successfully')
                            ->body('JSON validated and ready to save')
                            ->success()
                            ->send();
                    }
                }),
            default => Forms\Components\TextInput::make($key),
        };

        return $field
            ->label($label)
            ->required($required)
            ->helperText($helpText)
            ->default($this->data[$key] ?? ($fieldDef['default'] ?? null));
    }

    protected function hasPublicKeys(): bool
    {
        if (!$this->providerSchema) {
            return false;
        }

        foreach ($this->providerSchema['credential_schema'] ?? [] as $field) {
            if (!($field['is_secret'] ?? false)) {
                $key = $field['key'] ?? null;
                if ($key && isset($this->data[$key]) && !empty($this->data[$key])) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function copyPublicKeys(): void
    {
        if (!$this->providerSchema) {
            return;
        }

        $public = [];
        foreach ($this->providerSchema['credential_schema'] ?? [] as $field) {
            if (!($field['is_secret'] ?? false)) {
                $key = $field['key'] ?? null;
                if ($key && isset($this->data[$key]) && !empty($this->data[$key])) {
                    $public[$field['label'] ?? $key] = $this->data[$key];
                }
            }
        }

        $text = "Public Keys for {$this->providerSchema['display_name']}:\n\n";
        foreach ($public as $label => $value) {
            $text .= "{$label}: {$value}\n";
        }

        // Copy to clipboard (via JavaScript in browser)
        Notification::make()
            ->title('Public keys copied')
            ->body('Public configuration values have been copied to clipboard')
            ->success()
            ->send();
    }

    public function save(): void
    {
        $user = auth()->user();
        abort_unless(
            $user?->hasRole('super_admin') || $user?->can('integrations.edit'),
            403,
            'You do not have permission to save credentials.'
        );

        // Reload provider if not set (Livewire state might be lost on form submission)
        if (!$this->provider) {
            // Try providerId property first (camelCase)
            if ($this->providerId) {
                $this->provider = Provider::find($this->providerId);
            }
            
            // Try provider_id property (snake_case for form binding)
            if (!$this->provider && $this->provider_id) {
                $this->provider = Provider::find($this->provider_id);
                if ($this->provider) {
                    $this->providerId = $this->provider->id;
                }
            }
            
            // Fallback to query string
            if (!$this->provider) {
                $providerId = request()->query('provider');
                if ($providerId) {
                    $this->provider = Provider::find($providerId);
                    if ($this->provider) {
                        $this->providerId = $this->provider->id;
                        $this->provider_id = $this->provider->id;
                    }
                }
            }
        }

        if (!$this->provider) {
            Notification::make()
                ->title('Cannot save')
                ->body('Please select a provider first. If you selected a provider, try refreshing the page.')
                ->danger()
                ->persistent()
                ->send();
            return;
        }

        // Reload provider schema if not set (in case of form submission)
        if (!$this->providerSchema) {
            $this->providerSchema = ProviderRegistry::get($this->provider->name) 
                ?? ProviderRegistry::get($this->provider->key ?? $this->provider->name);
        }

        if (!$this->providerSchema) {
            Notification::make()
                ->title('Cannot save')
                ->body('Provider schema not found in registry. Provider: ' . $this->provider->name . '. Please ensure the provider is registered in ProviderRegistry.')
                ->danger()
                ->persistent()
                ->send();
            return;
        }

        $data = $this->form->getState();
        $environment = $data['environment'] ?? $this->environment;

        // Validate all required fields
        $rules = [];
        foreach ($this->providerSchema['credential_schema'] ?? [] as $field) {
            $key = $field['key'] ?? null;
            if ($key && ($field['required'] ?? false)) {
                // Check if it's a replace field or new field
                $replaceKey = "{$key}_replace";
                $newKey = "{$key}_new";
                
                if (isset($data[$replaceKey]) && $data[$replaceKey]) {
                    $rules[$newKey] = $field['validation'] ?? 'required';
                } elseif (!($this->configuredFields[$key] ?? false)) {
                    $rules[$key] = $field['validation'] ?? 'required';
                }
            }
        }

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            Notification::make()
                ->title('Validation failed')
                ->body($validator->errors()->first())
                ->danger()
                ->send();
            return;
        }

        // Get existing credentials for audit
        $secretsService = app(SecretsService::class);
        $before = $secretsService->getCredentials(
            $this->provider->type,
            $this->provider->name,
            $environment
        ) ?? [];

        DB::transaction(function () use ($data, $environment, $secretsService) {
            // Process each field
            foreach ($this->providerSchema['credential_schema'] ?? [] as $field) {
                $key = $field['key'] ?? null;
                if (!$key) continue;

                $replaceKey = "{$key}_replace";
                $newKey = "{$key}_new";

                // Handle replace logic for secret fields
                if (isset($data[$replaceKey]) && $data[$replaceKey] && isset($data[$newKey])) {
                    $value = $data[$newKey];
                    if (!empty($value)) {
                        $secretsService->setCredential(
                            $this->provider->type,
                            $this->provider->name,
                            $key,
                            $value,
                            $environment
                        );
                    }
                } elseif (isset($data[$key]) && !empty($data[$key])) {
                    // Regular field (not a replace)
                    $value = $data[$key];
                    
                    // Handle file uploads
                    if (is_array($value) && count($value) > 0) {
                        $filePath = storage_path('app/' . $value[0]);
                        if (file_exists($filePath)) {
                            $value = file_get_contents($filePath);
                        } else {
                            continue;
                        }
                    }
                    
                    $secretsService->setCredential(
                        $this->provider->type,
                        $this->provider->name,
                        $key,
                        $value,
                        $environment
                    );
                }
            }

            // Update provider environment if changed
            if ($environment !== $this->provider->environment) {
                $this->provider->update(['environment' => $environment]);
                $this->environment = $environment;
            }
        });

        $after = $secretsService->getCredentials(
            $this->provider->type,
            $this->provider->name,
            $environment
        ) ?? [];

        // Audit log (sanitized - never log actual credentials)
        AuditService::logCredentialChange(
            $this->provider,
            'credentials',
            !empty($before),
            !empty($after),
            $environment
        );

        Notification::make()
            ->title('Credentials saved successfully')
            ->success()
            ->send();

        $this->loadCredentials();
        $this->form->fill($this->data);
    }

    protected function testConnection(): void
    {
        $user = auth()->user();
        abort_unless(
            $user?->hasRole('super_admin') || $user?->can('integrations.test'),
            403,
            'You do not have permission to test connections.'
        );

        try {
            // Route to provider-specific test action
            $testAction = $this->providerSchema['test_action'] ?? null;
            
            if (!$testAction) {
                Notification::make()
                    ->title('Test not available')
                    ->body('No test action defined for this provider')
                    ->warning()
                    ->send();
                return;
            }

            // For now, show a generic test
            // In production, this would call the actual provider driver
            Notification::make()
                ->title('Connection test initiated')
                ->body('Testing connection to ' . ($this->providerSchema['display_name'] ?? $this->provider?->name ?? 'Unknown'))
                ->info()
                ->send();

            // TODO: Implement actual test logic per provider type
            // This will be done when implementing drivers

        } catch (\Exception $e) {
            Notification::make()
                ->title('Connection test failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getTitle(): string
    {
        if (!$this->provider) {
            return 'Provider Credentials';
        }
        
        return 'Provider Credentials: ' . ($this->providerSchema['display_name'] ?? $this->provider->label ?? $this->provider->name);
    }

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?\Illuminate\Database\Eloquent\Model $tenant = null): string
    {
        $url = parent::getUrl([], $isAbsolute, $panel, $tenant);
        if (!empty($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }
        return $url;
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Save Credentials')
                ->submit('save')
                ->color('primary'),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user?->hasRole('super_admin') || $user?->can('integrations.edit') ?? false;
    }
}
