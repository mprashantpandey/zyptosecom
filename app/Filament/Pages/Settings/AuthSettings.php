<?php

namespace App\Filament\Pages\Settings;

use App\Core\Providers\ProviderRegistry;
use App\Core\Services\AppConfigService;
use App\Core\Services\AuditService;
use App\Core\Services\SettingsService;
use App\Core\Services\SecretsService;
use App\Core\Settings\SettingKeys;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class AuthSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'settings/auth';
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static string $view = 'filament.pages.settings.auth-settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Authentication';
    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('settings.view'), 403);
        $this->loadSettings();
    }

    protected function loadSettings(): void
    {
        $settings = app(SettingsService::class);
        
        $this->data = [
            'auth_method' => $settings->get(SettingKeys::AUTH_METHOD, 'email_password'),
            'firebase_project_id' => $settings->get(SettingKeys::FIREBASE_PROJECT_ID, ''),
            'firebase_api_key' => $settings->get(SettingKeys::FIREBASE_API_KEY, ''),
            'firebase_credential_id' => $settings->get('firebase_credential_id', null),
            'otp_provider_id' => $settings->get(SettingKeys::OTP_CREDENTIAL_ID, null),
            'enable_email_login' => $settings->get(SettingKeys::ENABLE_EMAIL_LOGIN, true),
            'firebase_sign_in_methods' => $settings->get('firebase_sign_in_methods', ['email', 'phone']),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Authentication Method')
                    ->description('Choose how users will authenticate in your app')
                    ->schema([
                        Forms\Components\Select::make('auth_method')
                            ->label('Primary Authentication Method')
                            ->options([
                                'email_password' => 'Email & Password',
                                'firebase' => 'Firebase Authentication (Recommended for Mobile)',
                                'otp_provider' => 'Custom OTP Provider (SMS/WhatsApp)',
                            ])
                            ->required()
                            ->default('email_password')
                            ->live()
                            ->helperText('Firebase Authentication is recommended for mobile apps with Phone OTP support'),
                    ]),

                Forms\Components\Section::make('Email & Password')
                    ->schema([
                        Forms\Components\Toggle::make('enable_email_login')
                            ->label('Enable Email/Password Login')
                            ->default(true)
                            ->helperText('Allow users to login with email and password'),
                    ])
                    ->visible(fn ($get) => in_array($get('auth_method'), ['email_password', 'firebase'])),

                Forms\Components\Section::make('Firebase Configuration')
                    ->description('Configure Firebase Authentication for mobile apps')
                    ->schema([
                        Forms\Components\Placeholder::make('firebase_guide')
                            ->label('Setup Guide')
                            ->content(view('filament.components.firebase-auth-guide')),
                        
                        Forms\Components\TextInput::make('firebase_project_id')
                            ->label('Firebase Project ID')
                            ->maxLength(255)
                            ->required(fn ($get) => $get('auth_method') === 'firebase')
                            ->helperText('Your Firebase project ID (found in Firebase Console → Project Settings)'),
                        
                        Forms\Components\TextInput::make('firebase_api_key')
                            ->label('Firebase Web API Key')
                            ->maxLength(255)
                            ->required(fn ($get) => $get('auth_method') === 'firebase')
                            ->helperText('Web API Key for client-side initialization (not a secret; safe to expose in mobile app)')
                            ->placeholder('AIza...'),
                        
                        Forms\Components\CheckboxList::make('firebase_sign_in_methods')
                            ->label('Allowed Sign-in Methods')
                            ->options([
                                'email' => 'Email/Password',
                                'phone' => 'Phone OTP',
                                'google' => 'Google Sign-in',
                            ])
                            ->default(['email', 'phone'])
                            ->columns(3)
                            ->helperText('Select which sign-in methods are enabled in Firebase Console')
                            ->descriptions([
                                'email' => 'Users can sign in with email and password',
                                'phone' => 'Users can sign in with phone number (OTP)',
                                'google' => 'Users can sign in with Google account',
                            ]),
                        
                        Forms\Components\Select::make('firebase_credential_id')
                            ->label('Firebase Admin Service Account')
                            ->helperText('Service account JSON for server-side ID token verification (optional)')
                            ->options(function () {
                                return Provider::where('type', 'auth')
                                    ->where('name', 'firebase_auth')
                                    ->where('is_enabled', true)
                                    ->get()
                                    ->mapWithKeys(function ($provider) {
                                        $secretsService = app(SecretsService::class);
                                        $credentials = $secretsService->getCredentials(
                                            $provider->type,
                                            $provider->name,
                                            $provider->environment
                                        );
                                        $hasCredentials = !empty($credentials);
                                        return [$provider->id => $provider->label . ($hasCredentials ? ' ✅' : ' (Not configured)')];
                                    });
                            })
                            ->searchable()
                            ->placeholder('Select credentials (optional)')
                            ->helperText('Only needed if you verify Firebase ID tokens on the backend'),
                        
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('test_firebase')
                                ->label('Test Firebase Connection')
                                ->icon('heroicon-o-arrow-path')
                                ->color('info')
                                ->requiresConfirmation()
                                ->modalHeading('Test Firebase Connection')
                                ->modalDescription('This will verify your Firebase configuration and service account JSON (if provided).')
                                ->action(function ($get) {
                                    $this->testFirebase($get);
                                }),
                        ]),
                    ])
                    ->visible(fn ($get) => $get('auth_method') === 'firebase')
                    ->columns(2),

                Forms\Components\Section::make('OTP Provider')
                    ->schema([
                        Forms\Components\Select::make('otp_provider_id')
                            ->label('OTP Provider')
                            ->options(function () {
                                return Provider::where('type', 'notification')
                                    ->whereIn('name', ['msg91', 'twilio', 'interakt'])
                                    ->where('is_enabled', true)
                                    ->pluck('label', 'id');
                            })
                            ->searchable()
                            ->helperText('Select the provider for OTP delivery (SMS/WhatsApp). Configure credentials in Integrations.')
                            ->placeholder('Select a provider'),
                    ])
                    ->visible(fn ($get) => $get('auth_method') === 'otp_provider'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('settings.edit'), 403);

        $data = $this->form->getState();
        $settings = app(SettingsService::class);
        
        $keys = [
            SettingKeys::AUTH_METHOD, SettingKeys::FIREBASE_PROJECT_ID,
            SettingKeys::FIREBASE_API_KEY, SettingKeys::OTP_CREDENTIAL_ID,
            SettingKeys::ENABLE_EMAIL_LOGIN, 'firebase_credential_id', 'firebase_sign_in_methods',
        ];
        $before = $settings->snapshot($keys);

        DB::transaction(function () use ($data, $settings) {
            $settings->set(SettingKeys::AUTH_METHOD, $data['auth_method'], 'auth', 'string', false);
            $settings->set(SettingKeys::ENABLE_EMAIL_LOGIN, $data['enable_email_login'] ?? true, 'auth', 'boolean', false);
            
            if ($data['auth_method'] === 'firebase') {
                $settings->set(SettingKeys::FIREBASE_PROJECT_ID, $data['firebase_project_id'] ?? '', 'auth', 'string', false);
                $settings->set(SettingKeys::FIREBASE_API_KEY, $data['firebase_api_key'] ?? '', 'auth', 'string', false);
                $settings->set('firebase_credential_id', $data['firebase_credential_id'] ?? null, 'auth', 'integer', false);
                $settings->set('firebase_sign_in_methods', $data['firebase_sign_in_methods'] ?? ['email', 'phone'], 'auth', 'json', false);
            }
            
            if ($data['auth_method'] === 'otp_provider') {
                $settings->set(SettingKeys::OTP_CREDENTIAL_ID, $data['otp_provider_id'] ?? null, 'auth', 'integer', false);
            }
        });

        $after = $settings->snapshot($keys);

        AuditService::log('settings.auth_updated', null, $before, $after, ['module' => 'settings']);
        app(AppConfigService::class)->clearCache();

        Notification::make()
            ->title('Authentication settings saved successfully')
            ->success()
            ->send();

        $this->loadSettings();
    }

    public function testFirebase($get = null): void
    {
        abort_unless(auth()->user()->can('settings.edit'), 403);

        $data = $get ?: $this->form->getState();
        
        $errors = [];
        
        // Check Project ID
        if (empty($data['firebase_project_id'])) {
            $errors[] = 'Firebase Project ID is required';
        }
        
        // Check Web API Key
        if (empty($data['firebase_api_key'])) {
            $errors[] = 'Firebase Web API Key is required';
        } elseif (!str_starts_with($data['firebase_api_key'], 'AIza')) {
            $errors[] = 'Firebase Web API Key should start with "AIza"';
        }
        
        // Check Service Account JSON if credential is selected
        if (!empty($data['firebase_credential_id'])) {
            $provider = Provider::find($data['firebase_credential_id']);
            if ($provider) {
                $secretsService = app(SecretsService::class);
                $credentials = $secretsService->getCredentials(
                    $provider->type,
                    $provider->name,
                    $provider->environment
                );
                
                if (empty($credentials)) {
                    $errors[] = 'Selected Firebase credentials are not configured. Please configure them in Integrations.';
                } else {
                    // Validate service account JSON structure
                    $serviceAccount = $credentials['service_account_json'] ?? null;
                    if ($serviceAccount) {
                        if (is_string($serviceAccount)) {
                            $serviceAccount = json_decode($serviceAccount, true);
                        }
                        
                        if (!is_array($serviceAccount)) {
                            $errors[] = 'Service account JSON is invalid';
                        } else {
                            $requiredFields = ['project_id', 'client_email', 'private_key'];
                            foreach ($requiredFields as $field) {
                                if (empty($serviceAccount[$field])) {
                                    $errors[] = "Service account JSON missing required field: {$field}";
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if (!empty($errors)) {
            Notification::make()
                ->title('Firebase test failed')
                ->body(implode("\n", $errors))
                ->danger()
                ->send();
            return;
        }
        
        // If we got here, basic validation passed
        AuditService::log('settings.firebase_tested', null, [], ['status' => 'success'], ['module' => 'settings']);
        
        Notification::make()
            ->title('Firebase connection test successful')
            ->body('Your Firebase configuration appears to be correct. Make sure to enable the selected sign-in methods in Firebase Console.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Save Settings')
                ->submit('save')
                ->color('primary'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.view') ?? false;
    }
}
