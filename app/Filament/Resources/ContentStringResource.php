<?php

namespace App\Filament\Resources;

use App\Core\Services\AuditService;
use App\Filament\Resources\ContentStringResource\Pages;
use App\Models\ContentString;
use App\Models\Language;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ContentStringResource extends Resource
{
    protected static ?string $model = ContentString::class;

    protected static ?string $navigationIcon = 'heroicon-o-language';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Text & Language';
    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        $groups = ContentString::getGroupLabels();
        
        return $form
            ->schema([
                Forms\Components\Section::make('Text Information')
                    ->description('Manage text strings used throughout your store')
                    ->schema([
                        Forms\Components\TextInput::make('display_name')
                            ->label('Display Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Human-readable name (e.g., "Add to Cart Button")')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                // Auto-generate key from display name if key is empty
                                if (!$get('key') && $state) {
                                    $group = $get('group') ?: 'general';
                                    $key = $group . '.' . Str::slug(Str::lower($state));
                                    $set('key', $key);
                                }
                            }),
                        Forms\Components\TextInput::make('key')
                            ->label('Internal Key')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Technical key used in code (e.g., checkout.add_to_cart)')
                            ->visible(fn ($get) => $get('key') || auth()->user()?->hasRole('super_admin'))
                            ->disabled(fn ($record) => $record?->is_system ?? false),
                        Forms\Components\Select::make('group')
                            ->label('Group')
                            ->options($groups)
                            ->required()
                            ->default('general')
                            ->live()
                            ->helperText('Category for organizing strings'),
                        Forms\Components\Select::make('locale')
                            ->label('Language')
                            ->options(function () {
                                return Language::where('is_active', true)->pluck('name', 'code');
                            })
                            ->default('en')
                            ->required()
                            ->searchable()
                            ->helperText('Language for this text string'),
                        Forms\Components\Textarea::make('value')
                            ->label('Text Value')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('The actual text that will be displayed. Use {{variable}} for dynamic content.'),
                        Forms\Components\Textarea::make('usage_hint')
                            ->label('Usage Hint')
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText('Where this text is used (e.g., "Checkout Page, Cart Button")')
                            ->columnSpanFull(),
                        Forms\Components\TagsInput::make('variables')
                            ->label('Available Variables')
                            ->helperText('Variables that can be used in the text (e.g., {{product_name}}, {{price}})')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

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
                            ->helperText('Where this text string is used'),
                        Forms\Components\Toggle::make('is_system')
                            ->label('System String')
                            ->default(false)
                            ->disabled()
                            ->helperText('System strings cannot be deleted')
                            ->visible(fn ($record) => $record?->is_system ?? false),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $groups = ContentString::getGroupLabels();
        
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Display Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state, $record) => $state ?: $record->key),
                Tables\Columns\TextColumn::make('key')
                    ->label('Key')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Key copied!')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('value')
                    ->label('Text Value')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),
                Tables\Columns\TextColumn::make('group')
                    ->label('Group')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'general' => 'gray',
                        'authentication' => 'info',
                        'checkout' => 'success',
                        'orders' => 'warning',
                        'cart' => 'primary',
                        'errors' => 'danger',
                        'notifications' => 'purple',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $groups[$state] ?? $state)
                    ->sortable(),
                Tables\Columns\TextColumn::make('locale')
                    ->label('Language')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('usage_hint')
                    ->label('Used In')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_system')
                    ->label('System')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->label('Group')
                    ->options($groups),
                Tables\Filters\SelectFilter::make('locale')
                    ->label('Language')
                    ->options(function () {
                        return Language::where('is_active', true)->pluck('name', 'code');
                    }),
                Tables\Filters\TernaryFilter::make('is_system')
                    ->label('System Strings')
                    ->placeholder('All')
                    ->trueLabel('System only')
                    ->falseLabel('Custom only'),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading('Text Preview')
                    ->modalContent(function (ContentString $record) {
                        return view('filament.components.text-preview', [
                            'text' => $record->value,
                            'variables' => $record->variables ?? [],
                        ]);
                    })
                    ->modalSubmitAction(false),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (ContentString $record) {
                        if ($record->is_system) {
                            Notification::make()
                                ->title('Cannot delete system string')
                                ->body('System strings are required for core functionality and cannot be deleted.')
                                ->danger()
                                ->send();
                            return false;
                        }
                        
                        $before = $record->only(['id', 'key', 'display_name']);
                        AuditService::log('content_string.deleted', $record, $before, [], ['module' => 'cms']);
                    })
                    ->visible(fn (ContentString $record) => !$record->is_system),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->is_system) {
                                    Notification::make()
                                        ->title('Cannot delete system strings')
                                        ->body('Some selected strings are system strings and cannot be deleted.')
                                        ->warning()
                                        ->send();
                                    return false;
                                }
                            }
                        })
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if (!$record->is_system) {
                                    $before = $record->only(['id', 'key', 'display_name']);
                                    AuditService::log('content_string.deleted', $record, $before, [], ['module' => 'cms']);
                                    $record->delete();
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('group', 'asc')
            ->groups([
                Tables\Grouping\Group::make('group')
                    ->label('Group')
                    ->collapsible(),
                Tables\Grouping\Group::make('locale')
                    ->label('Language')
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContentStrings::route('/'),
            'create' => Pages\CreateContentString::route('/create'),
            'view' => Pages\ViewContentString::route('/{record}'),
            'edit' => Pages\EditContentString::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->can('content_string.view') || $user?->hasRole('super_admin') || $user?->hasRole('Super Admin') ?? false;
    }
}
