<?php

namespace App\Filament\Pages\Settings;

use App\Core\Services\AppConfigService;
use App\Core\Services\AuditService;
use App\Models\Language;
use App\Models\Translation;
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
use Illuminate\Support\Facades\Storage;

class TranslationsManager extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $slug = 'settings/translations';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.settings.translations-manager';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Translations';
    protected static ?int $navigationSort = 8;

    public ?string $selectedLocale = null;
    public ?string $selectedGroup = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('settings.translations.view'), 403);
        
        $default = Language::getDefault();
        $this->selectedLocale = $default?->code ?? 'en';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('group')
                    ->label('Group')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('key')
                    ->label('Key')
                    ->sortable()
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Translation')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn (Translation $record) => $record->value),
                Tables\Columns\IconColumn::make('is_locked')
                    ->label('Locked')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->label('Group')
                    ->options(function () {
                        return Translation::select('group')
                            ->distinct()
                            ->pluck('group', 'group');
                    })
                    ->query(function (Builder $query, array $state): Builder {
                        if (empty($state['value'])) {
                            return $query;
                        }
                        return $query->where('group', $state['value']);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->form([
                        Forms\Components\TextInput::make('key')
                            ->label('Key')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Textarea::make('value')
                            ->label('Translation')
                            ->required()
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->fillForm(function (Translation $record) {
                        return [
                            'key' => $record->key,
                            'value' => $record->value,
                        ];
                    })
                    ->action(function (Translation $record, array $data) {
                        if ($record->is_locked) {
                            Notification::make()
                                ->title('Cannot edit locked translation')
                                ->warning()
                                ->send();
                            return;
                        }
                        
                        $record->update([
                            'value' => $data['value'],
                            'updated_by' => auth()->id(),
                        ]);
                        
                        AuditService::log('translation.updated', $record, ['value' => $record->getOriginal('value')], ['value' => $data['value']], ['module' => 'settings']);
                        $this->clearTranslationCache($record->locale);
                        
                        Notification::make()
                            ->title('Translation updated')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Translation $record) => !$record->is_locked),
            ])
            ->headerActions([
                Tables\Actions\Action::make('add')
                    ->label('Add Translation')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\Select::make('locale')
                            ->label('Language')
                            ->options(function () {
                                return Language::where('is_active', true)
                                    ->pluck('name', 'code');
                            })
                            ->default($this->selectedLocale)
                            ->required(),
                        Forms\Components\Select::make('group')
                            ->label('Group')
                            ->options([
                                'app' => 'App',
                                'auth' => 'Auth',
                                'checkout' => 'Checkout',
                                'products' => 'Products',
                                'orders' => 'Orders',
                            ])
                            ->default('app')
                            ->required(),
                        Forms\Components\TextInput::make('key')
                            ->label('Key')
                            ->required()
                            ->maxLength(255)
                            ->helperText('e.g., checkout.place_order'),
                        Forms\Components\Textarea::make('value')
                            ->label('Translation')
                            ->required()
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->action(function (array $data) {
                        $translation = Translation::updateOrCreate(
                            [
                                'group' => $data['group'],
                                'key' => $data['key'],
                                'locale' => $data['locale'],
                            ],
                            [
                                'value' => $data['value'],
                                'updated_by' => auth()->id(),
                            ]
                        );
                        
                        AuditService::log('translation.created', $translation, [], [], ['module' => 'settings']);
                        $this->clearTranslationCache($data['locale']);
                        
                        Notification::make()
                            ->title('Translation added')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('locale')
                            ->label('Language')
                            ->options(function () {
                                return Language::where('is_active', true)
                                    ->pluck('name', 'code');
                            })
                            ->default($this->selectedLocale)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $this->exportTranslations($data['locale']);
                    }),
                Tables\Actions\Action::make('import')
                    ->label('Import CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('locale')
                            ->label('Language')
                            ->options(function () {
                                return Language::where('is_active', true)
                                    ->pluck('name', 'code');
                            })
                            ->default($this->selectedLocale)
                            ->required(),
                        Forms\Components\FileUpload::make('file')
                            ->label('CSV File')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel'])
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $this->importTranslations($data['locale'], $data['file']);
                    }),
                Tables\Actions\Action::make('auto_fill')
                    ->label('Auto-fill Missing Keys')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This will copy missing translation keys from the default language.')
                    ->form([
                        Forms\Components\Select::make('target_locale')
                            ->label('Target Language')
                            ->options(function () {
                                return Language::where('is_active', true)
                                    ->pluck('name', 'code');
                            })
                            ->default($this->selectedLocale)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $this->autoFillMissingKeys($data['target_locale']);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export_selected')
                        ->label('Export Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            // Export logic for selected records
                            Notification::make()
                                ->title('Export functionality coming soon')
                                ->info()
                                ->send();
                        }),
                ]),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $query = Translation::query();
        
        if ($this->selectedLocale) {
            $query->where('locale', $this->selectedLocale);
        }
        
        if ($this->selectedGroup) {
            $query->where('group', $this->selectedGroup);
        }
        
        return $query;
    }

    protected function exportTranslations(string $locale): void
    {
        $translations = Translation::where('locale', $locale)->get();
        
        $filename = "translations_{$locale}_" . now()->format('Y-m-d') . '.csv';
        $path = storage_path("app/temp/{$filename}");
        
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        $file = fopen($path, 'w');
        fputcsv($file, ['Group', 'Key', 'Value']);
        
        foreach ($translations as $translation) {
            fputcsv($file, [
                $translation->group,
                $translation->key,
                $translation->value,
            ]);
        }
        
        fclose($file);
        
        AuditService::log('translation.exported', null, [], ['locale' => $locale, 'count' => $translations->count()], ['module' => 'settings']);
        
        Notification::make()
            ->title('Export completed')
            ->body("{$translations->count()} translations exported. File saved: {$filename}")
            ->success()
            ->send();
        
        // Note: For file download, you would typically:
        // 1. Create a download route in web.php
        // 2. Store file path in session or database
        // 3. Return redirect to download route
        // For now, file is saved to storage/app/temp/ and can be accessed via file manager
    }

    protected function importTranslations(string $locale, string $filePath): void
    {
        $fullPath = storage_path('app/' . $filePath);
        
        if (!file_exists($fullPath)) {
            Notification::make()
                ->title('File not found')
                ->danger()
                ->send();
            return;
        }
        
        $file = fopen($fullPath, 'r');
        $header = fgetcsv($file); // Skip header
        
        $imported = 0;
        $updated = 0;
        
        while (($row = fgetcsv($file)) !== false) {
            if (count($row) < 3) continue;
            
            [$group, $key, $value] = $row;
            
            $translation = Translation::updateOrCreate(
                [
                    'group' => $group,
                    'key' => $key,
                    'locale' => $locale,
                ],
                [
                    'value' => $value,
                    'updated_by' => auth()->id(),
                ]
            );
            
            if ($translation->wasRecentlyCreated) {
                $imported++;
            } else {
                $updated++;
            }
        }
        
        fclose($file);
        
        $this->clearTranslationCache($locale);
        
        AuditService::log('translation.imported', null, [], [
            'locale' => $locale,
            'imported' => $imported,
            'updated' => $updated,
        ], ['module' => 'settings']);
        
        Notification::make()
            ->title('Translations imported')
            ->body("Imported: {$imported}, Updated: {$updated}")
            ->success()
            ->send();
    }

    protected function autoFillMissingKeys(string $targetLocale): void
    {
        $default = Language::getDefault();
        if (!$default) {
            Notification::make()
                ->title('No default language set')
                ->body('Please set a default language in Settings → Languages first')
                ->warning()
                ->send();
            return;
        }
        
        $defaultTranslations = Translation::where('locale', $default->code)->get();
        
        if ($defaultTranslations->isEmpty()) {
            Notification::make()
                ->title('No translations found in default language')
                ->body('Please add some translations to the default language first')
                ->warning()
                ->send();
            return;
        }
        
        $filled = 0;
        $updated = 0;
        
        // Copy ALL translations from default to target locale
        // This will create missing ones and update existing ones
        foreach ($defaultTranslations as $defaultTranslation) {
            $existing = Translation::where('group', $defaultTranslation->group)
                ->where('key', $defaultTranslation->key)
                ->where('locale', $targetLocale)
                ->first();
            
            if ($existing) {
                // Update existing translation with default value
                $existing->update([
                    'value' => $defaultTranslation->value,
                    'updated_by' => auth()->id(),
                ]);
                $updated++;
            } else {
                // Create new translation
                Translation::create([
                    'group' => $defaultTranslation->group,
                    'key' => $defaultTranslation->key,
                    'locale' => $targetLocale,
                    'value' => $defaultTranslation->value,
                    'updated_by' => auth()->id(),
                ]);
                $filled++;
            }
        }
        
        $this->clearTranslationCache($targetLocale);
        
        $total = $filled + $updated;
        
        if ($total > 0) {
            AuditService::log('translation.auto_filled', null, [], [
                'target_locale' => $targetLocale,
                'default_locale' => $default->code,
                'filled_count' => $filled,
                'updated_count' => $updated,
                'total_count' => $total,
            ], ['module' => 'settings']);
        }
        
        Notification::make()
            ->title("Auto-filled all translations")
            ->body("Copied {$total} translations from {$default->name} ({$filled} new, {$updated} updated)")
            ->success()
            ->send();
    }

    protected function clearTranslationCache(string $locale): void
    {
        \Illuminate\Support\Facades\Cache::forget("translations:v1:{$locale}");
        app(AppConfigService::class)->clearCache();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        return $user->can('settings.translations.view') || $user->hasRole(['super_admin', 'Super Admin']);
    }
    
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}

