<?php

namespace App\Filament\Pages;

use App\Core\Services\AppConfigService;
use App\Core\Services\AuditService;
use App\Models\AppVersion;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class AppVersionControl extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static string $view = 'filament.pages.app-version-control';
    protected static ?string $navigationGroup = 'Branding';
    protected static ?string $navigationLabel = 'App Version Control';
    protected static ?int $navigationSort = 3;

    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('branding.app_versions'), 403);

        $this->loadVersions();
    }

    protected function loadVersions(): void
    {
        $platforms = ['android', 'ios', 'web'];
        foreach ($platforms as $platform) {
            $version = AppVersion::getForPlatform($platform);
            $this->data[$platform] = [
                'latest_version' => $version->latest_version ?? '1.0.0',
                'latest_build' => $version->latest_build ?? null,
                'min_version' => $version->min_version ?? null,
                'min_build' => $version->min_build ?? null,
                'update_type' => $version->update_type ?? 'none',
                'update_message' => $version->update_message ?? '',
                'store_url' => $version->store_url ?? '',
            ];
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Platforms')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Android')
                            ->schema([
                                Forms\Components\Section::make('Android Version')
                                    ->schema($this->getPlatformFields('android')),
                            ]),
                        Forms\Components\Tabs\Tab::make('iOS')
                            ->schema([
                                Forms\Components\Section::make('iOS Version')
                                    ->schema($this->getPlatformFields('ios')),
                            ]),
                        Forms\Components\Tabs\Tab::make('Web')
                            ->schema([
                                Forms\Components\Section::make('Web Version')
                                    ->schema($this->getPlatformFields('web')),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getPlatformFields(string $platform): array
    {
        return [
            Forms\Components\TextInput::make("{$platform}.latest_version")
                ->label('Latest Version')
                ->required()
                ->maxLength(20)
                ->helperText('e.g., 1.2.0'),
            Forms\Components\TextInput::make("{$platform}.latest_build")
                ->label('Latest Build Number')
                ->maxLength(20)
                ->helperText('Build number (for mobile apps)')
                ->visible(fn () => $platform !== 'web'),
            Forms\Components\TextInput::make("{$platform}.min_version")
                ->label('Minimum Supported Version')
                ->maxLength(20)
                ->helperText('Minimum version required (e.g., 1.0.0)'),
            Forms\Components\TextInput::make("{$platform}.min_build")
                ->label('Minimum Build Number')
                ->maxLength(20)
                ->helperText('Minimum build required')
                ->visible(fn () => $platform !== 'web'),
            Forms\Components\Select::make("{$platform}.update_type")
                ->label('Update Type')
                ->options([
                    'none' => 'No Update',
                    'optional' => 'Optional Update',
                    'force' => 'Force Update',
                ])
                ->required()
                ->default('none')
                ->helperText('Force update requires confirmation'),
            Forms\Components\Textarea::make("{$platform}.update_message")
                ->label('Update Message')
                ->rows(3)
                ->helperText('Message shown to users when update is available'),
            Forms\Components\TextInput::make("{$platform}.store_url")
                ->label('Store URL')
                ->url()
                ->maxLength(500)
                ->helperText('App Store / Play Store URL'),
        ];
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('branding.app_versions'), 403);

        $data = $this->form->getState();

        DB::transaction(function () use ($data) {
            foreach (['android', 'ios', 'web'] as $platform) {
                $platformData = $data[$platform] ?? [];
                
                $before = AppVersion::getForPlatform($platform);
                $beforeData = $before ? $before->only(['latest_version', 'latest_build', 'min_version', 'min_build', 'update_type', 'update_message', 'store_url']) : [];

                AppVersion::updateOrCreate(
                    ['platform' => $platform],
                    [
                        'latest_version' => $platformData['latest_version'] ?? '1.0.0',
                        'latest_build' => $platformData['latest_build'] ?? null,
                        'min_version' => $platformData['min_version'] ?? null,
                        'min_build' => $platformData['min_build'] ?? null,
                        'update_type' => $platformData['update_type'] ?? 'none',
                        'update_message' => $platformData['update_message'] ?? null,
                        'store_url' => $platformData['store_url'] ?? null,
                        'released_at' => now(),
                    ]
                );

                $version = AppVersion::getForPlatform($platform);
                $afterData = $version->only(['latest_version', 'latest_build', 'min_version', 'min_build', 'update_type', 'update_message', 'store_url']);

                if ($version) {
                    AuditService::log('app_version.updated', $version, $beforeData, $afterData, ['module' => 'branding', 'platform' => $platform]);
                }
            }
        });

        app(AppConfigService::class)->clearCache();

        Notification::make()
            ->title('App versions updated successfully')
            ->success()
            ->send();

        $this->loadVersions();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Save Versions')
                ->submit('save'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('branding.app_versions') ?? false;
    }
}
