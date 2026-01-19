<?php

namespace App\Filament\Pages\Settings;

use App\Core\Services\AppConfigService;
use App\Core\Services\AuditService;
use App\Models\Language;
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

class LanguageSettings extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $slug = 'settings/languages';
    protected static ?string $navigationIcon = 'heroicon-o-language';
    protected static string $view = 'filament.pages.settings.language-settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Languages';
    protected static ?int $navigationSort = 7;

    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('settings.languages.view'), 403);
        $this->loadDefaultLanguage();
    }

    protected function loadDefaultLanguage(): void
    {
        $default = Language::getDefault();
        $this->data = [
            'default_language_id' => $default?->id,
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Default Language')
                    ->description('Select the default language for your store')
                    ->schema([
                        Forms\Components\Select::make('default_language_id')
                            ->label('Default Language')
                            ->options(function () {
                                return Language::where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable()
                            ->helperText('This language will be used as fallback if translations are missing'),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Language::query())
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('native_name')
                    ->label('Native Name')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_rtl')
                    ->label('RTL')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default')
                    ->placeholder('All')
                    ->trueLabel('Default only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true)
                            ->helperText('ISO language code (e.g., en, hi, ta)'),
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('native_name')
                            ->label('Native Name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Toggle::make('is_rtl')
                            ->label('Right-to-Left')
                            ->helperText('Enable for Arabic, Hebrew, etc.'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->required()
                            ->disabled(fn (Language $record) => $record->is_default)
                            ->helperText(fn (Language $record) => $record->is_default ? 'Default language must always be active' : ''),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                    ])
                    ->mutateFormDataUsing(function (array $data, Language $record): array {
                        // Ensure default language is always active
                        if ($record->is_default) {
                            $data['is_active'] = true;
                        }
                        $data['updated_by'] = auth()->id();
                        return $data;
                    })
                    ->after(function (Language $record) {
                        // Ensure default is always active
                        if ($record->is_default) {
                            $record->update(['is_active' => true]);
                        }
                        AuditService::log('language.updated', $record, [], [], ['module' => 'settings']);
                        app(AppConfigService::class)->clearCache();
                    }),
                Tables\Actions\Action::make('toggle')
                    ->label(fn (Language $record) => $record->is_active ? 'Disable' : 'Enable')
                    ->icon(fn (Language $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Language $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Language $record) {
                        $oldValue = $record->is_active;
                        
                        // Prevent disabling the default language
                        if ($record->is_default && !$oldValue) {
                            Notification::make()
                                ->title('Cannot disable default language')
                                ->body('Please set another language as default first')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        // Check if this is the last default language
                        $defaultCount = Language::where('is_default', true)->count();
                        if ($record->is_default && $defaultCount === 1 && $oldValue) {
                            Notification::make()
                                ->title('Cannot disable last default language')
                                ->body('At least one language must be set as default. Please set another language as default first.')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        $record->update([
                            'is_active' => !$oldValue,
                            'updated_by' => auth()->id(),
                        ]);
                        
                        AuditService::log('language.toggled', $record, ['is_active' => $oldValue], ['is_active' => !$oldValue], ['module' => 'settings']);
                        app(AppConfigService::class)->clearCache();
                    }),
                Tables\Actions\Action::make('set_default')
                    ->label('Set as Default')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Language $record) => !$record->is_default && $record->is_active)
                    ->action(function (Language $record) {
                        // Ensure at least one default exists
                        $currentDefaultCount = Language::where('is_default', true)->count();
                        
                        DB::transaction(function () use ($record) {
                            // Unset current default (but keep at least one if this is the only one)
                            $defaults = Language::where('is_default', true)->get();
                            if ($defaults->count() > 1 || !$defaults->contains('id', $record->id)) {
                                Language::where('is_default', true)->update(['is_default' => false]);
                            }
                            
                            // Set new default
                            $record->update([
                                'is_default' => true,
                                'is_active' => true, // Ensure default is always active
                                'updated_by' => auth()->id(),
                            ]);
                        });
                        
                        AuditService::log('language.default_changed', $record, [], [], ['module' => 'settings']);
                        app(AppConfigService::class)->clearCache();
                        
                        Notification::make()
                            ->title('Default language updated')
                            ->body("{$record->name} is now the default language")
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Language')
                    ->form([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(10)
                            ->unique('languages', 'code')
                            ->helperText('ISO language code (e.g., en, hi, ta)'),
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('native_name')
                            ->label('Native Name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Toggle::make('is_rtl')
                            ->label('Right-to-Left')
                            ->helperText('Enable for Arabic, Hebrew, etc.'),
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
                    ->after(function (Language $record) {
                        AuditService::log('language.created', $record, [], [], ['module' => 'settings']);
                        app(AppConfigService::class)->clearCache();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'is_active' => true,
                                    'updated_by' => auth()->id(),
                                ]);
                            }
                            app(AppConfigService::class)->clearCache();
                            Notification::make()
                                ->title(count($records) . ' languages activated')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $defaultCount = Language::where('is_default', true)->count();
                            $deactivated = 0;
                            $skipped = 0;
                            
                            foreach ($records as $record) {
                                // Prevent deactivating default languages
                                if ($record->is_default) {
                                    $skipped++;
                                    continue;
                                }
                                
                                // Ensure at least one default remains
                                if ($defaultCount <= 1 && $record->is_default) {
                                    $skipped++;
                                    continue;
                                }
                                
                                $record->update([
                                    'is_active' => false,
                                    'updated_by' => auth()->id(),
                                ]);
                                $deactivated++;
                            }
                            
                            app(AppConfigService::class)->clearCache();
                            
                            if ($skipped > 0) {
                                Notification::make()
                                    ->title('Some languages could not be deactivated')
                                    ->body("Deactivated: {$deactivated}, Skipped (default): {$skipped}")
                                    ->warning()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title("{$deactivated} languages deactivated")
                                    ->success()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('settings.languages.edit'), 403);

        $data = $this->form->getState();
        
        if (isset($data['default_language_id'])) {
            $newDefault = Language::findOrFail($data['default_language_id']);
            
            // Ensure the new default is active
            if (!$newDefault->is_active) {
                Notification::make()
                    ->title('Cannot set inactive language as default')
                    ->body('Please activate the language first')
                    ->danger()
                    ->send();
                return;
            }
            
            DB::transaction(function () use ($data, $newDefault) {
                // Unset current default
                Language::where('is_default', true)->update(['is_default' => false]);
                
                // Set new default and ensure it's active
                $newDefault->update([
                    'is_default' => true,
                    'is_active' => true, // Default must always be active
                    'updated_by' => auth()->id(),
                ]);
            });
            
            AuditService::log('settings.languages_updated', null, [], ['default_language_id' => $data['default_language_id']], ['module' => 'settings']);
            app(AppConfigService::class)->clearCache();
            
            Notification::make()
                ->title('Default language saved')
                ->body("{$newDefault->name} is now the default language")
                ->success()
                ->send();
        }
        
        $this->loadDefaultLanguage();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Save Default Language')
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
        return $user->can('settings.languages.view') || $user->hasRole(['super_admin', 'Super Admin']);
    }
    
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}

