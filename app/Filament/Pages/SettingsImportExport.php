<?php

namespace App\Filament\Pages;

use App\Core\Services\AuditService;
use App\Models\Brand;
use App\Models\Module;
use App\Models\Setting;
use App\Models\Theme;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SettingsImportExport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static string $view = 'filament.pages.settings-import-export';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Import/Export Settings';
    protected static ?int $navigationSort = 2;

    public ?array $data = [];
    public ?string $exportJson = null;
    public ?array $importPreview = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);
        abort_unless(auth()->user()?->can('system.developer_tools.view'), 403);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Export Settings')
                    ->schema([
                        Forms\Components\Placeholder::make('export_info')
                            ->label('')
                            ->content('Export all settings, modules, and branding configuration (excluding secrets) to a JSON file.'),
                        Forms\Components\Textarea::make('export_json')
                            ->label('Exported JSON')
                            ->rows(10)
                            ->disabled()
                            ->visible(fn () => $this->exportJson !== null),
                    ]),

                Forms\Components\Section::make('Import Settings')
                    ->schema([
                        Forms\Components\FileUpload::make('import_file')
                            ->label('Import JSON File')
                            ->acceptedFileTypes(['application/json'])
                            ->disk('local')
                            ->directory('imports')
                            ->helperText('Upload a JSON file exported from this system'),
                        Forms\Components\Textarea::make('import_json')
                            ->label('Or Paste JSON')
                            ->rows(10)
                            ->helperText('Paste the JSON content directly'),
                    ]),

                Forms\Components\Section::make('Import Preview')
                    ->schema([
                        Forms\Components\Placeholder::make('preview')
                            ->label('Preview')
                            ->content(fn () => $this->importPreview ? json_encode($this->importPreview, JSON_PRETTY_PRINT) : 'No preview available')
                            ->visible(fn () => $this->importPreview !== null),
                    ])
                    ->visible(fn () => $this->importPreview !== null),
            ])
            ->statePath('data');
    }

    public function export(): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $export = [
            'version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'exported_by' => auth()->user()->email,
            'settings' => [],
            'modules' => [],
            'branding' => [],
            'themes' => [],
        ];

        // Export settings (excluding secrets)
        $settings = Setting::where('is_encrypted', false)->get();
        foreach ($settings as $setting) {
            $export['settings'][] = [
                'key' => $setting->key,
                'value' => $setting->value,
                'type' => $setting->type,
                'group' => $setting->group,
                'description' => $setting->description,
            ];
        }

        // Export modules
        $modules = Module::all();
        foreach ($modules as $module) {
            $export['modules'][] = [
                'key' => $module->key,
                'name' => $module->name,
                'is_enabled' => $module->is_enabled,
                'platforms' => $module->platforms,
                'min_app_version' => $module->min_app_version,
                'starts_at' => $module->starts_at?->toIso8601String(),
                'ends_at' => $module->ends_at?->toIso8601String(),
                'metadata' => $module->metadata,
            ];
        }

        // Export branding (references only, not files)
        $brands = Brand::all();
        foreach ($brands as $brand) {
            $export['branding'][] = [
                'name' => $brand->name,
                'short_name' => $brand->short_name,
                'company_name' => $brand->company_name,
                'logo_light_path' => $brand->logo_light_path,
                'logo_dark_path' => $brand->logo_dark_path,
                'favicon_path' => $brand->favicon_path,
                'support_email' => $brand->support_email,
                'support_phone' => $brand->support_phone,
            ];
        }

        // Export themes (references only)
        $themes = Theme::all();
        foreach ($themes as $theme) {
            $export['themes'][] = [
                'name' => $theme->name,
                'tokens_json' => $theme->tokens_json,
                'mode' => $theme->mode,
            ];
        }

        $this->exportJson = json_encode($export, JSON_PRETTY_PRINT);

        AuditService::log('system.settings_exported', null, [], ['exported_at' => now()], ['module' => 'system']);

        Notification::make()
            ->title('Settings exported successfully')
            ->success()
            ->send();
    }

    public function previewImport(): void
    {
        $data = $this->form->getState();
        $jsonContent = null;

        if (!empty($data['import_file'])) {
            $path = Storage::disk('local')->path($data['import_file']);
            $jsonContent = file_get_contents($path);
        } elseif (!empty($data['import_json'])) {
            $jsonContent = $data['import_json'];
        }

        if (!$jsonContent) {
            Notification::make()
                ->title('No import data provided')
                ->warning()
                ->send();
            return;
        }

        try {
            $import = json_decode($jsonContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON: ' . json_last_error_msg());
            }

            // Validate structure
            if (!isset($import['version']) || !isset($import['settings'])) {
                throw new \Exception('Invalid export format');
            }

            $this->importPreview = [
                'version' => $import['version'] ?? 'unknown',
                'exported_at' => $import['exported_at'] ?? 'unknown',
                'settings_count' => count($import['settings'] ?? []),
                'modules_count' => count($import['modules'] ?? []),
                'branding_count' => count($import['branding'] ?? []),
                'themes_count' => count($import['themes'] ?? []),
            ];

            Notification::make()
                ->title('Import preview generated')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Import validation failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function executeImport(): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        $data = $this->form->getState();
        $jsonContent = null;

        if (!empty($data['import_file'])) {
            $path = Storage::disk('local')->path($data['import_file']);
            $jsonContent = file_get_contents($path);
        } elseif (!empty($data['import_json'])) {
            $jsonContent = $data['import_json'];
        }

        if (!$jsonContent) {
            Notification::make()
                ->title('No import data provided')
                ->warning()
                ->send();
            return;
        }

        try {
            $import = json_decode($jsonContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON: ' . json_last_error_msg());
            }

            DB::transaction(function () use ($import) {
                // Import settings
                if (isset($import['settings'])) {
                    foreach ($import['settings'] as $setting) {
                        Setting::updateOrCreate(
                            ['key' => $setting['key']],
                            [
                                'value' => $setting['value'],
                                'type' => $setting['type'] ?? 'string',
                                'group' => $setting['group'] ?? 'general',
                                'description' => $setting['description'] ?? null,
                            ]
                        );
                    }
                }

                // Import modules
                if (isset($import['modules'])) {
                    foreach ($import['modules'] as $module) {
                        Module::updateOrCreate(
                            ['key' => $module['key']],
                            [
                                'name' => $module['name'],
                                'is_enabled' => $module['is_enabled'] ?? true,
                                'platforms' => $module['platforms'] ?? ['web', 'app'],
                                'min_app_version' => $module['min_app_version'] ?? null,
                                'starts_at' => $module['starts_at'] ? \Carbon\Carbon::parse($module['starts_at']) : null,
                                'ends_at' => $module['ends_at'] ? \Carbon\Carbon::parse($module['ends_at']) : null,
                                'metadata' => $module['metadata'] ?? [],
                            ]
                        );
                    }
                }

                // Note: Branding and themes are references only, not imported
            });

            AuditService::log('system.settings_imported', null, [], [
                'imported_at' => now(),
                'settings_count' => count($import['settings'] ?? []),
                'modules_count' => count($import['modules'] ?? []),
            ], ['module' => 'system']);

            Notification::make()
                ->title('Settings imported successfully')
                ->success()
                ->send();

            $this->importPreview = null;
            $this->form->fill([]);
        } catch (\Exception $e) {
            Notification::make()
                ->title('Import failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('export')
                ->label('Export Settings')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action('export'),
            Forms\Components\Actions\Action::make('preview')
                ->label('Preview Import')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action('previewImport'),
            Forms\Components\Actions\Action::make('import')
                ->label('Execute Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->action('executeImport')
                ->requiresConfirmation()
                ->visible(fn () => $this->importPreview !== null),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') && auth()->user()?->can('system.developer_tools.view') ?? false;
    }
}

