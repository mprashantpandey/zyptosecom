<?php

namespace App\Filament\Resources;

use App\Core\Services\AuditService;
use App\Core\Services\AppConfigService;
use App\Filament\Resources\HomeSectionItemResource\Pages;
use App\Models\HomeSectionItem;
use App\Models\HomeSection;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class HomeSectionItemResource extends Resource
{
    protected static ?string $model = HomeSectionItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'Home Builder';
    protected static ?string $navigationLabel = 'Section Items';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        $sectionId = request()->query('home_section_id');
        $isContextual = !empty($sectionId);
        
        return $form
            ->schema([
                Forms\Components\Section::make('Item Information')
                    ->schema([
                        Forms\Components\Select::make('home_section_id')
                            ->label('Home Section')
                            ->relationship('homeSection', 'title')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(fn () => $isContextual)
                            ->default(fn () => $sectionId)
                            ->visible(fn () => !$isContextual),
                        Forms\Components\Hidden::make('home_section_id')
                            ->default(fn () => $sectionId)
                            ->visible(fn () => $isContextual),
                        Forms\Components\TextInput::make('title')
                            ->maxLength(255)
                            ->helperText('Item title (displayed to users)'),
                        Forms\Components\TextInput::make('subtitle')
                            ->maxLength(255)
                            ->helperText('Item subtitle (optional)'),
                        Forms\Components\TextInput::make('badge_text')
                            ->label('Badge Text')
                            ->maxLength(50)
                            ->helperText('Badge label (e.g., "New", "Sale")'),
                        Forms\Components\TextInput::make('cta_text')
                            ->label('CTA Text')
                            ->maxLength(100)
                            ->helperText('Call-to-action button text'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Image')
                    ->schema([
                        Forms\Components\Select::make('image_path')
                            ->label('Image')
                            ->options(function () {
                                return MediaAsset::where('type', 'image')
                                    ->orderBy('created_at', 'desc')
                                    ->get()
                                    ->mapWithKeys(function ($asset) {
                                        return [$asset->path => basename($asset->path) . ' (' . $asset->size_human . ')'];
                                    });
                            })
                            ->searchable()
                            ->helperText('Select from media library')
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('upload_new')
                                    ->label('Upload New')
                                    ->icon('heroicon-o-photo')
                                    ->url(MediaAssetResource::getUrl('create'))
                            ),
                        Forms\Components\FileUpload::make('image_upload')
                            ->label('Or Upload Image')
                            ->image()
                            ->directory('home-items')
                            ->maxSize(2048)
                            ->helperText('Upload a new image (will be saved to media library)')
                            ->dehydrated(false)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $set('image_path', $state);
                                }
                            }),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Action')
                    ->schema([
                        Forms\Components\Select::make('action_type')
                            ->label('When user taps this item →')
                            ->options([
                                'none' => 'Do Nothing',
                                'product' => 'Open Product',
                                'category' => 'Open Category',
                                'deal' => 'Open Deal',
                                'search' => 'Open Search',
                                'url' => 'Open URL',
                            ])
                            ->required()
                            ->default('none')
                            ->live()
                            ->helperText('What happens when a customer taps or clicks this item?')
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('action_product_id', null);
                                $set('action_category_id', null);
                                $set('action_deal_id', null);
                                $set('action_search_query', null);
                                $set('action_url', null);
                            }),
                        Forms\Components\Select::make('action_product_id')
                            ->label('Product')
                            ->options(function () {
                                return Product::where('is_active', true)->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->required(fn ($get) => $get('action_type') === 'product')
                            ->visible(fn ($get) => $get('action_type') === 'product')
                            ->dehydrated(false),
                        Forms\Components\Select::make('action_category_id')
                            ->label('Category')
                            ->options(function () {
                                return Category::where('is_active', true)->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required(fn ($get) => $get('action_type') === 'category')
                            ->visible(fn ($get) => $get('action_type') === 'category')
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('action_search_query')
                            ->label('Search Query')
                            ->maxLength(255)
                            ->required(fn ($get) => $get('action_type') === 'search')
                            ->visible(fn ($get) => $get('action_type') === 'search')
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('action_url')
                            ->label('URL')
                            ->url()
                            ->maxLength(500)
                            ->required(fn ($get) => $get('action_type') === 'url')
                            ->visible(fn ($get) => $get('action_type') === 'url')
                            ->helperText('Full URL (e.g., https://example.com/page)')
                            ->dehydrated(false),
                        Forms\Components\Hidden::make('action_payload')
                            ->dehydrated(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Schedule & Platform')
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Start Date & Time')
                            ->helperText('When should this item become active? (optional)'),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('End Date & Time')
                            ->helperText('When should this item become inactive? (optional)')
                            ->after('starts_at'),
                        Forms\Components\Select::make('platform_scope')
                            ->label('Platform Override')
                            ->options([
                                'inherit' => 'Inherit from Section',
                                'web' => 'Web Only',
                                'app' => 'App Only',
                                'both' => 'Both',
                            ])
                            ->default('inherit')
                            ->helperText('Override section platform scope (default: inherit)'),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Lower numbers appear first'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Metadata (JSON)')
                    ->schema([
                        Forms\Components\Textarea::make('meta_json')
                            ->label('Meta JSON')
                            ->rows(5)
                            ->helperText('Additional metadata (valid JSON object)')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : json_encode([], JSON_PRETTY_PRINT))
                            ->dehydrateStateUsing(fn ($state) => json_decode($state, true) ?? [])
                            ->rules(['json']),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $sectionId = request()->query('home_section_id');
        $isContextual = !empty($sectionId);
        
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($sectionId) {
                if ($sectionId) {
                    $query->where('home_section_id', $sectionId);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->badge()
                    ->sortable()
                    ->color('gray'),
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk('public')
                    ->size(60)
                    ->defaultImageUrl(url('/images/placeholder.png')),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('subtitle')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('action_type')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'product' => 'success',
                        'category' => 'info',
                        'search' => 'warning',
                        'url' => 'primary',
                        'none' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('action_payload')
                    ->label('Action')
                    ->formatStateUsing(function (HomeSectionItem $record) {
                        $payload = $record->action_payload;
                        if (empty($payload)) return '-';
                        
                        return match($record->action_type) {
                            'product' => 'Product #' . ($payload['product_id'] ?? 'N/A'),
                            'category' => 'Category #' . ($payload['category_id'] ?? 'N/A'),
                            'search' => 'Search: ' . ($payload['query'] ?? 'N/A'),
                            'url' => 'URL: ' . (parse_url($payload['url'] ?? '', PHP_URL_HOST) ?: 'N/A'),
                            default => '-',
                        };
                    })
                    ->tooltip(fn ($record) => json_encode($record->action_payload, JSON_PRETTY_PRINT)),
                Tables\Columns\TextColumn::make('platform_scope')
                    ->label('Platform')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'web' => 'info',
                        'app' => 'warning',
                        'both' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('schedule')
                    ->label('Schedule')
                    ->formatStateUsing(function (HomeSectionItem $record) {
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
                Tables\Filters\SelectFilter::make('action_type')
                    ->options([
                        'none' => 'None',
                        'product' => 'Product',
                        'category' => 'Category',
                        'search' => 'Search',
                        'url' => 'URL',
                    ]),
                Tables\Filters\SelectFilter::make('platform_scope')
                    ->options([
                        'inherit' => 'Inherit',
                        'web' => 'Web Only',
                        'app' => 'App Only',
                        'both' => 'Both',
                    ]),
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
                Tables\Actions\Action::make('copy_deeplink')
                    ->label('Copy Deep Link')
                    ->icon('heroicon-o-link')
                    ->action(function (HomeSectionItem $record) {
                        $url = $record->getActionUrl();
                        if ($url) {
                            copy($url);
                            \Filament\Notifications\Notification::make()
                                ->title('Deep link copied to clipboard')
                                ->success()
                                ->send();
                        }
                    })
                    ->visible(fn (HomeSectionItem $record) => $record->action_type !== 'none'),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->action(function (HomeSectionItem $record) {
                        $newItem = $record->replicate();
                        $newItem->title = ($record->title ?? 'Item') . ' (Copy)';
                        $newItem->sort_order = HomeSectionItem::where('home_section_id', $record->home_section_id)->max('sort_order') + 1;
                        $newItem->save();
                        AuditService::log('home_item.duplicated', $newItem, ['id' => $record->id], ['id' => $newItem->id], ['module' => 'home_builder']);
                        app(AppConfigService::class)->clearCache();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (HomeSectionItem $record) {
                        $before = $record->only(['id', 'title', 'action_type']);
                        AuditService::log('home_item.deleted', $record, $before, [], ['module' => 'home_builder']);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                $before = $record->only(['id', 'title']);
                                AuditService::log('home_item.deleted', $record, $before, [], ['module' => 'home_builder']);
                            }
                        }),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->poll('30s')
            ->heading(fn () => $isContextual ? HomeSection::find(request()->query('home_section_id'))?->title . ' - Items' : 'Section Items');
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
            'index' => Pages\ListHomeSectionItems::route('/'),
            'create' => Pages\CreateHomeSectionItem::route('/create'),
            'edit' => Pages\EditHomeSectionItem::route('/{record}/edit'),
        ];
    }
}
