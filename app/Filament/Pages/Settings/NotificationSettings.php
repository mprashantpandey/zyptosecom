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

class NotificationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'settings/notifications';
    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static string $view = 'filament.pages.settings.notification-settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Notifications';
    protected static ?int $navigationSort = 5;

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
            'quiet_hours_enabled' => $settings->get(SettingKeys::QUIET_HOURS_ENABLED, false),
            'quiet_hours_start' => $settings->get(SettingKeys::QUIET_HOURS_START, '22:00'),
            'quiet_hours_end' => $settings->get(SettingKeys::QUIET_HOURS_END, '08:00'),
            'push_enabled' => $settings->get(SettingKeys::PUSH_ENABLED, false),
            'firebase_sender_id' => $settings->get(SettingKeys::FIREBASE_SENDER_ID, ''),
            'sms_provider_id' => $settings->get(SettingKeys::SMS_CREDENTIAL_ID, null),
            'whatsapp_provider_id' => $settings->get(SettingKeys::WHATSAPP_CREDENTIAL_ID, null),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Quiet Hours')
                    ->schema([
                        Forms\Components\Toggle::make('quiet_hours_enabled')
                            ->label('Enable Quiet Hours')
                            ->default(false)
                            ->helperText('Suppress notifications during specified hours'),
                        Forms\Components\TimePicker::make('quiet_hours_start')
                            ->label('Start Time')
                            ->default('22:00')
                            ->required()
                            ->visible(fn ($get) => $get('quiet_hours_enabled')),
                        Forms\Components\TimePicker::make('quiet_hours_end')
                            ->label('End Time')
                            ->default('08:00')
                            ->required()
                            ->visible(fn ($get) => $get('quiet_hours_enabled')),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Push Notifications')
                    ->schema([
                        Forms\Components\Toggle::make('push_enabled')
                            ->label('Enable Push Notifications')
                            ->default(false),
                        Forms\Components\TextInput::make('firebase_sender_id')
                            ->label('Firebase Sender ID')
                            ->maxLength(255)
                            ->helperText('Firebase Cloud Messaging Sender ID')
                            ->visible(fn ($get) => $get('push_enabled')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('SMS Provider')
                    ->schema([
                        Forms\Components\Select::make('sms_provider_id')
                            ->label('SMS Provider')
                            ->options(function () {
                                return Provider::where('type', 'notification')
                                    ->where('is_enabled', true)
                                    ->where(function ($q) {
                                        $q->where('name', 'like', '%sms%')
                                          ->orWhere('name', 'like', '%twilio%')
                                          ->orWhere('name', 'like', '%msg91%');
                                    })
                                    ->pluck('label', 'id');
                            })
                            ->searchable()
                            ->helperText('Select provider for SMS notifications')
                            ->placeholder('Select SMS provider'),
                    ]),

                Forms\Components\Section::make('WhatsApp Provider')
                    ->schema([
                        Forms\Components\Select::make('whatsapp_provider_id')
                            ->label('WhatsApp Provider')
                            ->options(function () {
                                return Provider::where('type', 'notification')
                                    ->where('is_enabled', true)
                                    ->where(function ($q) {
                                        $q->where('name', 'like', '%whatsapp%')
                                          ->orWhere('name', 'like', '%twilio%');
                                    })
                                    ->pluck('label', 'id');
                            })
                            ->searchable()
                            ->helperText('Select provider for WhatsApp notifications')
                            ->placeholder('Select WhatsApp provider'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('settings.edit'), 403);

        $data = $this->form->getState();
        $settings = app(SettingsService::class);
        
        $keys = [
            SettingKeys::QUIET_HOURS_ENABLED, SettingKeys::QUIET_HOURS_START,
            SettingKeys::QUIET_HOURS_END, SettingKeys::PUSH_ENABLED,
            SettingKeys::FIREBASE_SENDER_ID, SettingKeys::SMS_CREDENTIAL_ID,
            SettingKeys::WHATSAPP_CREDENTIAL_ID,
        ];
        $before = $settings->snapshot($keys);

        DB::transaction(function () use ($data, $settings) {
            $settings->set(SettingKeys::QUIET_HOURS_ENABLED, $data['quiet_hours_enabled'] ?? false, 'notifications', 'boolean', false);
            $settings->set(SettingKeys::QUIET_HOURS_START, $data['quiet_hours_start'] ?? '22:00', 'notifications', 'string', false);
            $settings->set(SettingKeys::QUIET_HOURS_END, $data['quiet_hours_end'] ?? '08:00', 'notifications', 'string', false);
            $settings->set(SettingKeys::PUSH_ENABLED, $data['push_enabled'] ?? false, 'notifications', 'boolean', false);
            $settings->set(SettingKeys::FIREBASE_SENDER_ID, $data['firebase_sender_id'] ?? '', 'notifications', 'string', false);
            $settings->set(SettingKeys::SMS_CREDENTIAL_ID, $data['sms_provider_id'] ?? null, 'notifications', 'integer', false);
            $settings->set(SettingKeys::WHATSAPP_CREDENTIAL_ID, $data['whatsapp_provider_id'] ?? null, 'notifications', 'integer', false);
        });

        $after = $settings->snapshot($keys);

        AuditService::log('settings.notification_updated', null, $before, $after, ['module' => 'settings']);
        app(AppConfigService::class)->clearCache();

        Notification::make()
            ->title('Notification settings saved successfully')
            ->success()
            ->send();

        $this->loadSettings();
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
