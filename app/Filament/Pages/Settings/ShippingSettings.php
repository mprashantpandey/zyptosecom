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

class ShippingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'settings/shipping';
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static string $view = 'filament.pages.settings.shipping-settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Shipping';
    protected static ?int $navigationSort = 4;

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
            'active_shipping_provider' => $settings->get(SettingKeys::ACTIVE_SHIPPING_PROVIDER, ''),
            'shipping_environment' => $settings->get(SettingKeys::SHIPPING_ENVIRONMENT, 'sandbox'),
            'shipping_credential_id' => $settings->get(SettingKeys::SHIPPING_CREDENTIAL_ID, null),
            'default_pickup_address' => $settings->get(SettingKeys::DEFAULT_PICKUP_ADDRESS, ''),
            'serviceability_mode' => $settings->get(SettingKeys::SERVICEABILITY_MODE, 'all'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Shipping Provider')
                    ->schema([
                        Forms\Components\Select::make('active_shipping_provider')
                            ->label('Active Shipping Provider')
                            ->options(function () {
                                return Provider::where('type', 'shipping')
                                    ->where('is_enabled', true)
                                    ->pluck('label', 'name');
                            })
                            ->searchable()
                            ->required()
                            ->helperText('Select the shipping provider to use'),
                        Forms\Components\Select::make('shipping_environment')
                            ->label('Environment')
                            ->options([
                                'sandbox' => 'Sandbox (Test)',
                                'production' => 'Production (Live)',
                            ])
                            ->required()
                            ->default('sandbox')
                            ->helperText('Test or production environment'),
                        Forms\Components\Select::make('shipping_credential_id')
                            ->label('Credentials')
                            ->options(function ($get) {
                                $providerName = $get('active_shipping_provider');
                                if (!$providerName) {
                                    return [];
                                }
                                return Provider::where('type', 'shipping')
                                    ->where('name', $providerName)
                                    ->where('is_enabled', true)
                                    ->pluck('label', 'id');
                            })
                            ->searchable()
                            ->helperText('Select provider credentials')
                            ->placeholder('Select credentials'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Pickup Address')
                    ->schema([
                        Forms\Components\Textarea::make('default_pickup_address')
                            ->label('Default Pickup Address')
                            ->rows(4)
                            ->maxLength(500)
                            ->helperText('Address from where shipments will be picked up'),
                    ]),

                Forms\Components\Section::make('Serviceability')
                    ->schema([
                        Forms\Components\Select::make('serviceability_mode')
                            ->label('Serviceability Mode')
                            ->options([
                                'all' => 'All Pincodes',
                                'pincode_list' => 'Specific Pincode List',
                            ])
                            ->required()
                            ->default('all')
                            ->helperText('Choose how to determine serviceable areas'),
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
            SettingKeys::ACTIVE_SHIPPING_PROVIDER, SettingKeys::SHIPPING_ENVIRONMENT,
            SettingKeys::SHIPPING_CREDENTIAL_ID, SettingKeys::DEFAULT_PICKUP_ADDRESS,
            SettingKeys::SERVICEABILITY_MODE,
        ];
        $before = $settings->snapshot($keys);

        DB::transaction(function () use ($data, $settings) {
            $settings->set(SettingKeys::ACTIVE_SHIPPING_PROVIDER, $data['active_shipping_provider'], 'shipping', 'string', false);
            $settings->set(SettingKeys::SHIPPING_ENVIRONMENT, $data['shipping_environment'], 'shipping', 'string', false);
            $settings->set(SettingKeys::SHIPPING_CREDENTIAL_ID, $data['shipping_credential_id'] ?? null, 'shipping', 'integer', false);
            $settings->set(SettingKeys::DEFAULT_PICKUP_ADDRESS, $data['default_pickup_address'] ?? '', 'shipping', 'string', false);
            $settings->set(SettingKeys::SERVICEABILITY_MODE, $data['serviceability_mode'], 'shipping', 'string', false);
        });

        $after = $settings->snapshot($keys);

        AuditService::log('settings.shipping_updated', null, $before, $after, ['module' => 'settings']);
        app(AppConfigService::class)->clearCache();

        Notification::make()
            ->title('Shipping settings saved successfully')
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
