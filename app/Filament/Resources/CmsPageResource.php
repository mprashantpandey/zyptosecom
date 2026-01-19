<?php

namespace App\Filament\Resources;

use App\Core\Services\AuditService;
use App\Filament\Resources\CmsPageResource\Pages;
use App\Models\CmsPage;
use App\Models\Language;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class CmsPageResource extends Resource
{
    protected static ?string $model = CmsPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'CMS Pages';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Page Information')
                    ->description('Basic page details and content')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Page Title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                if (!$get('slug') || Str::slug($get('slug')) === Str::slug($get('title', ''))) {
                                    $set('slug', Str::slug($state));
                                }
                            })
                            ->helperText('The title of your page'),
                        Forms\Components\TextInput::make('slug')
                            ->label('URL Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Used in URL like /privacy-policy. Auto-generated from title if left empty.')
                            ->formatStateUsing(fn ($state) => $state ?: ''),
                        Forms\Components\Select::make('type')
                            ->label('Page Type')
                            ->options([
                                'page' => 'Regular Page',
                                'terms' => 'Terms & Conditions',
                                'privacy' => 'Privacy Policy',
                                'about' => 'About Us',
                                'help' => 'Help/FAQ',
                                'custom' => 'Custom',
                            ])
                            ->required()
                            ->default('page')
                            ->helperText('System pages (Terms, Privacy) cannot be deleted'),
                        Forms\Components\Select::make('locale')
                            ->label('Language')
                            ->options(function () {
                                return Language::where('is_active', true)->pluck('name', 'code');
                            })
                            ->default('en')
                            ->required()
                            ->searchable(),
                        Forms\Components\RichEditor::make('content')
                            ->label('Page Content')
                            ->required()
                            ->columnSpanFull()
                            ->helperText('This is the full page content. You can use rich text formatting.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Visibility Controls')
                    ->description('Control where and how this page appears')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->required()
                            ->helperText('Only active pages are visible to visitors'),
                        Forms\Components\Toggle::make('show_in_web')
                            ->label('Show in Web')
                            ->default(true)
                            ->helperText('Display this page on the web storefront'),
                        Forms\Components\Toggle::make('show_in_app')
                            ->label('Show in App')
                            ->default(true)
                            ->helperText('Display this page in the mobile app'),
                        Forms\Components\Toggle::make('show_in_footer')
                            ->label('Show in Footer')
                            ->default(false)
                            ->helperText('Add a link to this page in the website footer'),
                        Forms\Components\Toggle::make('show_in_header')
                            ->label('Show in Header')
                            ->default(false)
                            ->helperText('Add a link to this page in the website header menu'),
                        Forms\Components\Toggle::make('requires_login')
                            ->label('Requires Login')
                            ->default(false)
                            ->helperText('Only logged-in users can view this page'),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Forms\Components\Section::make('SEO Settings')
                    ->description('Search engine optimization settings')
                    ->schema([
                        Forms\Components\TextInput::make('seo_title')
                            ->label('SEO Title')
                            ->maxLength(60)
                            ->helperText('Title for search engines (recommended: 50-60 characters)')
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('preview_seo')
                                    ->label('Preview')
                                    ->icon('heroicon-o-eye')
                                    ->action(function ($get) {
                                        $title = $get('seo_title') ?: $get('title');
                                        $description = $get('seo_description') ?: Str::limit(strip_tags($get('content')), 160);
                                        $url = url('/' . ($get('slug') ?: 'page'));
                                        
                                        Notification::make()
                                            ->title('SEO Preview')
                                            ->body("Title: {$title}\n\nDescription: {$description}\n\nURL: {$url}")
                                            ->info()
                                            ->persistent()
                                            ->send();
                                    })
                            ),
                        Forms\Components\Textarea::make('seo_description')
                            ->label('SEO Description')
                            ->rows(3)
                            ->maxLength(160)
                            ->helperText('Meta description for search engines (recommended: 150-160 characters)'),
                        Forms\Components\TagsInput::make('seo_keywords')
                            ->label('SEO Keywords')
                            ->helperText('Comma-separated keywords for search engines')
                            ->dehydrateStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                            ->formatStateUsing(fn ($state) => is_string($state) && !empty($state) ? explode(', ', $state) : []),
                        Forms\Components\Placeholder::make('seo_preview')
                            ->label('How it appears on Google')
                            ->content(function ($get) {
                                $title = $get('seo_title') ?: $get('title') ?: 'Page Title';
                                $description = $get('seo_description') ?: Str::limit(strip_tags($get('content') ?: ''), 160) ?: 'Page description';
                                $url = url('/' . ($get('slug') ?: 'page'));
                                
                                return "<div class='p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700'>
                                    <div class='space-y-2'>
                                        <div class='text-sm text-blue-600 dark:text-blue-400 font-medium'>{$title}</div>
                                        <div class='text-xs text-gray-600 dark:text-gray-400'>{$url}</div>
                                        <div class='text-sm text-gray-700 dark:text-gray-300'>{$description}</div>
                                    </div>
                                </div>";
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Advanced')
                    ->schema([
                        Forms\Components\Select::make('platform')
                            ->label('Platform Scope')
                            ->options([
                                'all' => 'All Platforms',
                                'web' => 'Web Only',
                                'app' => 'App Only',
                            ])
                            ->default('all')
                            ->helperText('Legacy field - use visibility controls above instead'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('slug')
                    ->label('URL Slug')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('URL slug copied!')
                    ->formatStateUsing(fn ($state) => '/' . $state),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'terms', 'privacy' => 'danger',
                        'about', 'help' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('locale')
                    ->label('Language')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('show_in_web')
                    ->label('Web')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('show_in_app')
                    ->label('App')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('show_in_footer')
                    ->label('Footer')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('show_in_header')
                    ->label('Header')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'page' => 'Regular Page',
                        'terms' => 'Terms & Conditions',
                        'privacy' => 'Privacy Policy',
                        'about' => 'About Us',
                        'help' => 'Help/FAQ',
                        'custom' => 'Custom',
                    ]),
                Tables\Filters\SelectFilter::make('locale')
                    ->options(function () {
                        return Language::where('is_active', true)->pluck('name', 'code');
                    }),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
                Tables\Filters\TernaryFilter::make('show_in_footer')
                    ->label('In Footer')
                    ->placeholder('All'),
                Tables\Filters\TernaryFilter::make('show_in_header')
                    ->label('In Header')
                    ->placeholder('All'),
            ])
            ->actions([
                Tables\Actions\Action::make('preview_web')
                    ->label('Preview Web')
                    ->icon('heroicon-o-globe-alt')
                    ->color('info')
                    ->url(fn (CmsPage $record) => url('/' . $record->slug), shouldOpenInNewTab: true)
                    ->visible(fn (CmsPage $record) => $record->show_in_web && $record->is_active),
                Tables\Actions\Action::make('preview_app')
                    ->label('Preview App')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('warning')
                    ->url(fn (CmsPage $record) => url('/app/' . $record->slug), shouldOpenInNewTab: true)
                    ->visible(fn (CmsPage $record) => $record->show_in_app && $record->is_active),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (CmsPage $record) {
                        if ($record->isSystemPage()) {
                            Notification::make()
                                ->title('Cannot delete system page')
                                ->body('System pages (Terms, Privacy) cannot be deleted for legal compliance.')
                                ->danger()
                                ->send();
                            return false;
                        }
                        
                        $before = $record->only(['id', 'title', 'slug']);
                        AuditService::log('cms_page.deleted', $record, $before, [], ['module' => 'cms']);
                    })
                    ->visible(fn (CmsPage $record) => !$record->isSystemPage()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (!$record->is_active) {
                                    $before = ['is_active' => false];
                                    $record->is_active = true;
                                    $record->save();
                                    AuditService::log('cms_page.updated', $record, $before, ['is_active' => true], ['module' => 'cms']);
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title("{$count} pages activated")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->is_active) {
                                    $before = ['is_active' => false];
                                    $record->is_active = false;
                                    $record->save();
                                    AuditService::log('cms_page.updated', $record, $before, ['is_active' => false], ['module' => 'cms']);
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title("{$count} pages deactivated")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->isSystemPage()) {
                                    Notification::make()
                                        ->title('Cannot delete system pages')
                                        ->body('Some selected pages are system pages and cannot be deleted.')
                                        ->warning()
                                        ->send();
                                    return false;
                                }
                            }
                        })
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if (!$record->isSystemPage()) {
                                    $before = $record->only(['id', 'title', 'slug']);
                                    AuditService::log('cms_page.deleted', $record, $before, [], ['module' => 'cms']);
                                    $record->delete();
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCmsPages::route('/'),
            'create' => Pages\CreateCmsPage::route('/create'),
            'view' => Pages\ViewCmsPage::route('/{record}'),
            'edit' => Pages\EditCmsPage::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->can('cms_page.view') || $user?->hasRole('super_admin') || $user?->hasRole('Super Admin') ?? false;
    }
}
