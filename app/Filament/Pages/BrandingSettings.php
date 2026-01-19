<?php

namespace App\Filament\Pages;

use App\Core\Services\AppConfigService;
use App\Core\Services\AuditService;
use App\Models\Brand;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BrandingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';
    protected static string $view = 'filament.pages.branding-settings';
    protected static ?string $navigationGroup = 'Branding';
    protected static ?string $navigationLabel = 'Branding Settings';
    protected static ?int $navigationSort = 1;

    public ?Brand $brand = null;
    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('branding.edit'), 403);

        // Get or create default brand
        $this->brand = Brand::firstOrCreate([], [
            'name' => 'ZyptoseComm',
            'slug' => 'zyptosecomm',
            'is_active' => true,
        ]);

        $this->loadBrandData();
    }

    protected function loadBrandData(): void
    {
        $this->data = [
            'name' => $this->brand->name ?? 'ZyptoseComm',
            'short_name' => $this->brand->short_name ?? 'ZC',
            'company_name' => $this->brand->company_name ?? '',
            'support_email' => $this->brand->support_email ?? '',
            'support_phone' => $this->brand->support_phone ?? '',
            'logo_light_path' => $this->brand->logo_light_path,
            'logo_dark_path' => $this->brand->logo_dark_path,
            'app_icon_path' => $this->brand->app_icon_path,
            'favicon_path' => $this->brand->favicon_path,
            'splash_path' => $this->brand->splash_path,
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Brand Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('App Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('short_name')
                            ->label('Short Name')
                            ->maxLength(50)
                            ->helperText('Short name for app icon/badge'),
                        Forms\Components\TextInput::make('company_name')
                            ->label('Company Name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('support_email')
                            ->label('Support Email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('support_phone')
                            ->label('Support Phone')
                            ->tel()
                            ->maxLength(20),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Brand Assets')
                    ->description('Upload brand assets (logos, icons, favicon, splash)')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_light_path')
                            ->label('Logo (Light)')
                            ->image()
                            ->directory('branding')
                            ->maxSize(2048)
                            ->helperText('Recommended: 512x512px, PNG or SVG'),
                        Forms\Components\FileUpload::make('logo_dark_path')
                            ->label('Logo (Dark)')
                            ->image()
                            ->directory('branding')
                            ->maxSize(2048)
                            ->helperText('Dark mode logo'),
                        Forms\Components\FileUpload::make('app_icon_path')
                            ->label('App Icon')
                            ->image()
                            ->directory('branding')
                            ->maxSize(512)
                            ->helperText('App icon (1024x1024px recommended)'),
                        Forms\Components\FileUpload::make('favicon_path')
                            ->label('Favicon')
                            ->image()
                            ->directory('branding')
                            ->maxSize(64)
                            ->helperText('Favicon (32x32px or 16x16px)'),
                        Forms\Components\FileUpload::make('splash_path')
                            ->label('Splash Screen')
                            ->image()
                            ->directory('branding')
                            ->maxSize(2048)
                            ->helperText('Splash screen image'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Preview')
                    ->schema([
                        Forms\Components\Placeholder::make('preview')
                            ->label('Preview')
                            ->content(function () {
                                $brand = $this->brand;
                                if (!$brand || !$brand->is_published) {
                                    return 'No published branding. Changes will be saved as draft.';
                                }
                                return "Published: " . $brand->published_at?->format('M d, Y H:i');
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('branding.edit'), 403);

        $data = $this->form->getState();
        $before = $this->brand->only(['name', 'short_name', 'company_name', 'logo_light_path', 'logo_dark_path', 'app_icon_path', 'favicon_path', 'splash_path', 'support_email', 'support_phone']);

        DB::transaction(function () use ($data) {
            $this->brand->update([
                'name' => $data['name'],
                'short_name' => $data['short_name'] ?? null,
                'company_name' => $data['company_name'] ?? null,
                'support_email' => $data['support_email'] ?? null,
                'support_phone' => $data['support_phone'] ?? null,
                'logo_light_path' => $data['logo_light_path'] ?? null,
                'logo_dark_path' => $data['logo_dark_path'] ?? null,
                'app_icon_path' => $data['app_icon_path'] ?? null,
                'favicon_path' => $data['favicon_path'] ?? null,
                'splash_path' => $data['splash_path'] ?? null,
            ]);
        });

        $after = $this->brand->fresh()->only(['name', 'short_name', 'company_name', 'logo_light_path', 'logo_dark_path', 'app_icon_path', 'favicon_path', 'splash_path', 'support_email', 'support_phone']);

        AuditService::log('branding.updated', $this->brand, $before, $after, ['module' => 'branding']);
        app(AppConfigService::class)->clearCache();

        Notification::make()
            ->title('Branding settings saved')
            ->success()
            ->send();
    }

    public function publish(): void
    {
        abort_unless(auth()->user()->can('branding.edit'), 403);

        $before = ['is_published' => $this->brand->is_published, 'published_at' => $this->brand->published_at];

        DB::transaction(function () {
            $this->brand->update([
                'is_published' => true,
                'published_at' => now(),
            ]);
        });

        $after = ['is_published' => $this->brand->fresh()->is_published, 'published_at' => $this->brand->fresh()->published_at];

        AuditService::log('branding.published', $this->brand, $before, $after, ['module' => 'branding']);
        app(AppConfigService::class)->clearCache();

        Notification::make()
            ->title('Branding published successfully')
            ->success()
            ->send();
    }

    public function revert(): void
    {
        abort_unless(auth()->user()->can('branding.edit'), 403);

        $published = Brand::where('id', $this->brand->id)
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->first();

        if (!$published) {
            Notification::make()
                ->title('No published version found')
                ->warning()
                ->send();
            return;
        }

        $before = $this->brand->only(['name', 'short_name', 'company_name', 'logo_light_path', 'logo_dark_path']);

        DB::transaction(function () use ($published) {
            $this->brand->update([
                'name' => $published->name,
                'short_name' => $published->short_name,
                'company_name' => $published->company_name,
                'logo_light_path' => $published->logo_light_path,
                'logo_dark_path' => $published->logo_dark_path,
                'app_icon_path' => $published->app_icon_path,
                'favicon_path' => $published->favicon_path,
                'splash_path' => $published->splash_path,
            ]);
        });

        $this->loadBrandData();

        AuditService::log('branding.reverted', $this->brand, $before, $this->brand->fresh()->only(['name', 'short_name', 'company_name']), ['module' => 'branding']);

        Notification::make()
            ->title('Reverted to published version')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Save Draft')
                ->submit('save'),
            Forms\Components\Actions\Action::make('publish')
                ->label('Publish')
                ->color('success')
                ->requiresConfirmation()
                ->action('publish'),
            Forms\Components\Actions\Action::make('revert')
                ->label('Revert to Published')
                ->color('warning')
                ->requiresConfirmation()
                ->action('revert')
                ->visible(fn () => $this->brand?->is_published),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('branding.edit') ?? false;
    }
}
