<?php

namespace App\Filament\Resources;

use App\Core\Services\AuditService;
use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Coupons';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->description('Coupon title and code')
                    ->schema([
                        Forms\Components\Placeholder::make('info')
                            ->label('')
                            ->content('Create discount coupons that customers can use at checkout. Set conditions, usage limits, and schedule when coupons are active.')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('title')
                            ->label('Coupon Title')
                            ->required()
                            ->maxLength(255)
                            ->helperText('A friendly name for this coupon (e.g., "Summer Sale 20% Off")'),
                        Forms\Components\TextInput::make('code')
                            ->label('Coupon Code')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('The code customers will enter at checkout')
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('generate')
                                    ->icon('heroicon-m-sparkles')
                                    ->action(function ($set) {
                                        $set('code', strtoupper(Str::random(8)));
                                    })
                            ),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText('Optional description shown to customers'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active coupons can be used'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Discount Settings')
                    ->description('How much discount to apply')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Discount Type')
                            ->options([
                                'percentage' => 'Percentage (%)',
                                'fixed' => 'Fixed Amount',
                                'free_shipping' => 'Free Shipping',
                            ])
                            ->required()
                            ->default('percentage')
                            ->live()
                            ->helperText('Choose how the discount is calculated'),
                        Forms\Components\TextInput::make('value')
                            ->label(fn ($get) => match($get('type')) {
                                'percentage' => 'Discount Percentage',
                                'fixed' => 'Discount Amount',
                                'free_shipping' => 'N/A (Free Shipping)',
                                default => 'Value',
                            })
                            ->numeric()
                            ->required(fn ($get) => $get('type') !== 'free_shipping')
                            ->visible(fn ($get) => $get('type') !== 'free_shipping')
                            ->suffix(fn ($get) => $get('type') === 'percentage' ? '%' : '₹')
                            ->helperText(fn ($get) => match($get('type')) {
                                'percentage' => 'Enter percentage (e.g., 20 for 20% off)',
                                'fixed' => 'Enter fixed amount in default currency',
                                default => '',
                            }),
                        Forms\Components\TextInput::make('max_discount_amount')
                            ->label('Maximum Discount')
                            ->numeric()
                            ->visible(fn ($get) => $get('type') === 'percentage')
                            ->suffix('₹')
                            ->helperText('Optional: Limit the maximum discount amount for percentage coupons'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Conditions')
                    ->description('When and how the coupon can be used')
                    ->schema([
                        Forms\Components\TextInput::make('min_order_amount')
                            ->label('Minimum Order Amount')
                            ->numeric()
                            ->suffix('₹')
                            ->helperText('Minimum order total required to use this coupon'),
                        Forms\Components\TextInput::make('usage_limit_total')
                            ->label('Total Usage Limit')
                            ->numeric()
                            ->helperText('Maximum number of times this coupon can be used (leave empty for unlimited)'),
                        Forms\Components\TextInput::make('usage_limit_per_user')
                            ->label('Usage Limit Per Customer')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->helperText('How many times each customer can use this coupon'),
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Start Date & Time')
                            ->helperText('When the coupon becomes active'),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('End Date & Time')
                            ->helperText('When the coupon expires')
                            ->after('starts_at'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Applies To')
                    ->description('Which products or categories this coupon applies to')
                    ->schema([
                        Forms\Components\Select::make('applies_to')
                            ->label('Applies To')
                            ->options([
                                'all' => 'All Products',
                                'categories' => 'Selected Categories',
                                'products' => 'Selected Products',
                            ])
                            ->required()
                            ->default('all')
                            ->live()
                            ->helperText('Choose which products this coupon applies to'),
                        Forms\Components\Select::make('applicable_categories')
                            ->label('Categories')
                            ->multiple()
                            ->options(fn () => Category::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get) => $get('applies_to') === 'categories')
                            ->required(fn ($get) => $get('applies_to') === 'categories')
                            ->helperText('Select categories this coupon applies to'),
                        Forms\Components\Select::make('applicable_products')
                            ->label('Products')
                            ->multiple()
                            ->options(fn () => Product::where('is_active', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn ($get) => $get('applies_to') === 'products')
                            ->required(fn ($get) => $get('applies_to') === 'products')
                            ->helperText('Select specific products this coupon applies to'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'percentage' => 'success',
                        'fixed' => 'info',
                        'free_shipping' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'percentage' => 'Percentage',
                        'fixed' => 'Fixed',
                        'free_shipping' => 'Free Shipping',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('value')
                    ->label('Value')
                    ->formatStateUsing(fn (Coupon $record): string => match ($record->type) {
                        'percentage' => "{$record->value}%",
                        'fixed' => "₹{$record->value}",
                        'free_shipping' => 'Free',
                        default => (string) $record->value,
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Coupon $record): string => match ($record->status) {
                        'running' => 'success',
                        'upcoming' => 'info',
                        'expired' => 'danger',
                        'inactive' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (Coupon $record): string => ucfirst($record->status)),
                Tables\Columns\TextColumn::make('usage')
                    ->label('Usage')
                    ->formatStateUsing(fn (Coupon $record): string => 
                        ($record->used_count ?? 0) . '/' . ($record->usage_limit_total ?? '∞')
                    )
                    ->sortable('used_count'),
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
                        'percentage' => 'Percentage',
                        'fixed' => 'Fixed',
                        'free_shipping' => 'Free Shipping',
                    ]),
                Tables\Filters\Filter::make('status')
                    ->label('Schedule Status')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options([
                                'running' => 'Running',
                                'upcoming' => 'Upcoming',
                                'expired' => 'Expired',
                                'inactive' => 'Inactive',
                            ]),
                    ])
                    ->query(function ($query, array $data) {
                        if (!isset($data['status'])) {
                            return $query;
                        }
                        $status = $data['status'];
                        $now = now();
                        return match ($status) {
                            'running' => $query->where('is_active', true)
                                ->where(function ($q) use ($now) {
                                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                                })
                                ->where(function ($q) use ($now) {
                                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                                }),
                            'upcoming' => $query->where('is_active', true)
                                ->where('starts_at', '>', $now),
                            'expired' => $query->where(function ($q) use ($now) {
                                $q->where('ends_at', '<', $now)->orWhere('is_active', false);
                            }),
                            'inactive' => $query->where('is_active', false),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle')
                    ->label(fn (Coupon $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (Coupon $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Coupon $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Coupon $record) {
                        $oldValue = $record->is_active;
                        $record->update([
                            'is_active' => !$oldValue,
                            'updated_by' => auth()->id(),
                        ]);
                        AuditService::log('coupon.status_changed', $record, ['is_active' => $oldValue], ['is_active' => !$oldValue], ['module' => 'promotions']);
                    }),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('new_code')
                            ->label('New Code')
                            ->required()
                            ->maxLength(50)
                            ->unique('coupons', 'code'),
                    ])
                    ->action(function (Coupon $record, array $data) {
                        $newCoupon = $record->replicate();
                        $newCoupon->code = $data['new_code'];
                        $newCoupon->title = $record->title . ' (Copy)';
                        $newCoupon->used_count = 0;
                        $newCoupon->is_active = false;
                        $newCoupon->created_by = auth()->id();
                        $newCoupon->updated_by = auth()->id();
                        $newCoupon->save();
                        AuditService::log('coupon.created', $newCoupon, [], [], ['module' => 'promotions']);
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
                                AuditService::log('coupon.status_changed', $record, ['is_active' => false], ['is_active' => true], ['module' => 'promotions']);
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
                                AuditService::log('coupon.status_changed', $record, ['is_active' => true], ['is_active' => false], ['module' => 'promotions']);
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
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'view' => Pages\ViewCoupon::route('/{record}'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('coupons.view') ?? false;
    }
}
