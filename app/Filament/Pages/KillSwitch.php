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

class KillSwitch extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static string $view = 'filament.pages.kill-switch';
    protected static ?string $navigationGroup = 'Branding';
    protected static ?string $navigationLabel = 'Kill Switch';
    protected static ?int $navigationSort = 5;

    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('branding.kill_switch'), 403);

        $this->loadKillSwitchData();
    }

    protected function loadKillSwitchData(): void
    {
        $platforms = ['all', 'android', 'ios', 'web'];
        foreach ($platforms as $platform) {
            $flag = RuntimeFlag::getForPlatform($platform);
            $this->data[$platform] = [
                'kill_switch_enabled' => $flag->kill_switch_enabled ?? false,
                'kill_switch_message' => $flag->kill_switch_message ?? 'The application is temporarily unavailable. Please contact support.',
                'kill_switch_until' => $flag->kill_switch_until ?? null,
            ];
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Emergency Kill Switch')
                    ->description('⚠️ WARNING: Kill switch will immediately disable the app for all users on selected platforms. Use only in emergency situations.')
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
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger'),
            ])
            ->statePath('data');
    }

    protected function getPlatformFields(string $platform): array
    {
        return [
            Forms\Components\Toggle::make("{$platform}.kill_switch_enabled")
                ->label('Enable Kill Switch')
                ->helperText("⚠️ Enable kill switch for {$platform} - This will immediately disable the app!")
                ->live()
                ->required(),
            Forms\Components\Textarea::make("{$platform}.kill_switch_message")
                ->label('Kill Switch Message')
                ->rows(4)
                ->required(fn ($get) => $get("{$platform}.kill_switch_enabled"))
                ->visible(fn ($get) => $get("{$platform}.kill_switch_enabled"))
                ->helperText('Message shown to users when kill switch is active'),
            Forms\Components\DateTimePicker::make("{$platform}.kill_switch_until")
                ->label('Auto-disable Until')
                ->helperText('Automatically disable kill switch after this time (optional)')
                ->visible(fn ($get) => $get("{$platform}.kill_switch_enabled"))
                ->timezone('Asia/Kolkata'),
        ];
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('branding.kill_switch'), 403);

        $data = $this->form->getState();

        DB::transaction(function () use ($data) {
            foreach (['all', 'android', 'ios', 'web'] as $platform) {
                $platformData = $data[$platform] ?? [];
                
                $before = RuntimeFlag::getForPlatform($platform);
                $beforeData = $before ? $before->only(['kill_switch_enabled', 'kill_switch_message', 'kill_switch_until']) : [];

                RuntimeFlag::updateOrCreate(
                    ['platform' => $platform],
                    [
                        'kill_switch_enabled' => $platformData['kill_switch_enabled'] ?? false,
                        'kill_switch_message' => $platformData['kill_switch_message'] ?? null,
                        'kill_switch_until' => $platformData['kill_switch_until'] ?? null,
                    ]
                );

                $flag = RuntimeFlag::getForPlatform($platform);
                $afterData = $flag->only(['kill_switch_enabled', 'kill_switch_message', 'kill_switch_until']);

                if ($flag) {
                    AuditService::log('kill_switch.updated', $flag, $beforeData, $afterData, ['module' => 'branding', 'platform' => $platform]);
                }
            }
        });

        app(AppConfigService::class)->clearCache();

        Notification::make()
            ->title('Kill switch updated successfully')
            ->warning()
            ->send();

        $this->loadKillSwitchData();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Save Kill Switch Settings')
                ->submit('save')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Confirm Kill Switch Change')
                ->modalDescription('Are you sure you want to update kill switch settings? This will immediately affect app availability.'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('branding.kill_switch') ?? false;
    }
}
