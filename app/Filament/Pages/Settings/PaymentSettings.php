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

class PaymentSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'settings/payment';
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static string $view = 'filament.pages.settings.payment-settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Payments';
    protected static ?int $navigationSort = 3;

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
            'enable_cod' => $settings->get(SettingKeys::ENABLE_COD, true),
            'cod_max_amount' => $settings->get(SettingKeys::COD_MAX_AMOUNT, 5000),
            'cod_pincodes' => $settings->get(SettingKeys::COD_PINCODES, []),
            'razorpay_enabled' => $settings->get(SettingKeys::RAZORPAY_ENABLED, false),
            'razorpay_environment' => $settings->get(SettingKeys::RAZORPAY_ENVIRONMENT, 'sandbox'),
            'razorpay_credential_id' => $settings->get(SettingKeys::RAZORPAY_CREDENTIAL_ID, null),
            'payu_enabled' => $settings->get(SettingKeys::PAYU_ENABLED, false),
            'payu_environment' => $settings->get(SettingKeys::PAYU_ENVIRONMENT, 'sandbox'),
            'payu_credential_id' => $settings->get(SettingKeys::PAYU_CREDENTIAL_ID, null),
            'stripe_enabled' => $settings->get(SettingKeys::STRIPE_ENABLED, false),
            'stripe_environment' => $settings->get(SettingKeys::STRIPE_ENVIRONMENT, 'sandbox'),
            'stripe_credential_id' => $settings->get(SettingKeys::STRIPE_CREDENTIAL_ID, null),
            'cashfree_enabled' => $settings->get(SettingKeys::CASHFREE_ENABLED, false),
            'cashfree_environment' => $settings->get(SettingKeys::CASHFREE_ENVIRONMENT, 'sandbox'),
            'cashfree_credential_id' => $settings->get(SettingKeys::CASHFREE_CREDENTIAL_ID, null),
            'phonepe_enabled' => $settings->get(SettingKeys::PHONEPE_ENABLED, false),
            'phonepe_environment' => $settings->get(SettingKeys::PHONEPE_ENVIRONMENT, 'sandbox'),
            'phonepe_credential_id' => $settings->get(SettingKeys::PHONEPE_CREDENTIAL_ID, null),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Cash on Delivery (COD)')
                    ->schema([
                        Forms\Components\Toggle::make('enable_cod')
                            ->label('Enable COD')
                            ->default(true)
                            ->helperText('Allow customers to pay cash on delivery'),
                        Forms\Components\TextInput::make('cod_max_amount')
                            ->label('Maximum COD Amount')
                            ->numeric()
                            ->default(5000)
                            ->prefix('₹')
                            ->helperText('Maximum order value for COD')
                            ->visible(fn ($get) => $get('enable_cod')),
                        Forms\Components\TagsInput::make('cod_pincodes')
                            ->label('COD Pincodes (Optional)')
                            ->placeholder('Enter pincode')
                            ->helperText('Leave empty to allow COD for all pincodes. Enter specific pincodes to restrict.')
                            ->visible(fn ($get) => $get('enable_cod')),
                    ]),

                Forms\Components\Section::make('Payment Gateways')
                    ->schema([
                        $this->getGatewaySection('razorpay', 'Razorpay'),
                        $this->getGatewaySection('payu', 'PayU'),
                        $this->getGatewaySection('stripe', 'Stripe'),
                        $this->getGatewaySection('cashfree', 'Cashfree'),
                        $this->getGatewaySection('phonepe', 'PhonePe'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getGatewaySection(string $gateway, string $label): Forms\Components\Section
    {
        $enabledKey = strtoupper($gateway) . '_ENABLED';
        $envKey = strtoupper($gateway) . '_ENVIRONMENT';
        $credKey = strtoupper($gateway) . '_CREDENTIAL_ID';
        
        return Forms\Components\Section::make($label)
            ->schema([
                Forms\Components\Toggle::make("{$gateway}_enabled")
                    ->label("Enable {$label}")
                    ->default(false),
                Forms\Components\Select::make("{$gateway}_environment")
                    ->label('Environment')
                    ->options([
                        'sandbox' => 'Sandbox (Test)',
                        'production' => 'Production (Live)',
                    ])
                    ->default('sandbox')
                    ->required()
                    ->visible(fn ($get) => $get("{$gateway}_enabled")),
                Forms\Components\Select::make("{$gateway}_credential_id")
                    ->label('Credentials')
                    ->options(function () use ($gateway) {
                        return Provider::where('type', 'payment')
                            ->where('name', $gateway)
                            ->where('is_enabled', true)
                            ->pluck('label', 'id');
                    })
                    ->searchable()
                    ->helperText('Select provider credentials')
                    ->placeholder('Select credentials')
                    ->visible(fn ($get) => $get("{$gateway}_enabled")),
            ])
            ->collapsible()
            ->collapsed();
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('settings.edit'), 403);

        $data = $this->form->getState();
        $settings = app(SettingsService::class);
        
        $keys = array_keys($this->data);
        $before = $settings->snapshot($keys);

        DB::transaction(function () use ($data, $settings) {
            $settings->set(SettingKeys::ENABLE_COD, $data['enable_cod'] ?? true, 'payment', 'boolean', true);
            $settings->set(SettingKeys::COD_MAX_AMOUNT, $data['cod_max_amount'] ?? 5000, 'payment', 'float', true);
            $settings->set(SettingKeys::COD_PINCODES, $data['cod_pincodes'] ?? [], 'payment', 'json', false);
            
            $gatewayMap = [
                'razorpay' => [SettingKeys::RAZORPAY_ENABLED, SettingKeys::RAZORPAY_ENVIRONMENT, SettingKeys::RAZORPAY_CREDENTIAL_ID],
                'payu' => [SettingKeys::PAYU_ENABLED, SettingKeys::PAYU_ENVIRONMENT, SettingKeys::PAYU_CREDENTIAL_ID],
                'stripe' => [SettingKeys::STRIPE_ENABLED, SettingKeys::STRIPE_ENVIRONMENT, SettingKeys::STRIPE_CREDENTIAL_ID],
                'cashfree' => [SettingKeys::CASHFREE_ENABLED, SettingKeys::CASHFREE_ENVIRONMENT, SettingKeys::CASHFREE_CREDENTIAL_ID],
                'phonepe' => [SettingKeys::PHONEPE_ENABLED, SettingKeys::PHONEPE_ENVIRONMENT, SettingKeys::PHONEPE_CREDENTIAL_ID],
            ];
            
            foreach ($gatewayMap as $gateway => [$enabledKey, $envKey, $credKey]) {
                $settings->set($enabledKey, $data["{$gateway}_enabled"] ?? false, 'payment', 'boolean', true);
                if ($data["{$gateway}_enabled"] ?? false) {
                    $settings->set($envKey, $data["{$gateway}_environment"] ?? 'sandbox', 'payment', 'string', false);
                    $settings->set($credKey, $data["{$gateway}_credential_id"] ?? null, 'payment', 'integer', false);
                }
            }
        });

        $after = $settings->snapshot($keys);

        AuditService::log('settings.payment_updated', null, $before, $after, ['module' => 'settings']);
        app(AppConfigService::class)->clearCache();

        Notification::make()
            ->title('Payment settings saved successfully')
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
