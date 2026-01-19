<?php

namespace App\Filament\Pages;

use App\Core\Services\AppConfigService;
use App\Core\Services\AuditService;
use App\Models\RuntimeFlag;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class MaintenanceMode extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static string $view = 'filament.pages.maintenance-mode';
    protected static ?string $navigationGroup = 'Branding';
    protected static ?string $navigationLabel = 'Maintenance Mode';
    protected static ?int $navigationSort = 4;

    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('branding.maintenance'), 403);

        $this->loadMaintenanceData();
    }

    protected function loadMaintenanceData(): void
    {
        $platforms = ['android', 'ios', 'web', 'all'];
        foreach ($platforms as $platform) {
            $flag = RuntimeFlag::getForPlatform($platform);
            $this->data[$platform] = [
                'maintenance_enabled' => $flag->maintenance_enabled ?? false,
                'maintenance_message' => $flag->maintenance_message ?? 'We are currently under maintenance. Please check back soon.',
                'maintenance_starts_at' => $flag->maintenance_starts_at ?? null,
                'maintenance_ends_at' => $flag->maintenance_ends_at ?? null,
            ];
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Platforms')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('All Platforms')
                            ->schema($this->getPlatformFields('all')),
                        Forms\Components\Tabs\Tab::make('Android')
                            ->schema($this->getPlatformFields('android')),
                        Forms\Components\Tabs\Tab::make('iOS')
                            ->schema($this->getPlatformFields('ios')),
                        Forms\Components\Tabs\Tab::make('Web')
                            ->schema($this->getPlatformFields('web')),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getPlatformFields(string $platform): array
    {
        return [
            Forms\Components\Toggle::make("{$platform}.maintenance_enabled")
                ->label('Enable Maintenance Mode')
                ->helperText("Enable maintenance mode for {$platform}")
                ->live(),
            Forms\Components\Textarea::make("{$platform}.maintenance_message")
                ->label('Maintenance Message')
                ->rows(3)
                ->required(fn ($get) => $get("{$platform}.maintenance_enabled"))
                ->visible(fn ($get) => $get("{$platform}.maintenance_enabled"))
                ->helperText('Message shown to users during maintenance'),
            Forms\Components\DateTimePicker::make("{$platform}.maintenance_starts_at")
                ->label('Start Date & Time')
                ->helperText('Schedule when maintenance should start (optional)')
                ->visible(fn ($get) => $get("{$platform}.maintenance_enabled")),
            Forms\Components\DateTimePicker::make("{$platform}.maintenance_ends_at")
                ->label('End Date & Time')
                ->helperText('Schedule when maintenance should end (optional)')
                ->visible(fn ($get) => $get("{$platform}.maintenance_enabled")),
        ];
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('branding.maintenance'), 403);

        $data = $this->form->getState();

        DB::transaction(function () use ($data) {
            foreach (['all', 'android', 'ios', 'web'] as $platform) {
                $platformData = $data[$platform] ?? [];
                
                $before = RuntimeFlag::getForPlatform($platform);
                $beforeData = $before ? $before->only(['maintenance_enabled', 'maintenance_message', 'maintenance_starts_at', 'maintenance_ends_at']) : [];

                RuntimeFlag::updateOrCreate(
                    ['platform' => $platform],
                    [
                        'maintenance_enabled' => $platformData['maintenance_enabled'] ?? false,
                        'maintenance_message' => $platformData['maintenance_message'] ?? null,
                        'maintenance_starts_at' => $platformData['maintenance_starts_at'] ?? null,
                        'maintenance_ends_at' => $platformData['maintenance_ends_at'] ?? null,
                    ]
                );

                $flag = RuntimeFlag::getForPlatform($platform);
                $afterData = $flag->only(['maintenance_enabled', 'maintenance_message', 'maintenance_starts_at', 'maintenance_ends_at']);

                if ($flag) {
                    AuditService::log('maintenance_mode.updated', $flag, $beforeData, $afterData, ['module' => 'branding', 'platform' => $platform]);
                }
            }
        });

        app(AppConfigService::class)->clearCache();

        Notification::make()
            ->title('Maintenance mode updated successfully')
            ->success()
            ->send();

        $this->loadMaintenanceData();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('branding.maintenance') ?? false;
    }
}
