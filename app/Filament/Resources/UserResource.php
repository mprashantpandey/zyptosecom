<?php

namespace App\Filament\Resources;

use App\Core\Services\AuditService;
use App\Filament\Pages\CustomerDetail;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Users';
    protected static ?string $navigationLabel = 'Customers';
    protected static ?int $navigationSort = 1;

    /**
     * Scope to customers only
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', 'customer');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Customer Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\Select::make('is_active')
                            ->label('Status')
                            ->options([
                                true => 'Active',
                                false => 'Blocked',
                            ])
                            ->required()
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('internal_notes')
                            ->label('Internal Notes')
                            ->rows(4)
                            ->maxLength(1000)
                            ->helperText('Private notes about this customer (not visible to customer)')
                            ->columnSpanFull()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $state, $record) {
                                if ($record && $record->metadata) {
                                    $component->state($record->metadata['internal_notes'] ?? '');
                                }
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (User $record) => $record->is_active ? 'Active' : 'Blocked')
                    ->badge()
                    ->color(fn (User $record) => $record->is_active ? 'success' : 'danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->counts('orders')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Total Spent')
                    ->formatStateUsing(fn (User $record) => '₹' . number_format($record->total_spent, 2))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withCount(['orders as total_spent_sum' => function ($q) {
                            $q->where('payment_status', 'paid')
                              ->select(DB::raw('COALESCE(SUM(total_amount), 0)'));
                        }])->orderBy('total_spent_sum', $direction);
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('wallet_balance')
                    ->label('Wallet')
                    ->formatStateUsing(fn (User $record) => '₹' . number_format($record->wallet_balance, 2))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->leftJoin('wallets', 'users.id', '=', 'wallets.user_id')
                            ->orderBy('wallets.balance', $direction)
                            ->select('users.*');
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        true => 'Active',
                        false => 'Blocked',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Registered From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Registered Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date)
                            );
                    }),
                Tables\Filters\SelectFilter::make('order_count')
                    ->label('Order Count')
                    ->options([
                        'none' => 'No orders',
                        '1-5' => '1 to 5 orders',
                        '5+' => 'More than 5 orders',
                    ])
                    ->query(function (Builder $query, string $state): Builder {
                        return match($state) {
                            'none' => $query->doesntHave('orders'),
                            '1-5' => $query->has('orders', '>=', 1)->has('orders', '<=', 5),
                            '5+' => $query->has('orders', '>', 5),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_detail')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->url(fn (User $record) => CustomerDetail::getUrl(['record' => $record->id])),
                Tables\Actions\Action::make('toggle_status')
                    ->label(fn (User $record) => $record->is_active ? 'Block' : 'Unblock')
                    ->icon(fn (User $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (User $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $before = ['is_active' => $record->is_active];
                        $record->is_active = !$record->is_active;
                        $record->save();
                        AuditService::log('customer.status_changed', $record, $before, ['is_active' => $record->is_active], ['module' => 'customers']);
                    }),
                Tables\Actions\Action::make('adjust_wallet')
                    ->label('Adjust Wallet')
                    ->icon('heroicon-o-wallet')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->required()
                            ->helperText('Positive for credit, negative for debit'),
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->action(function (User $record, array $data) {
                        $wallet = $record->wallet ?? Wallet::create([
                            'user_id' => $record->id,
                            'balance' => 0,
                            'currency' => 'INR',
                        ]);

                        $balanceBefore = $wallet->balance;
                        $amount = (float)$data['amount'];
                        $balanceAfter = $balanceBefore + $amount;

                        DB::transaction(function () use ($wallet, $amount, $balanceBefore, $balanceAfter, $data, $record) {
                            $wallet->update(['balance' => $balanceAfter]);

                            WalletTransaction::create([
                                'wallet_id' => $wallet->id,
                                'type' => $amount >= 0 ? 'credit' : 'debit',
                                'source' => 'admin',
                                'amount' => abs($amount),
                                'balance_before' => $balanceBefore,
                                'balance_after' => $balanceAfter,
                                'description' => $data['reason'],
                            ]);

                            AuditService::log('wallet.adjusted', $record, [
                                'balance_before' => $balanceBefore,
                                'amount' => $amount,
                            ], [
                                'balance_after' => $balanceAfter,
                                'reason' => $data['reason'],
                            ], ['module' => 'customers']);
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Wallet adjusted successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (User $record) {
                        $before = $record->only(['id', 'name', 'email', 'is_active']);
                        AuditService::log('customer.deleted', $record, $before, [], ['module' => 'customers']);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('block')
                        ->label('Block Selected')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Block Selected Customers')
                        ->modalDescription('Are you sure you want to block these customers? They will not be able to login or place orders.')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $before = ['is_active' => $record->is_active];
                                $record->is_active = false;
                                $record->save();
                                AuditService::log('customer.status_changed', $record, $before, ['is_active' => false], ['module' => 'customers']);
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Customers blocked')
                                ->body(count($records) . ' customer(s) have been blocked')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('unblock')
                        ->label('Unblock Selected')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Unblock Selected Customers')
                        ->modalDescription('Are you sure you want to unblock these customers?')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $before = ['is_active' => $record->is_active];
                                $record->is_active = true;
                                $record->save();
                                AuditService::log('customer.status_changed', $record, $before, ['is_active' => true], ['module' => 'customers']);
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Customers unblocked')
                                ->body(count($records) . ' customer(s) have been unblocked')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\ExportAction::make()
                        ->label('Export to CSV')
                        ->icon('heroicon-o-arrow-down-tray'),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('users.view') ?? false;
    }
}
