<?php

namespace App\Filament\Pages\Settings;

use App\Core\Services\AppConfigService;
use App\Core\Services\AuditService;
use App\Core\Services\CurrencyService;
use App\Core\Services\SettingsService;
use App\Core\Settings\SettingKeys;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CurrencySettings extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $slug = 'settings/currencies';
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static string $view = 'filament.pages.settings.currency-settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Currencies';
    protected static ?int $navigationSort = 9;

    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('settings.currencies.view'), 403);
        $this->loadSettings();
    }

    protected function loadSettings(): void
    {
        $settings = app(SettingsService::class);
        $currencyService = app(CurrencyService::class);
        $default = $currencyService->getDefaultCurrency();
        
        $this->data = [
            'default_currency_id' => $default?->id,
            'currency_selection_enabled' => $settings->get('currency_selection_enabled', false),
            'currency_auto_convert' => $settings->get('currency_auto_convert', false),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Default Currency')
                    ->description('Configure default currency and conversion settings')
                    ->schema([
                        Forms\Components\Select::make('default_currency_id')
                            ->label('Default Currency')
                            ->options(function () {
                                return Currency::where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable()
                            ->helperText('All product prices are stored in this currency'),
                        Forms\Components\Toggle::make('currency_selection_enabled')
                            ->label('Allow customers to choose currency')
                            ->helperText('If enabled, customers can select a different currency in the app/web'),
                        Forms\Components\Toggle::make('currency_auto_convert')
                            ->label('Auto convert prices using exchange rates')
                            ->helperText('If disabled, only default currency will be available')
                            ->visible(fn ($get) => $get('currency_selection_enabled')),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Currency::query())
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('symbol')
                    ->label('Symbol')
                    ->formatStateUsing(fn (Currency $record) => $record->symbol . ' (' . $record->symbol_position . ')'),
                Tables\Columns\TextColumn::make('decimals')
                    ->label('Decimals')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true)
                            ->helperText('ISO currency code (e.g., INR, USD)'),
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('symbol')
                            ->label('Symbol')
                            ->required()
                            ->maxLength(10),
                        Forms\Components\Select::make('symbol_position')
                            ->label('Symbol Position')
                            ->options([
                                'before' => 'Before amount',
                                'after' => 'After amount',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('decimals')
                            ->label('Decimal Places')
                            ->numeric()
                            ->default(2)
                            ->required(),
                        Forms\Components\TextInput::make('thousand_separator')
                            ->label('Thousand Separator')
                            ->maxLength(5)
                            ->default(',')
                            ->required(),
                        Forms\Components\TextInput::make('decimal_separator')
                            ->label('Decimal Separator')
                            ->maxLength(5)
                            ->default('.')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->required()
                            ->disabled(fn (Currency $record) => $record->is_default)
                            ->helperText(fn (Currency $record) => $record->is_default ? 'Default currency must always be active' : ''),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                    ])
                    ->mutateFormDataUsing(function (array $data, Currency $record): array {
                        // Ensure default currency is always active
                        if ($record->is_default) {
                            $data['is_active'] = true;
                        }
                        $data['updated_by'] = auth()->id();
                        return $data;
                    })
                    ->after(function (Currency $record) {
                        // Ensure default is always active
                        if ($record->is_default) {
                            $record->update(['is_active' => true]);
                        }
                        AuditService::log('currency.updated', $record, [], [], ['module' => 'settings']);
                        app(CurrencyService::class)->clearCache();
                        app(AppConfigService::class)->clearCache();
                    }),
                Tables\Actions\Action::make('toggle')
                    ->label(fn (Currency $record) => $record->is_active ? 'Disable' : 'Enable')
                    ->icon(fn (Currency $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Currency $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Currency $record) {
                        $oldValue = $record->is_active;
                        
                        // Prevent disabling the default currency
                        if ($record->is_default && !$oldValue) {
                            Notification::make()
                                ->title('Cannot disable default currency')
                                ->body('Please set another currency as default first')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        // Check if this is the last default currency
                        $defaultCount = Currency::where('is_default', true)->count();
                        if ($record->is_default && $defaultCount === 1 && $oldValue) {
                            Notification::make()
                                ->title('Cannot disable last default currency')
                                ->body('At least one currency must be set as default. Please set another currency as default first.')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        $record->update([
                            'is_active' => !$oldValue,
                            'updated_by' => auth()->id(),
                        ]);
                        
                        AuditService::log('currency.toggled', $record, ['is_active' => $oldValue], ['is_active' => !$oldValue], ['module' => 'settings']);
                        app(CurrencyService::class)->clearCache();
                        app(AppConfigService::class)->clearCache();
                    }),
                Tables\Actions\Action::make('set_default')
                    ->label('Set as Default')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Currency $record) => !$record->is_default && $record->is_active)
                    ->action(function (Currency $record) {
                        // Ensure at least one default exists
                        $currentDefaultCount = Currency::where('is_default', true)->count();
                        
                        DB::transaction(function () use ($record) {
                            // Unset current default (but keep at least one if this is the only one)
                            $defaults = Currency::where('is_default', true)->get();
                            if ($defaults->count() > 1 || !$defaults->contains('id', $record->id)) {
                                Currency::where('is_default', true)->update(['is_default' => false]);
                            }
                            
                            // Set new default
                            $record->update([
                                'is_default' => true,
                                'is_active' => true, // Ensure default is always active
                                'updated_by' => auth()->id(),
                            ]);
                        });
                        
                        AuditService::log('currency.default_changed', $record, [], [], ['module' => 'settings']);
                        app(CurrencyService::class)->clearCache();
                        app(AppConfigService::class)->clearCache();
                        
                        Notification::make()
                            ->title('Default currency updated')
                            ->body("{$record->name} ({$record->code}) is now the default currency")
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Currency')
                    ->form([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(10)
                            ->unique('currencies', 'code')
                            ->helperText('ISO currency code (e.g., INR, USD)'),
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('symbol')
                            ->label('Symbol')
                            ->required()
                            ->maxLength(10),
                        Forms\Components\Select::make('symbol_position')
                            ->label('Symbol Position')
                            ->options([
                                'before' => 'Before amount',
                                'after' => 'After amount',
                            ])
                            ->default('before')
                            ->required(),
                        Forms\Components\TextInput::make('decimals')
                            ->label('Decimal Places')
                            ->numeric()
                            ->default(2)
                            ->required(),
                        Forms\Components\TextInput::make('thousand_separator')
                            ->label('Thousand Separator')
                            ->maxLength(5)
                            ->default(',')
                            ->required(),
                        Forms\Components\TextInput::make('decimal_separator')
                            ->label('Decimal Separator')
                            ->maxLength(5)
                            ->default('.')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->required(),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        $data['updated_by'] = auth()->id();
                        return $data;
                    })
                    ->after(function (Currency $record) {
                        AuditService::log('currency.created', $record, [], [], ['module' => 'settings']);
                        app(CurrencyService::class)->clearCache();
                        app(AppConfigService::class)->clearCache();
                    }),
            ]);
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('settings.currencies.edit'), 403);

        $data = $this->form->getState();
        $settings = app(SettingsService::class);
        
        $before = [
            'default_currency_id' => $this->data['default_currency_id'] ?? null,
            'currency_selection_enabled' => $this->data['currency_selection_enabled'] ?? false,
            'currency_auto_convert' => $this->data['currency_auto_convert'] ?? false,
        ];

        DB::transaction(function () use ($data, $settings) {
            if (isset($data['default_currency_id'])) {
                $newDefault = Currency::findOrFail($data['default_currency_id']);
                
                // Ensure the new default is active
                if (!$newDefault->is_active) {
                    Notification::make()
                        ->title('Cannot set inactive currency as default')
                        ->body('Please activate the currency first')
                        ->danger()
                        ->send();
                    return;
                }
                
                Currency::where('is_default', true)->update(['is_default' => false]);
                $newDefault->update([
                    'is_default' => true,
                    'is_active' => true, // Default must always be active
                    'updated_by' => auth()->id(),
                ]);
            }
            
            $settings->set('currency_selection_enabled', $data['currency_selection_enabled'] ?? false, 'currency', 'boolean', false);
            $settings->set('currency_auto_convert', $data['currency_auto_convert'] ?? false, 'currency', 'boolean', false);
        });

        $after = [
            'default_currency_id' => $data['default_currency_id'] ?? null,
            'currency_selection_enabled' => $data['currency_selection_enabled'] ?? false,
            'currency_auto_convert' => $data['currency_auto_convert'] ?? false,
        ];

        AuditService::log('settings.currencies_updated', null, $before, $after, ['module' => 'settings']);
        app(CurrencyService::class)->clearCache();
        app(AppConfigService::class)->clearCache();

        $newDefault = isset($data['default_currency_id']) ? Currency::find($data['default_currency_id']) : null;
        
        Notification::make()
            ->title('Currency settings saved')
            ->body($newDefault ? "{$newDefault->name} ({$newDefault->code}) is now the default currency" : 'Settings updated successfully')
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
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        return $user->can('settings.currencies.view') || $user->hasRole(['super_admin', 'Super Admin']);
    }
    
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}

