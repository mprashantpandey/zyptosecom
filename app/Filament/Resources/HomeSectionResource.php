<?php

namespace App\Filament\Resources;

use App\Core\Services\AuditService;
use App\Core\Services\AppConfigService;
use App\Filament\Resources\HomeSectionResource\Pages;
use App\Models\HomeSection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class HomeSectionResource extends Resource
{
    protected static ?string $model = HomeSection::class;

    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationGroup = 'Home Builder';
    protected static ?string $navigationLabel = 'Home Sections';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Section Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                if (!$get('key') || Str::slug($get('key')) === Str::slug($get('title', ''))) {
                                    $set('key', Str::slug($state));
                                }
                            }),
                        Forms\Components\TextInput::make('key')
                            ->label('Key (Slug)')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Unique identifier for this section (auto-generated from title)'),
                        Forms\Components\Select::make('type')
                            ->label('Section Template')
                            ->options([
                                'hero_banner' => 'Hero Banner',
                                'category_grid' => 'Category Grid',
                                'product_carousel' => 'Product Carousel',
                                'deals_slider' => 'Deals Slider',
                                'image_cta' => 'Image + CTA',
                                'slider' => 'Slider (Legacy)',
                                'banner' => 'Banner (Legacy)',
                                'offer_cards' => 'Offer Cards (Legacy)',
                                'custom_html' => 'Custom HTML',
                            ])
                            ->required()
                            ->live()
                            ->helperText('Choose a template to get started. Each template has pre-configured settings.')
                            ->afterStateUpdated(function ($state, callable $set) {
                                $defaults = self::getDefaultSettingsForType($state);
                                $set('settings_json', json_encode($defaults, JSON_PRETTY_PRINT));
                            }),
                        Forms\Components\Select::make('platform_scope')
                            ->label('Platform Scope')
                            ->options([
                                'web' => 'Web Only',
                                'app' => 'App Only',
                                'both' => 'Both Web & App',
                            ])
                            ->required()
                            ->default('both'),
                        Forms\Components\Toggle::make('is_enabled')
                            ->label('Enabled')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Schedule')
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Start Date & Time')
                            ->helperText('When should this section become active? (optional)'),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('End Date & Time')
                            ->helperText('When should this section become inactive? (optional)')
                            ->after('starts_at')
                            ->rules([
                                function ($get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $startsAt = $get('starts_at');
                                        if ($startsAt && $value && $value < $startsAt) {
                                            $fail('End date must be after start date.');
                                        }
                                    };
                                },
                            ]),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Lower numbers appear first'),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Forms\Components\Section::make('Advanced Settings (Optional)')
                    ->description('Advanced configuration for developers. Most users don\'t need to modify these.')
                    ->schema([
                        Forms\Components\Textarea::make('settings_json')
                            ->label('Settings JSON')
                            ->rows(10)
                            ->helperText('Section-specific configuration (valid JSON object). Only modify if you know what you\'re doing.')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : json_encode([], JSON_PRETTY_PRINT))
                            ->dehydrateStateUsing(fn ($state) => json_decode($state, true) ?? [])
                            ->rules(['json'])
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->badge()
                    ->sortable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'slider' => 'info',
                        'banner' => 'success',
                        'category_grid' => 'warning',
                        'product_carousel' => 'danger',
                        'offer_cards' => 'primary',
                        'custom_html' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('platform_scope')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'web' => 'info',
                        'app' => 'warning',
                        'both' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('schedule')
                    ->label('Schedule')
                    ->formatStateUsing(function (HomeSection $record) {
                        $schedule = [];
                        if ($record->starts_at && $record->starts_at->isFuture()) {
                            $schedule[] = 'Starts: ' . $record->starts_at->format('M d, H:i');
                        }
                        if ($record->ends_at && $record->ends_at->isFuture()) {
                            $schedule[] = 'Ends: ' . $record->ends_at->format('M d, H:i');
                        }
                        return implode(' | ', $schedule) ?: 'Always active';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'slider' => 'Slider',
                        'banner' => 'Banner',
                        'category_grid' => 'Category Grid',
                        'product_carousel' => 'Product Carousel',
                        'offer_cards' => 'Offer Cards',
                        'custom_html' => 'Custom HTML',
                    ]),
                Tables\Filters\SelectFilter::make('platform_scope')
                    ->options([
                        'web' => 'Web Only',
                        'app' => 'App Only',
                        'both' => 'Both',
                    ]),
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label('Enabled')
                    ->placeholder('All')
                    ->trueLabel('Enabled only')
                    ->falseLabel('Disabled only'),
                Tables\Filters\Filter::make('schedule_active')
                    ->label('Schedule Status')
                    ->query(function (Builder $query): Builder {
                        $now = now();
                        return $query->where(function ($q) use ($now) {
                            $q->whereNull('starts_at')
                                ->orWhere('starts_at', '<=', $now);
                        })->where(function ($q) use ($now) {
                            $q->whereNull('ends_at')
                                ->orWhere('ends_at', '>=', $now);
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('preview_home')
                    ->label('Preview Home')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn () => url('/'), shouldOpenInNewTab: true)
                    ->helperText('Preview how the home page looks with current sections'),
                Tables\Actions\Action::make('manage_items')
                    ->label('Manage Items')
                    ->icon('heroicon-o-squares-plus')
                    ->color('warning')
                    ->url(fn (HomeSection $record) => HomeSectionItemResource::getUrl('index', ['home_section_id' => $record->id]))
                    ->badge(fn (HomeSection $record) => $record->items()->count())
                    ->badgeColor(fn (HomeSection $record) => $record->items()->count() === 0 ? 'danger' : 'success')
                    ->helperText(fn (HomeSection $record) => $record->items()->count() === 0 ? 'Warning: This section has no items!' : ''),
                Tables\Actions\Action::make('toggle_enabled')
                    ->label(fn (HomeSection $record) => $record->is_enabled ? 'Disable' : 'Enable')
                    ->icon(fn (HomeSection $record) => $record->is_enabled ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (HomeSection $record) => $record->is_enabled ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (HomeSection $record) {
                        $before = ['is_enabled' => $record->is_enabled];
                        $record->is_enabled = !$record->is_enabled;
                        $record->save();
                        AuditService::log('home_section.toggled', $record, $before, ['is_enabled' => $record->is_enabled], ['module' => 'home_builder']);
                        app(AppConfigService::class)->clearCache();
                        Cache::forget('home_layout:v1:web');
                        Cache::forget('home_layout:v1:app');
                    }),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (HomeSection $record) {
                        $before = $record->only(['id', 'key', 'title']);
                        $newSection = $record->replicate();
                        $newSection->key = $record->key . '-' . time();
                        $newSection->title = $record->title . ' (Copy)';
                        $newSection->is_enabled = false;
                        $newSection->sort_order = HomeSection::max('sort_order') + 1;
                        $newSection->save();
                        
                        // Duplicate items
                        foreach ($record->items as $item) {
                            $newItem = $item->replicate();
                            $newItem->home_section_id = $newSection->id;
                            $newItem->save();
                        }
                        
                        AuditService::log('home_section.duplicated', $newSection, $before, ['id' => $newSection->id, 'key' => $newSection->key], ['module' => 'home_builder']);
                        app(AppConfigService::class)->clearCache();
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (HomeSection $record) {
                        $before = $record->only(['id', 'key', 'title']);
                        AuditService::log('home_section.deleted', $record, $before, [], ['module' => 'home_builder']);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('enable_selected')
                        ->label('Enable Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $before = ['is_enabled' => $record->is_enabled];
                                $record->is_enabled = true;
                                $record->save();
                                AuditService::log('home_section.toggled', $record, $before, ['is_enabled' => true], ['module' => 'home_builder']);
                            }
                            app(AppConfigService::class)->clearCache();
                            Cache::forget('home_layout:v1:web');
                            Cache::forget('home_layout:v1:app');
                        }),
                    Tables\Actions\BulkAction::make('disable_selected')
                        ->label('Disable Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $before = ['is_enabled' => $record->is_enabled];
                                $record->is_enabled = false;
                                $record->save();
                                AuditService::log('home_section.toggled', $record, $before, ['is_enabled' => false], ['module' => 'home_builder']);
                            }
                            app(AppConfigService::class)->clearCache();
                            Cache::forget('home_layout:v1:web');
                            Cache::forget('home_layout:v1:app');
                        }),
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                $before = $record->only(['id', 'key', 'title']);
                                AuditService::log('home_section.deleted', $record, $before, [], ['module' => 'home_builder']);
                            }
                        }),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->poll('30s');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHomeSections::route('/'),
            'create' => Pages\CreateHomeSection::route('/create'),
            'view' => Pages\ViewHomeSection::route('/{record}'),
            'edit' => Pages\EditHomeSection::route('/{record}/edit'),
        ];
    }

    protected static function getDefaultSettingsForType(string $type): array
    {
        return match($type) {
            'hero_banner' => [
                'height' => '400px',
                'show_overlay' => true,
                'overlay_opacity' => 0.5,
                'text_position' => 'center',
                'autoplay' => true,
                'interval' => 5000,
            ],
            'category_grid' => [
                'columns' => 4,
                'show_title' => true,
                'show_count' => true,
                'card_style' => 'default',
            ],
            'product_carousel' => [
                'items_per_view' => 4,
                'show_arrows' => true,
                'autoplay' => false,
                'show_dots' => true,
            ],
            'deals_slider' => [
                'autoplay' => true,
                'interval' => 4000,
                'show_indicators' => true,
                'show_timer' => true,
            ],
            'image_cta' => [
                'height' => '300px',
                'cta_position' => 'bottom-right',
                'show_overlay' => false,
            ],
            'slider' => ['autoplay' => true, 'interval' => 5000, 'show_indicators' => true],
            'banner' => ['height' => '300px', 'show_overlay' => false],
            'offer_cards' => ['columns' => 3, 'card_style' => 'default'],
            'custom_html' => ['html' => '', 'sanitize' => true],
            default => [],
        };
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('home_builder.view') ?? false;
    }
}
