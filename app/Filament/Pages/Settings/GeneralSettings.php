<?php

namespace App\Filament\Pages\Settings;

use App\Core\Services\AppConfigService;
use App\Core\Services\AuditService;
use App\Core\Services\SettingsService;
use App\Core\Settings\SettingKeys;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class GeneralSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'settings/general';
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $view = 'filament.pages.settings.general-settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'General';
    protected static ?int $navigationSort = 1;

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
            'store_name' => $settings->get(SettingKeys::STORE_NAME, ''),
            'store_phone' => $settings->get(SettingKeys::STORE_PHONE, ''),
            'store_email' => $settings->get(SettingKeys::STORE_EMAIL, ''),
            'store_address' => $settings->get(SettingKeys::STORE_ADDRESS, ''),
            'store_city' => $settings->get(SettingKeys::STORE_CITY, ''),
            'store_state' => $settings->get(SettingKeys::STORE_STATE, ''),
            'store_pincode' => $settings->get(SettingKeys::STORE_PINCODE, ''),
            'currency' => $settings->get(SettingKeys::CURRENCY, 'INR'),
            'timezone' => $settings->get(SettingKeys::TIMEZONE, 'Asia/Kolkata'),
            'gstin' => $settings->get(SettingKeys::GSTIN, ''),
            'invoice_prefix' => $settings->get(SettingKeys::INVOICE_PREFIX, 'INV'),
            'invoice_start_number' => $settings->get(SettingKeys::INVOICE_START_NUMBER, '1'),
            'web_url' => $settings->get(SettingKeys::WEB_URL, config('app.url')),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Store Information')
                    ->schema([
                        Forms\Components\TextInput::make('store_name')
                            ->label('Store Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('The name of your store'),
                        Forms\Components\TextInput::make('store_phone')
                            ->label('Store Phone')
                            ->tel()
                            ->maxLength(20)
                            ->helperText('Contact phone number'),
                        Forms\Components\TextInput::make('store_email')
                            ->label('Store Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->helperText('Contact email address'),
                        Forms\Components\TextInput::make('web_url')
                            ->label('Web Store URL')
                            ->url()
                            ->required()
                            ->maxLength(500)
                            ->default(config('app.url'))
                            ->helperText('The public URL of your web storefront (e.g., https://yourstore.com). Used for links in emails, CMS pages, etc.')
                            ->placeholder('https://yourstore.com'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Store Address')
                    ->schema([
                        Forms\Components\Textarea::make('store_address')
                            ->label('Address')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Street address'),
                        Forms\Components\TextInput::make('store_city')
                            ->label('City')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('store_state')
                            ->label('State')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('store_pincode')
                            ->label('Pincode')
                            ->maxLength(10)
                            ->helperText('Postal/ZIP code'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Regional Settings')
                    ->schema([
                        Forms\Components\Select::make('currency')
                            ->label('Currency')
                            ->options([
                                'INR' => 'INR - Indian Rupee',
                                'USD' => 'USD - US Dollar',
                                'EUR' => 'EUR - Euro',
                                'GBP' => 'GBP - British Pound',
                            ])
                            ->required()
                            ->default('INR')
                            ->helperText('Default currency for pricing'),
                        Forms\Components\Select::make('timezone')
                            ->label('Timezone')
                            ->options($this->getTimezoneOptions())
                            ->required()
                            ->searchable()
                            ->helperText('Store timezone'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Tax & Invoicing')
                    ->schema([
                        Forms\Components\TextInput::make('gstin')
                            ->label('GSTIN')
                            ->maxLength(15)
                            ->helperText('GST Identification Number (optional)'),
                        Forms\Components\TextInput::make('invoice_prefix')
                            ->label('Invoice Prefix')
                            ->maxLength(10)
                            ->default('INV')
                            ->helperText('Prefix for invoice numbers (e.g., INV)'),
                        Forms\Components\TextInput::make('invoice_start_number')
                            ->label('Invoice Start Number')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->helperText('Starting number for invoices'),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('settings.edit'), 403);

        $data = $this->form->getState();
        $settings = app(SettingsService::class);
        
        // Get before snapshot
        $keys = [
            SettingKeys::STORE_NAME, SettingKeys::STORE_PHONE, SettingKeys::STORE_EMAIL,
            SettingKeys::STORE_ADDRESS, SettingKeys::STORE_CITY, SettingKeys::STORE_STATE,
            SettingKeys::STORE_PINCODE, SettingKeys::CURRENCY, SettingKeys::TIMEZONE,
            SettingKeys::GSTIN, SettingKeys::INVOICE_PREFIX, SettingKeys::INVOICE_START_NUMBER,
            SettingKeys::WEB_URL,
        ];
        $before = $settings->snapshot($keys);

        DB::transaction(function () use ($data, $settings) {
            $settings->set(SettingKeys::STORE_NAME, $data['store_name'], 'general', 'string', true);
            $settings->set(SettingKeys::STORE_PHONE, $data['store_phone'] ?? '', 'general', 'string', true);
            $settings->set(SettingKeys::STORE_EMAIL, $data['store_email'], 'general', 'string', true);
            $settings->set(SettingKeys::STORE_ADDRESS, $data['store_address'] ?? '', 'general', 'string', true);
            $settings->set(SettingKeys::STORE_CITY, $data['store_city'] ?? '', 'general', 'string', true);
            $settings->set(SettingKeys::STORE_STATE, $data['store_state'] ?? '', 'general', 'string', true);
            $settings->set(SettingKeys::STORE_PINCODE, $data['store_pincode'] ?? '', 'general', 'string', true);
            $settings->set(SettingKeys::CURRENCY, $data['currency'], 'general', 'string', true);
            $settings->set(SettingKeys::TIMEZONE, $data['timezone'], 'general', 'string', true);
            $settings->set(SettingKeys::GSTIN, $data['gstin'] ?? '', 'general', 'string', false);
            $settings->set(SettingKeys::INVOICE_PREFIX, $data['invoice_prefix'] ?? 'INV', 'general', 'string', false);
            $settings->set(SettingKeys::INVOICE_START_NUMBER, $data['invoice_start_number'] ?? '1', 'general', 'integer', false);
            $settings->set(SettingKeys::WEB_URL, $data['web_url'] ?? config('app.url'), 'general', 'string', true);
        });

        $after = $settings->snapshot($keys);

        // Audit log
        AuditService::log('settings.general_updated', null, $before, $after, ['module' => 'settings']);

        // Clear cache
        app(AppConfigService::class)->clearCache();

        Notification::make()
            ->title('General settings saved successfully')
            ->success()
            ->send();

        $this->loadSettings();
    }

    protected function getTimezoneOptions(): array
    {
        return [
            'Asia/Kolkata' => 'Asia/Kolkata (IST)',
            'UTC' => 'UTC',
            'America/New_York' => 'America/New_York (EST)',
            'Europe/London' => 'Europe/London (GMT)',
            'Asia/Dubai' => 'Asia/Dubai (GST)',
            'Asia/Singapore' => 'Asia/Singapore (SGT)',
        ];
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
