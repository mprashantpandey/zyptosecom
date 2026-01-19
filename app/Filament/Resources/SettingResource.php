<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Filament\Resources\SettingResource\RelationManagers;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('Unique setting key (e.g., app.name)'),
                Forms\Components\Textarea::make('value')
                    ->label('Value')
                    ->required()
                    ->rows(3)
                    ->helperText('Value as string, JSON, or number'),
                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options([
                        'string' => 'String',
                        'json' => 'JSON',
                        'number' => 'Number',
                        'integer' => 'Integer',
                        'boolean' => 'Boolean',
                        'float' => 'Float',
                    ])
                    ->required()
                    ->default('string'),
                Forms\Components\Select::make('group')
                    ->label('Group')
                    ->options([
                        'general' => 'General',
                        'store' => 'Store',
                        'localization' => 'Localization',
                        'tax' => 'Tax',
                        'shipping' => 'Shipping',
                        'notifications' => 'Notifications',
                        'branding' => 'Branding',
                        'app' => 'App',
                        'payments' => 'Payments',
                    ])
                    ->required()
                    ->default('general'),
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(2),
                Forms\Components\Toggle::make('is_public')
                    ->label('Public')
                    ->helperText('Can be exposed to public APIs')
                    ->default(false),
                Forms\Components\Toggle::make('is_encrypted')
                    ->label('Encrypted')
                    ->helperText('Value should be encrypted at rest')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'json' => 'info',
                        'boolean' => 'success',
                        'number', 'integer', 'float' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('group')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_public')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_encrypted')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'string' => 'String',
                        'json' => 'JSON',
                        'number' => 'Number',
                        'boolean' => 'Boolean',
                    ]),
                Tables\Filters\SelectFilter::make('group')
                    ->options([
                        'general' => 'General',
                        'store' => 'Store',
                        'localization' => 'Localization',
                        'tax' => 'Tax',
                        'shipping' => 'Shipping',
                        'notifications' => 'Notifications',
                    ]),
                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Public')
                    ->placeholder('All')
                    ->trueLabel('Public only')
                    ->falseLabel('Private only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'view' => Pages\ViewSetting::route('/{record}'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}
