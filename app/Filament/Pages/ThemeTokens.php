<?php

namespace App\Filament\Pages;

use App\Core\Services\AppConfigService;
use App\Core\Services\AuditService;
use App\Models\Brand;
use App\Models\Theme;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ThemeTokens extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-swatch';
    protected static string $view = 'filament.pages.theme-tokens';
    protected static ?string $navigationGroup = 'Branding';
    protected static ?string $navigationLabel = 'Theme Tokens';
    protected static ?int $navigationSort = 2;

    public ?Theme $theme = null;
    public ?array $data = [];
    public string $editorMode = 'form'; // 'form' or 'json'

    public function mount(): void
    {
        abort_unless(auth()->user()->can('branding.theme.edit'), 403);

        $brand = Brand::first();
        
        // First, try to find an existing draft theme for this brand
        $this->theme = Theme::where('brand_id', $brand?->id)
            ->where('mode', 'draft')
            ->first();
        
        // If no draft theme exists, check if there's a default theme (name='default') we can use
        if (!$this->theme) {
            // Check if default theme exists (from seeder, brand_id is null)
            $defaultTheme = Theme::where('name', 'default')
                ->whereNull('brand_id')
                ->first();
            
            if ($defaultTheme) {
                // Use the default theme if it's in draft mode, otherwise create a draft copy
                if ($defaultTheme->mode === 'draft') {
                    // Update brand_id to current brand if needed
                    $this->theme = $defaultTheme;
                    if ($this->theme->brand_id !== $brand?->id) {
                        $this->theme->update(['brand_id' => $brand?->id]);
                    }
                } else {
                    // Default theme is published, create a new draft with unique name
                    $this->theme = Theme::create([
                        'brand_id' => $brand?->id,
                        'name' => 'default-draft-' . time(), // Unique name to avoid constraint violation
                        'label' => 'Default Theme (Draft)',
                        'mode' => 'draft',
                        'tokens_json' => $defaultTheme->tokens_json ?? $this->getDefaultTokens(),
                        'primary_color' => $defaultTheme->primary_color ?? '#007bff',
                        'secondary_color' => $defaultTheme->secondary_color ?? '#6c757d',
                        'accent_color' => $defaultTheme->accent_color ?? '#ffc107',
                        'background_color' => $defaultTheme->background_color ?? '#ffffff',
                        'surface_color' => $defaultTheme->surface_color ?? '#f8f9fa',
                        'text_color' => $defaultTheme->text_color ?? '#212529',
                        'text_secondary_color' => $defaultTheme->text_secondary_color ?? '#6c757d',
                        'border_radius' => $defaultTheme->border_radius ?? '8px',
                        'ui_density' => $defaultTheme->ui_density ?? 'normal',
                        'font_family' => $defaultTheme->font_family,
                        'font_url' => $defaultTheme->font_url,
                        'is_active' => true,
                        'is_default' => false,
                    ]);
                }
            } else {
                // No default theme exists, use the seeder's default theme or create new
                // Try to find any theme with name='default' (might have brand_id set)
                $anyDefault = Theme::where('name', 'default')->first();
                
                if ($anyDefault && $anyDefault->mode === 'draft') {
                    $this->theme = $anyDefault;
                    if ($this->theme->brand_id !== $brand?->id) {
                        $this->theme->update(['brand_id' => $brand?->id]);
                    }
                } else {
                    // Create a new draft with unique name
                    $this->theme = Theme::create([
                        'brand_id' => $brand?->id,
                        'name' => 'default-draft-' . time(),
                        'label' => 'Default Theme (Draft)',
                        'mode' => 'draft',
                        'tokens_json' => $this->getDefaultTokens(),
                        'primary_color' => '#007bff',
                        'secondary_color' => '#6c757d',
                        'accent_color' => '#ffc107',
                        'background_color' => '#ffffff',
                        'surface_color' => '#f8f9fa',
                        'text_color' => '#212529',
                        'text_secondary_color' => '#6c757d',
                        'border_radius' => '8px',
                        'ui_density' => 'normal',
                        'font_family' => 'Roboto',
                        'font_url' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap',
                        'is_active' => true,
                        'is_default' => false,
                    ]);
                }
            }
        }

        $this->loadThemeData();
    }

    protected function loadThemeData(): void
    {
        $tokens = $this->theme->tokens_json ?? $this->getDefaultTokens();

        $this->data = [
            'primary_color' => $tokens['colors']['primary'] ?? '#007bff',
            'secondary_color' => $tokens['colors']['secondary'] ?? '#6c757d',
            'accent_color' => $tokens['colors']['accent'] ?? '#ffc107',
            'background_color' => $tokens['colors']['background'] ?? '#ffffff',
            'surface_color' => $tokens['colors']['surface'] ?? '#f8f9fa',
            'text_color' => $tokens['colors']['text'] ?? '#212529',
            'text_secondary_color' => $tokens['colors']['text_secondary'] ?? '#6c757d',
            'border_radius' => $tokens['radius']['default'] ?? '8px',
            'ui_density' => $tokens['density'] ?? 'normal',
            'font_family' => $tokens['typography']['fontFamily'] ?? null,
            'font_url' => $tokens['typography']['fontUrl'] ?? null,
            'tokens_json' => $tokens,
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Theme Editor')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Form Editor')
                            ->schema([
                                Forms\Components\Section::make('Colors')
                                    ->schema([
                                        Forms\Components\ColorPicker::make('primary_color')
                                            ->label('Primary Color')
                                            ->required(),
                                        Forms\Components\ColorPicker::make('secondary_color')
                                            ->label('Secondary Color')
                                            ->required(),
                                        Forms\Components\ColorPicker::make('accent_color')
                                            ->label('Accent Color')
                                            ->required(),
                                        Forms\Components\ColorPicker::make('background_color')
                                            ->label('Background Color')
                                            ->required(),
                                        Forms\Components\ColorPicker::make('surface_color')
                                            ->label('Surface Color')
                                            ->required(),
                                        Forms\Components\ColorPicker::make('text_color')
                                            ->label('Text Color')
                                            ->required(),
                                        Forms\Components\ColorPicker::make('text_secondary_color')
                                            ->label('Text Secondary Color')
                                            ->required(),
                                    ])
                                    ->columns(2),

                                Forms\Components\Section::make('Typography & Spacing')
                                    ->schema([
                                        Forms\Components\TextInput::make('font_family')
                                            ->label('Font Family')
                                            ->maxLength(255)
                                            ->helperText('e.g., Roboto, Inter'),
                                        Forms\Components\TextInput::make('font_url')
                                            ->label('Font URL')
                                            ->url()
                                            ->maxLength(500)
                                            ->helperText('Google Fonts URL or custom font URL'),
                                        Forms\Components\TextInput::make('border_radius')
                                            ->label('Border Radius')
                                            ->maxLength(20)
                                            ->default('8px')
                                            ->helperText('e.g., 8px, 12px, rounded-full'),
                                        Forms\Components\Select::make('ui_density')
                                            ->label('UI Density')
                                            ->options([
                                                'compact' => 'Compact',
                                                'normal' => 'Normal',
                                                'comfortable' => 'Comfortable',
                                            ])
                                            ->default('normal'),
                                    ])
                                    ->columns(2),
                            ]),

                        Forms\Components\Tabs\Tab::make('JSON Editor')
                            ->schema([
                                Forms\Components\Textarea::make('tokens_json')
                                    ->label('Tokens JSON')
                                    ->rows(20)
                                    ->helperText('Advanced: Edit JSON directly. Must include colors.primary, colors.secondary, typography.fontFamily, radius.default')
                                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : json_encode([], JSON_PRETTY_PRINT))
                                    ->dehydrateStateUsing(fn ($state) => json_decode($state, true) ?? []),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('branding.theme.edit'), 403);

        $data = $this->form->getState();

        // Build tokens JSON from form data
        $tokensJson = $data['tokens_json'] ?? [];
        
        // If using form editor, build from fields
        if (!isset($data['tokens_json']) || empty($data['tokens_json'])) {
            $tokensJson = [
                'colors' => [
                    'primary' => $data['primary_color'],
                    'secondary' => $data['secondary_color'],
                    'accent' => $data['accent_color'],
                    'background' => $data['background_color'],
                    'surface' => $data['surface_color'],
                    'text' => $data['text_color'],
                    'text_secondary' => $data['text_secondary_color'],
                ],
                'typography' => [
                    'fontFamily' => $data['font_family'] ?? null,
                    'fontUrl' => $data['font_url'] ?? null,
                ],
                'radius' => [
                    'default' => $data['border_radius'] ?? '8px',
                ],
                'density' => $data['ui_density'] ?? 'normal',
            ];
        }

        // Validate required tokens
        $validator = Validator::make($tokensJson, [
            'colors.primary' => 'required',
            'colors.secondary' => 'required',
            'typography.fontFamily' => 'nullable|string',
            'radius.default' => 'required|string',
        ]);

        if ($validator->fails()) {
            Notification::make()
                ->title('Validation failed')
                ->body('Tokens must include colors.primary, colors.secondary, and radius.default')
                ->danger()
                ->send();
            return;
        }

        $before = $this->theme->only(['tokens_json', 'mode']);

        DB::transaction(function () use ($tokensJson) {
            $this->theme->update([
                'tokens_json' => $tokensJson,
                'mode' => 'draft',
            ]);
        });

        $after = $this->theme->fresh()->only(['tokens_json', 'mode']);

        AuditService::log('theme.updated', $this->theme, $before, $after, ['module' => 'branding']);
        app(AppConfigService::class)->clearCache();

        Notification::make()
            ->title('Theme tokens saved as draft')
            ->success()
            ->send();
    }

    public function publish(): void
    {
        abort_unless(auth()->user()->can('branding.theme.edit'), 403);

        $before = ['mode' => $this->theme->mode, 'published_at' => $this->theme->published_at];

        DB::transaction(function () {
            $this->theme->update([
                'mode' => 'published',
                'published_at' => now(),
            ]);
        });

        $after = ['mode' => $this->theme->fresh()->mode, 'published_at' => $this->theme->fresh()->published_at];

        AuditService::log('theme.published', $this->theme, $before, $after, ['module' => 'branding']);
        app(AppConfigService::class)->clearCache();

        Notification::make()
            ->title('Theme published successfully')
            ->success()
            ->send();
    }

    public function revert(): void
    {
        abort_unless(auth()->user()->can('branding.theme.edit'), 403);

        $published = Theme::where('brand_id', $this->theme->brand_id)
            ->where('mode', 'published')
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->first();

        if (!$published) {
            Notification::make()
                ->title('No published theme found')
                ->warning()
                ->send();
            return;
        }

        $before = $this->theme->only(['tokens_json']);

        DB::transaction(function () use ($published) {
            $this->theme->update([
                'tokens_json' => $published->tokens_json,
            ]);
        });

        $this->loadThemeData();

        AuditService::log('theme.reverted', $this->theme, $before, $this->theme->fresh()->only(['tokens_json']), ['module' => 'branding']);

        Notification::make()
            ->title('Reverted to published theme')
            ->success()
            ->send();
    }

    protected function getDefaultTokens(): array
    {
        return [
            'colors' => [
                'primary' => '#007bff',
                'secondary' => '#6c757d',
                'accent' => '#ffc107',
                'background' => '#ffffff',
                'surface' => '#f8f9fa',
                'text' => '#212529',
                'text_secondary' => '#6c757d',
            ],
            'typography' => [
                'fontFamily' => null,
                'fontUrl' => null,
            ],
            'radius' => [
                'default' => '8px',
            ],
            'density' => 'normal',
        ];
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
                ->visible(fn () => Theme::where('brand_id', $this->theme?->brand_id)->where('mode', 'published')->exists()),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('branding.theme.edit') ?? false;
    }
}
