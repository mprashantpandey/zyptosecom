<?php

namespace App\Filament\Resources;

use App\Core\Services\AuditService;
use App\Filament\Resources\DealResource\Pages;
use App\Models\Deal;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class DealResource extends Resource
{
    protected static ?string $model = Deal::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Deals';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Deal Information')
                    ->description('Basic deal details')
                    ->schema([
                        Forms\Components\Placeholder::make('info')
                            ->label('')
                            ->content('Create flash sales and special deals with discounted prices on selected products. Set start and end dates to schedule when deals are active.')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('title')
                            ->label('Deal Title')
                            ->required()
                            ->maxLength(255)
                            ->helperText('A friendly name for this deal (e.g., "Flash Sale - Electronics")'),
                        Forms\Components\Select::make('type')
                            ->label('Deal Type')
                            ->options([
                                'flash' => 'Flash Sale',
                                'banner' => 'Banner Deal',
                                'bundle' => 'Bundle Deal',
                            ])
                            ->required()
                            ->default('flash')
                            ->helperText('Type of deal'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active deals are shown to customers'),
                        Forms\Components\TextInput::make('priority')
                            ->label('Priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher priority deals appear first (0 = lowest)'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Schedule')
                    ->description('When the deal is active')
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Start Date & Time')
                            ->helperText('When the deal becomes active'),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('End Date & Time')
                            ->helperText('When the deal expires')
                            ->after('starts_at'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Products')
                    ->description('Add products to this deal with special prices')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('Products')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->options(fn () => Product::where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                // Set default deal price to 10% off
                                                $defaultPrice = $product->price * 0.9;
                                                $set('deal_price', round($defaultPrice, 2));
                                            }
                                        }
                                    }),
                                Forms\Components\TextInput::make('deal_price')
                                    ->label('Deal Price')
                                    ->numeric()
                                    ->required()
                                    ->suffix('₹')
                                    ->helperText('Special price for this deal'),
                                Forms\Components\TextInput::make('stock_limit')
                                    ->label('Stock Limit')
                                    ->numeric()
                                    ->helperText('Optional: Limit how many units available in this deal'),
                                Forms\Components\TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Order in which products appear'),
                            ])
                            ->columns(4)
                            ->itemLabel(fn (array $state): ?string => 
                                $state['product_id'] ? Product::find($state['product_id'])?->name : 'New Product'
                            )
                            ->defaultItems(0)
                            ->addActionLabel('Add Product')
                            ->collapsible(),
                    ])
                    ->columns(1),
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
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'flash' => 'danger',
                        'banner' => 'info',
                        'bundle' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Deal $record): string => match ($record->status) {
                        'running' => 'success',
                        'upcoming' => 'info',
                        'expired' => 'danger',
                        'inactive' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (Deal $record): string => ucfirst($record->status)),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('items')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active only'),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'flash' => 'Flash Sale',
                        'banner' => 'Banner Deal',
                        'bundle' => 'Bundle Deal',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle')
                    ->label(fn (Deal $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (Deal $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Deal $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Deal $record) {
                        $oldValue = $record->is_active;
                        $record->update([
                            'is_active' => !$oldValue,
                            'updated_by' => auth()->id(),
                        ]);
                        AuditService::log('deal.activated', $record, ['is_active' => $oldValue], ['is_active' => !$oldValue], ['module' => 'promotions']);
                    }),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->action(function (Deal $record) {
                        DB::transaction(function () use ($record) {
                            $newDeal = $record->replicate();
                            $newDeal->title = $record->title . ' (Copy)';
                            $newDeal->is_active = false;
                            $newDeal->created_by = auth()->id();
                            $newDeal->updated_by = auth()->id();
                            $newDeal->save();

                            // Duplicate items
                            foreach ($record->items as $item) {
                                $newItem = $item->replicate();
                                $newItem->deal_id = $newDeal->id;
                                $newItem->save();
                            }

                            AuditService::log('deal.created', $newDeal, [], [], ['module' => 'promotions']);
                        });
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'is_active' => true,
                                    'updated_by' => auth()->id(),
                                ]);
                                AuditService::log('deal.activated', $record, ['is_active' => false], ['is_active' => true], ['module' => 'promotions']);
                            }
                        }),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'is_active' => false,
                                    'updated_by' => auth()->id(),
                                ]);
                                AuditService::log('deal.activated', $record, ['is_active' => true], ['is_active' => false], ['module' => 'promotions']);
                            }
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListDeals::route('/'),
            'create' => Pages\CreateDeal::route('/create'),
            'view' => Pages\ViewDeal::route('/{record}'),
            'edit' => Pages\EditDeal::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('deals.view') ?? false;
    }
}
