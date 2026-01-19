<?php

namespace App\Filament\Pages;

use App\Core\Services\AppConfigService;
use App\Core\Services\AuditService;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class SettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static string $view = 'filament.pages.settings-page';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Developer Tools: Advanced Settings';
    protected static ?int $navigationSort = 999; // Last in group

    public ?array $data = [];

    public ?string $confirmation = '';
    public bool $confirmed = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);
        abort_unless(auth()->user()?->can('system.developer_tools.view'), 403);

        $this->loadSettings();
    }

    protected function loadSettings(): void
    {
        $this->data = [
            // General
            'app_name' => Setting::getTyped('app_name', 'ZyptoseComm'),
            'currency' => Setting::getTyped('currency', 'INR'),
            'timezone' => Setting::getTyped('timezone', 'Asia/Kolkata'),
            'support_email' => Setting::getTyped('support_email', ''),
            'support_phone' => Setting::getTyped('support_phone', ''),

            // Store
            'store_address' => Setting::getTyped('store_address', ''),
            'store_city' => Setting::getTyped('store_city', ''),
            'store_state' => Setting::getTyped('store_state', ''),
            'store_pincode' => Setting::getTyped('store_pincode', ''),
            'store_gstin' => Setting::getTyped('store_gstin', ''),

            // Localization
            'default_language' => Setting::getTyped('default_language', 'en'),
            'supported_languages' => Setting::getTyped('supported_languages', ['en']),

            // Tax
            'gst_enabled' => Setting::getTyped('gst_enabled', false),
            'gst_inclusive' => Setting::getTyped('gst_inclusive', false),
            'default_gst_rate' => Setting::getTyped('default_gst_rate', 18.0),

            // Shipping
            'default_weight_unit' => Setting::getTyped('default_weight_unit', 'kg'),
            'default_dim_unit' => Setting::getTyped('default_dim_unit', 'cm'),
            'free_shipping_min_cart' => Setting::getTyped('free_shipping_min_cart', 0),

            // Notifications
            'quiet_hours_enabled' => Setting::getTyped('quiet_hours_enabled', false),
            'quiet_hours_start' => Setting::getTyped('quiet_hours_start', '22:00'),
            'quiet_hours_end' => Setting::getTyped('quiet_hours_end', '08:00'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Settings')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->schema([
                                Forms\Components\TextInput::make('app_name')
                                    ->label('App Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('currency')
                                    ->label('Currency')
                                    ->required()
                                    ->maxLength(3)
                                    ->default('INR'),
                                Forms\Components\Select::make('timezone')
                                    ->label('Timezone')
                                    ->options($this->getTimezoneOptions())
                                    ->required()
                                    ->searchable(),
                                Forms\Components\TextInput::make('support_email')
                                    ->label('Support Email')
                                    ->email()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('support_phone')
                                    ->label('Support Phone')
                                    ->tel()
                                    ->maxLength(20),
                            ]),

                        Tabs\Tab::make('Store')
                            ->schema([
                                Forms\Components\Textarea::make('store_address')
                                    ->label('Store Address')
                                    ->rows(3),
                                Forms\Components\TextInput::make('store_city')
                                    ->label('City')
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('store_state')
                                    ->label('State')
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('store_pincode')
                                    ->label('Pincode')
                                    ->maxLength(10),
                                Forms\Components\TextInput::make('store_gstin')
                                    ->label('GSTIN')
                                    ->maxLength(15),
                            ]),

                        Tabs\Tab::make('Localization')
                            ->schema([
                                Forms\Components\Select::make('default_language')
                                    ->label('Default Language')
                                    ->options(['en' => 'English', 'hi' => 'Hindi', 'ta' => 'Tamil', 'te' => 'Telugu'])
                                    ->required(),
                                Forms\Components\TagsInput::make('supported_languages')
                                    ->label('Supported Languages')
                                    ->placeholder('Add language code')
                                    ->helperText('Enter language codes (e.g., en, hi, ta)'),
                            ]),

                        Tabs\Tab::make('Tax')
                            ->schema([
                                Forms\Components\Toggle::make('gst_enabled')
                                    ->label('Enable GST')
                                    ->default(false),
                                Forms\Components\Toggle::make('gst_inclusive')
                                    ->label('GST Inclusive Pricing')
                                    ->default(false),
                                Forms\Components\TextInput::make('default_gst_rate')
                                    ->label('Default GST Rate (%)')
                                    ->numeric()
                                    ->default(18.0)
                                    ->minValue(0)
                                    ->maxValue(100),
                            ]),

                        Tabs\Tab::make('Shipping')
                            ->schema([
                                Forms\Components\Select::make('default_weight_unit')
                                    ->label('Default Weight Unit')
                                    ->options(['kg' => 'Kilograms', 'g' => 'Grams', 'lb' => 'Pounds', 'oz' => 'Ounces'])
                                    ->required(),
                                Forms\Components\Select::make('default_dim_unit')
                                    ->label('Default Dimension Unit')
                                    ->options(['cm' => 'Centimeters', 'm' => 'Meters', 'in' => 'Inches', 'ft' => 'Feet'])
                                    ->required(),
                                Forms\Components\TextInput::make('free_shipping_min_cart')
                                    ->label('Free Shipping Minimum Cart Value')
                                    ->numeric()
                                    ->default(0)
                                    ->prefix('₹'),
                            ]),

                        Tabs\Tab::make('Notifications')
                            ->schema([
                                Forms\Components\Toggle::make('quiet_hours_enabled')
                                    ->label('Enable Quiet Hours')
                                    ->default(false),
                                Forms\Components\TimePicker::make('quiet_hours_start')
                                    ->label('Quiet Hours Start')
                                    ->default('22:00')
                                    ->visible(fn ($get) => $get('quiet_hours_enabled')),
                                Forms\Components\TimePicker::make('quiet_hours_end')
                                    ->label('Quiet Hours End')
                                    ->default('08:00')
                                    ->visible(fn ($get) => $get('quiet_hours_enabled')),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);
        abort_unless(auth()->user()?->can('system.developer_tools.view'), 403);

        $data = $this->form->getState();
        
        // Require confirmation
        if (!($data['confirmed'] ?? false) || ($data['confirmation'] ?? '') !== 'CONFIRM') {
            Notification::make()
                ->title('Confirmation required')
                ->body('You must check the confirmation box and type "CONFIRM" to save changes.')
                ->danger()
                ->send();
            return;
        }
        $before = $this->getCurrentSettingsSnapshot();

        DB::transaction(function () use ($data) {
            // General
            Setting::setTyped('app_name', $data['app_name'], 'general', 'string', 'Application name');
            Setting::setTyped('currency', $data['currency'], 'general', 'string', 'Default currency');
            Setting::setTyped('timezone', $data['timezone'], 'general', 'string', 'Application timezone');
            Setting::setTyped('support_email', $data['support_email'] ?? '', 'general', 'string', 'Support email address');
            Setting::setTyped('support_phone', $data['support_phone'] ?? '', 'general', 'string', 'Support phone number');

            // Store
            Setting::setTyped('store_address', $data['store_address'] ?? '', 'store', 'string', 'Store address');
            Setting::setTyped('store_city', $data['store_city'] ?? '', 'store', 'string', 'Store city');
            Setting::setTyped('store_state', $data['store_state'] ?? '', 'store', 'string', 'Store state');
            Setting::setTyped('store_pincode', $data['store_pincode'] ?? '', 'store', 'string', 'Store pincode');
            Setting::setTyped('store_gstin', $data['store_gstin'] ?? '', 'store', 'string', 'Store GSTIN');

            // Localization
            Setting::setTyped('default_language', $data['default_language'], 'localization', 'string', 'Default language');
            Setting::setTyped('supported_languages', $data['supported_languages'] ?? ['en'], 'localization', 'json', 'Supported languages');

            // Tax
            Setting::setTyped('gst_enabled', $data['gst_enabled'] ?? false, 'tax', 'boolean', 'GST enabled');
            Setting::setTyped('gst_inclusive', $data['gst_inclusive'] ?? false, 'tax', 'boolean', 'GST inclusive pricing');
            Setting::setTyped('default_gst_rate', $data['default_gst_rate'] ?? 18.0, 'tax', 'float', 'Default GST rate');

            // Shipping
            Setting::setTyped('default_weight_unit', $data['default_weight_unit'], 'shipping', 'string', 'Default weight unit');
            Setting::setTyped('default_dim_unit', $data['default_dim_unit'], 'shipping', 'string', 'Default dimension unit');
            Setting::setTyped('free_shipping_min_cart', $data['free_shipping_min_cart'] ?? 0, 'shipping', 'float', 'Free shipping minimum cart value');

            // Notifications
            Setting::setTyped('quiet_hours_enabled', $data['quiet_hours_enabled'] ?? false, 'notifications', 'boolean', 'Quiet hours enabled');
            Setting::setTyped('quiet_hours_start', $data['quiet_hours_start'] ?? '22:00', 'notifications', 'string', 'Quiet hours start time');
            Setting::setTyped('quiet_hours_end', $data['quiet_hours_end'] ?? '08:00', 'notifications', 'string', 'Quiet hours end time');
        });

        $after = $this->getCurrentSettingsSnapshot();

        // Audit log
        AuditService::log('setting_change', null, $before, $after, ['module' => 'settings']);

        // Clear app config cache
        app(AppConfigService::class)->clearCache();

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }

    protected function getCurrentSettingsSnapshot(): array
    {
        return [
            'app_name' => Setting::getTyped('app_name'),
            'currency' => Setting::getTyped('currency'),
            'timezone' => Setting::getTyped('timezone'),
            'support_email' => Setting::getTyped('support_email'),
            'support_phone' => Setting::getTyped('support_phone'),
            'store_address' => Setting::getTyped('store_address'),
            'store_city' => Setting::getTyped('store_city'),
            'store_state' => Setting::getTyped('store_state'),
            'store_pincode' => Setting::getTyped('store_pincode'),
            'store_gstin' => Setting::getTyped('store_gstin'),
            'default_language' => Setting::getTyped('default_language'),
            'supported_languages' => Setting::getTyped('supported_languages'),
            'gst_enabled' => Setting::getTyped('gst_enabled'),
            'gst_inclusive' => Setting::getTyped('gst_inclusive'),
            'default_gst_rate' => Setting::getTyped('default_gst_rate'),
            'default_weight_unit' => Setting::getTyped('default_weight_unit'),
            'default_dim_unit' => Setting::getTyped('default_dim_unit'),
            'free_shipping_min_cart' => Setting::getTyped('free_shipping_min_cart'),
            'quiet_hours_enabled' => Setting::getTyped('quiet_hours_enabled'),
            'quiet_hours_start' => Setting::getTyped('quiet_hours_start'),
            'quiet_hours_end' => Setting::getTyped('quiet_hours_end'),
        ];
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
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Confirm Save')
                ->modalDescription('Are you sure you want to save these advanced settings? Incorrect values may break the system.')
                ->modalSubmitActionLabel('Yes, Save'),
        ];
    }

    public static function canAccess(): bool
    {
        // Only Super Admin can access developer tools
        return auth()->user()?->hasRole('super_admin') && auth()->user()?->can('system.developer_tools.view') ?? false;
    }
}
