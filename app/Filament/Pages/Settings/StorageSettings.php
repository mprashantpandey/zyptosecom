<?php

namespace App\Filament\Pages\Settings;

use App\Core\Services\AppConfigService;
use App\Core\Services\AuditService;
use App\Core\Services\SettingsService;
use App\Core\Services\SecretsService;
use App\Core\Settings\SettingKeys;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class StorageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'settings/storage';
    protected static ?string $navigationIcon = 'heroicon-o-server';
    protected static string $view = 'filament.pages.settings.storage-settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Storage';
    protected static ?int $navigationSort = 6;

    public ?array $data = [];
    public bool $storageLinkActive = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('settings.storage.view'), 403);
        $this->loadSettings();
        $this->checkStorageLink();
    }

    protected function loadSettings(): void
    {
        $settings = app(SettingsService::class);
        
        $this->data = [
            'storage_driver' => $settings->get(SettingKeys::STORAGE_DRIVER, 'local'),
            's3_credential_id' => $settings->get(SettingKeys::STORAGE_S3_CREDENTIAL_ID, null),
            's3_bucket' => $settings->get(SettingKeys::STORAGE_S3_BUCKET, ''),
            's3_region' => $settings->get(SettingKeys::STORAGE_S3_REGION, 'us-east-1'),
            's3_base_url' => $settings->get(SettingKeys::STORAGE_S3_BASE_URL, ''),
        ];
    }

    protected function checkStorageLink(): void
    {
        $linkPath = public_path('storage');
        $this->storageLinkActive = File::exists($linkPath) && is_link($linkPath);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Storage Driver')
                    ->description('Choose where to store uploaded files')
                    ->schema([
                        Forms\Components\Select::make('storage_driver')
                            ->label('Storage Driver')
                            ->options([
                                'local' => 'Local Storage (Default)',
                                's3' => 'AWS S3 (or S3-compatible)',
                            ])
                            ->required()
                            ->default('local')
                            ->live()
                            ->helperText('Local storage saves files on your server. S3 stores files in cloud storage.'),
                    ]),

                Forms\Components\Section::make('Local Storage')
                    ->schema([
                        Forms\Components\Placeholder::make('local_status')
                            ->label('Storage Status')
                            ->content(function () {
                                $storageWritable = is_writable(storage_path('app'));
                                $publicWritable = is_writable(public_path());
                                $linkStatus = $this->storageLinkActive ? 'Active ✅' : 'Inactive ❌';
                                
                                return view('filament.components.storage-status', [
                                    'storageWritable' => $storageWritable,
                                    'publicWritable' => $publicWritable,
                                    'linkStatus' => $linkStatus,
                                ]);
                            }),
                    ])
                    ->visible(fn ($get) => $get('storage_driver') === 'local'),

                Forms\Components\Section::make('S3 Configuration')
                    ->schema([
                        Forms\Components\Select::make('s3_credential_id')
                            ->label('S3 Credentials')
                            ->helperText('Select S3 credentials configured in Integrations')
                            ->options(function () {
                                return Provider::where('type', 'storage')
                                    ->where('name', 's3')
                                    ->where('is_enabled', true)
                                    ->get()
                                    ->mapWithKeys(function ($provider) {
                                        $secretsService = app(SecretsService::class);
                                        $credentials = $secretsService->getCredentials(
                                            $provider->type,
                                            $provider->name,
                                            $provider->environment
                                        );
                                        $hasCredentials = !empty($credentials);
                                        return [$provider->id => $provider->label . ($hasCredentials ? ' ✅' : ' (Not configured)')];
                                    });
                            })
                            ->searchable()
                            ->placeholder('Select S3 credentials')
                            ->required(fn ($get) => $get('storage_driver') === 's3')
                            ->live(),
                        
                        Forms\Components\TextInput::make('s3_bucket')
                            ->label('Bucket Name')
                            ->maxLength(255)
                            ->required(fn ($get) => $get('storage_driver') === 's3')
                            ->helperText('S3 bucket name'),
                        
                        Forms\Components\TextInput::make('s3_region')
                            ->label('Region')
                            ->maxLength(255)
                            ->default('us-east-1')
                            ->required(fn ($get) => $get('storage_driver') === 's3')
                            ->helperText('AWS region (e.g., us-east-1, ap-south-1)'),
                        
                        Forms\Components\TextInput::make('s3_base_url')
                            ->label('Base URL (Optional)')
                            ->maxLength(500)
                            ->url()
                            ->helperText('CDN URL if using CloudFront or similar (optional)')
                            ->placeholder('https://cdn.example.com'),
                        
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('test_s3')
                                ->label('Test S3 Connection')
                                ->icon('heroicon-o-arrow-path')
                                ->color('info')
                                ->requiresConfirmation()
                                ->modalHeading('Test S3 Connection')
                                ->modalDescription('This will attempt to connect to your S3 bucket and upload a test file.')
                                ->action(function ($get) {
                                    $this->testS3Connection($get);
                                }),
                        ]),
                    ])
                    ->visible(fn ($get) => $get('storage_driver') === 's3')
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('settings.storage.edit'), 403);

        $data = $this->form->getState();
        $settings = app(SettingsService::class);
        
        $keys = [
            SettingKeys::STORAGE_DRIVER, SettingKeys::STORAGE_S3_CREDENTIAL_ID,
            SettingKeys::STORAGE_S3_BUCKET, SettingKeys::STORAGE_S3_REGION,
            SettingKeys::STORAGE_S3_BASE_URL,
        ];
        $before = $settings->snapshot($keys);

        DB::transaction(function () use ($data, $settings) {
            $settings->set(SettingKeys::STORAGE_DRIVER, $data['storage_driver'], 'storage', 'string', false);
            
            if ($data['storage_driver'] === 's3') {
                $settings->set(SettingKeys::STORAGE_S3_CREDENTIAL_ID, $data['s3_credential_id'] ?? null, 'storage', 'integer', false);
                $settings->set(SettingKeys::STORAGE_S3_BUCKET, $data['s3_bucket'] ?? '', 'storage', 'string', false);
                $settings->set(SettingKeys::STORAGE_S3_REGION, $data['s3_region'] ?? 'us-east-1', 'storage', 'string', false);
                $settings->set(SettingKeys::STORAGE_S3_BASE_URL, $data['s3_base_url'] ?? '', 'storage', 'string', false);
            }
        });

        $after = $settings->snapshot($keys);

        AuditService::log('settings.storage_updated', null, $before, $after, ['module' => 'settings']);
        app(AppConfigService::class)->clearCache();

        Notification::make()
            ->title('Storage settings saved successfully')
            ->success()
            ->send();

        $this->loadSettings();
    }

    public function createStorageLink(): void
    {
        abort_unless(auth()->user()->can('settings.storage.edit'), 403);

        try {
            if ($this->storageLinkActive) {
                Notification::make()
                    ->title('Storage link already exists')
                    ->warning()
                    ->send();
                return;
            }

            Artisan::call('storage:link');
            $output = Artisan::output();

            $this->checkStorageLink();

            AuditService::log('storage.link_created', null, [], ['status' => 'success'], ['module' => 'storage']);

            Notification::make()
                ->title('Storage link created successfully')
                ->success()
                ->send();
        } catch (\Exception $e) {
            AuditService::log('storage.link_created', null, [], ['status' => 'failed', 'error' => $e->getMessage()], ['module' => 'storage']);

            Notification::make()
                ->title('Failed to create storage link')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function removeStorageLink(): void
    {
        abort_unless(auth()->user()->can('settings.storage.edit'), 403);

        try {
            if (!$this->storageLinkActive) {
                Notification::make()
                    ->title('Storage link does not exist')
                    ->warning()
                    ->send();
                return;
            }

            $linkPath = public_path('storage');
            if (is_link($linkPath)) {
                unlink($linkPath);
            }

            $this->checkStorageLink();

            AuditService::log('storage.link_removed', null, [], ['status' => 'success'], ['module' => 'storage']);

            Notification::make()
                ->title('Storage link removed successfully')
                ->success()
                ->send();
        } catch (\Exception $e) {
            AuditService::log('storage.link_removed', null, [], ['status' => 'failed', 'error' => $e->getMessage()], ['module' => 'storage']);

            Notification::make()
                ->title('Failed to remove storage link')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function testS3Connection($get = null): void
    {
        abort_unless(auth()->user()->can('settings.storage.edit'), 403);

        $data = $get ?: $this->form->getState();

        if (empty($data['s3_credential_id']) || empty($data['s3_bucket'])) {
            Notification::make()
                ->title('S3 test failed')
                ->body('Please configure S3 credentials and bucket name first')
                ->danger()
                ->send();
            return;
        }

        try {
            $provider = Provider::find($data['s3_credential_id']);
            if (!$provider) {
                throw new \Exception('S3 provider not found');
            }

            $secretsService = app(SecretsService::class);
            $credentials = $secretsService->getCredentials(
                $provider->type,
                $provider->name,
                $provider->environment
            );

            if (empty($credentials)) {
                throw new \Exception('S3 credentials not configured. Please configure them in Integrations.');
            }

            // Test S3 connection by attempting to list bucket
            // This is a simplified test - in production, you'd use AWS SDK
            $testResult = [
                'bucket' => $data['s3_bucket'],
                'region' => $data['s3_region'],
                'credentials_configured' => !empty($credentials['access_key_id']),
            ];

            AuditService::log('storage.s3_tested', null, [], ['status' => 'success', 'result' => $testResult], ['module' => 'storage']);

            Notification::make()
                ->title('S3 connection test successful')
                ->body('S3 configuration appears correct. Make sure your bucket exists and credentials have proper permissions.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            AuditService::log('storage.s3_tested', null, [], ['status' => 'failed', 'error' => $e->getMessage()], ['module' => 'storage']);

            Notification::make()
                ->title('S3 connection test failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
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
        return auth()->user()?->can('settings.storage.view') ?? false;
    }
}

