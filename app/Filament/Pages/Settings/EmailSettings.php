<?php

namespace App\Filament\Pages\Settings;

use App\Core\Services\AppConfigService;
use App\Core\Services\AuditService;
use App\Core\Services\SettingsService;
use App\Core\Settings\SettingKeys;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class EmailSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'settings/email';
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static string $view = 'filament.pages.settings.email-settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Email';
    protected static ?int $navigationSort = 6;

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
            'email_provider' => $settings->get(SettingKeys::EMAIL_PROVIDER, 'smtp'),
            'smtp_host' => $settings->get(SettingKeys::SMTP_HOST, ''),
            'smtp_port' => $settings->get(SettingKeys::SMTP_PORT, 587),
            'smtp_username' => $settings->get(SettingKeys::SMTP_USERNAME, ''),
            'smtp_password' => $settings->get(SettingKeys::SMTP_PASSWORD, ''),
            'smtp_encryption' => $settings->get(SettingKeys::SMTP_ENCRYPTION, 'tls'),
            'email_from_address' => $settings->get(SettingKeys::EMAIL_FROM_ADDRESS, ''),
            'email_from_name' => $settings->get(SettingKeys::EMAIL_FROM_NAME, ''),
            'sendgrid_credential_id' => $settings->get(SettingKeys::SENDGRID_CREDENTIAL_ID, null),
            'mailgun_credential_id' => $settings->get(SettingKeys::MAILGUN_CREDENTIAL_ID, null),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Email Provider')
                    ->schema([
                        Forms\Components\Select::make('email_provider')
                            ->label('Email Provider')
                            ->options([
                                'smtp' => 'SMTP',
                                'sendgrid' => 'SendGrid',
                                'mailgun' => 'Mailgun',
                            ])
                            ->required()
                            ->default('smtp')
                            ->live()
                            ->helperText('Choose your email service provider'),
                    ]),

                Forms\Components\Section::make('SMTP Configuration')
                    ->schema([
                        Forms\Components\TextInput::make('smtp_host')
                            ->label('SMTP Host')
                            ->required()
                            ->maxLength(255)
                            ->helperText('e.g., smtp.gmail.com'),
                        Forms\Components\TextInput::make('smtp_port')
                            ->label('SMTP Port')
                            ->numeric()
                            ->required()
                            ->default(587)
                            ->helperText('Common ports: 587 (TLS), 465 (SSL), 25'),
                        Forms\Components\TextInput::make('smtp_username')
                            ->label('SMTP Username')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Your email address'),
                        Forms\Components\TextInput::make('smtp_password')
                            ->label('SMTP Password')
                            ->password()
                            ->required()
                            ->maxLength(255)
                            ->helperText('Your email password or app password'),
                        Forms\Components\Select::make('smtp_encryption')
                            ->label('Encryption')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                                'none' => 'None',
                            ])
                            ->required()
                            ->default('tls'),
                        Forms\Components\TextInput::make('email_from_address')
                            ->label('From Email Address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->helperText('Default sender email'),
                        Forms\Components\TextInput::make('email_from_name')
                            ->label('From Name')
                            ->maxLength(255)
                            ->helperText('Default sender name'),
                    ])
                    ->columns(2)
                    ->visible(fn ($get) => $get('email_provider') === 'smtp'),

                Forms\Components\Section::make('SendGrid Configuration')
                    ->schema([
                        Forms\Components\Select::make('sendgrid_credential_id')
                            ->label('SendGrid Credentials')
                            ->options(function () {
                                return Provider::where('type', 'notification')
                                    ->where('name', 'sendgrid')
                                    ->where('is_enabled', true)
                                    ->pluck('label', 'id');
                            })
                            ->searchable()
                            ->helperText('Select SendGrid provider credentials')
                            ->placeholder('Select credentials'),
                    ])
                    ->visible(fn ($get) => $get('email_provider') === 'sendgrid'),

                Forms\Components\Section::make('Mailgun Configuration')
                    ->schema([
                        Forms\Components\Select::make('mailgun_credential_id')
                            ->label('Mailgun Credentials')
                            ->options(function () {
                                return Provider::where('type', 'notification')
                                    ->where('name', 'mailgun')
                                    ->where('is_enabled', true)
                                    ->pluck('label', 'id');
                            })
                            ->searchable()
                            ->helperText('Select Mailgun provider credentials')
                            ->placeholder('Select credentials'),
                    ])
                    ->visible(fn ($get) => $get('email_provider') === 'mailgun'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('settings.edit'), 403);

        $data = $this->form->getState();
        $settings = app(SettingsService::class);
        
        $keys = [
            SettingKeys::EMAIL_PROVIDER, SettingKeys::SMTP_HOST, SettingKeys::SMTP_PORT,
            SettingKeys::SMTP_USERNAME, SettingKeys::SMTP_PASSWORD, SettingKeys::SMTP_ENCRYPTION,
            SettingKeys::EMAIL_FROM_ADDRESS, SettingKeys::EMAIL_FROM_NAME,
            SettingKeys::SENDGRID_CREDENTIAL_ID, SettingKeys::MAILGUN_CREDENTIAL_ID,
        ];
        $before = $settings->snapshot($keys);

        DB::transaction(function () use ($data, $settings) {
            $settings->set(SettingKeys::EMAIL_PROVIDER, $data['email_provider'], 'email', 'string', false);
            
            if ($data['email_provider'] === 'smtp') {
                $settings->set(SettingKeys::SMTP_HOST, $data['smtp_host'], 'email', 'string', false);
                $settings->set(SettingKeys::SMTP_PORT, $data['smtp_port'], 'email', 'integer', false);
                $settings->set(SettingKeys::SMTP_USERNAME, $data['smtp_username'], 'email', 'string', false);
                $settings->set(SettingKeys::SMTP_PASSWORD, $data['smtp_password'], 'email', 'string', false);
                $settings->set(SettingKeys::SMTP_ENCRYPTION, $data['smtp_encryption'], 'email', 'string', false);
            }
            
            $settings->set(SettingKeys::EMAIL_FROM_ADDRESS, $data['email_from_address'], 'email', 'string', false);
            $settings->set(SettingKeys::EMAIL_FROM_NAME, $data['email_from_name'] ?? '', 'email', 'string', false);
            
            if ($data['email_provider'] === 'sendgrid') {
                $settings->set(SettingKeys::SENDGRID_CREDENTIAL_ID, $data['sendgrid_credential_id'] ?? null, 'email', 'integer', false);
            }
            
            if ($data['email_provider'] === 'mailgun') {
                $settings->set(SettingKeys::MAILGUN_CREDENTIAL_ID, $data['mailgun_credential_id'] ?? null, 'email', 'integer', false);
            }
        });

        $after = $settings->snapshot($keys);

        AuditService::log('settings.email_updated', null, $before, $after, ['module' => 'settings']);
        app(AppConfigService::class)->clearCache();

        Notification::make()
            ->title('Email settings saved successfully')
            ->success()
            ->send();

        $this->loadSettings();
    }

    public function testEmail(): void
    {
        // Placeholder for email test
        Notification::make()
            ->title('Email test')
            ->body('Test email functionality will be implemented')
            ->info()
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
