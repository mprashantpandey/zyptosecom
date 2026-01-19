<?php

namespace App\Filament\Resources;

use App\Core\Services\AuditService;
use App\Filament\Resources\RefundResource\Pages;
use App\Models\Refund;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Refunds';
    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('refunds.view') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Refund Information')
                    ->schema([
                        Forms\Components\TextInput::make('refund_number')
                            ->label('Refund Number')
                            ->required()
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\Select::make('order_id')
                            ->label('Order')
                            ->relationship('order', 'order_number')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $order = \App\Models\Order::find($state);
                                    if ($order) {
                                        $set('amount', $order->total_amount);
                                        $set('user_id', $order->user_id);
                                    }
                                }
                            }),
                        Forms\Components\Select::make('payment_id')
                            ->label('Payment')
                            ->relationship('payment', 'transaction_id', fn ($query, $get) => 
                                $query->where('order_id', $get('order_id'))
                            )
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\Select::make('user_id')
                            ->label('Customer')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Refund Details')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Refund Amount')
                            ->numeric()
                            ->prefix('₹')
                            ->required()
                            ->helperText('Amount to be refunded'),
                        Forms\Components\Select::make('method')
                            ->label('Refund Method')
                            ->options([
                                'gateway_refund' => 'Gateway Refund (Original Payment Method)',
                                'wallet_credit' => 'Wallet Credit',
                                'manual' => 'Manual (Bank Transfer/Cash)',
                            ])
                            ->required()
                            ->helperText('How the refund will be processed'),
                        Forms\Components\Textarea::make('reason')
                            ->label('Customer Reason')
                            ->maxLength(500)
                            ->rows(3)
                            ->helperText('Reason provided by customer'),
                        Forms\Components\Textarea::make('admin_note')
                            ->label('Admin Note')
                            ->maxLength(500)
                            ->rows(3)
                            ->helperText('Internal note for this refund'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                            ])
                            ->required()
                            ->disabled(fn ($record) => $record && in_array($record->status, ['completed', 'failed']))
                            ->dehydrated(),
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->maxLength(500)
                            ->rows(3)
                            ->visible(fn ($get) => $get('status') === 'rejected')
                            ->required(fn ($get) => $get('status') === 'rejected'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('refund_number')
                    ->label('Refund #')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('INR')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('method')
                    ->label('Method')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'gateway_refund' => 'Gateway',
                        'wallet_credit' => 'Wallet',
                        'manual' => 'Manual',
                        default => ucfirst($state),
                    }),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'info' => 'processing',
                        'gray' => 'completed',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->visible(fn ($record) => $record && $record->approved_by),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('method')
                    ->label('Method')
                    ->options([
                        'gateway_refund' => 'Gateway Refund',
                        'wallet_credit' => 'Wallet Credit',
                        'manual' => 'Manual',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Refund $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('admin_note')
                            ->label('Admin Note')
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->action(function (Refund $record, array $data): void {
                        DB::transaction(function () use ($record, $data) {
                            $record->update([
                                'status' => 'approved',
                                'approved_by' => auth()->id(),
                                'approved_at' => now(),
                                'admin_note' => $data['admin_note'] ?? $record->admin_note,
                            ]);

                            // Process refund based on method
                            if ($record->method === 'wallet_credit') {
                                $wallet = Wallet::firstOrCreate(
                                    ['user_id' => $record->user_id],
                                    ['balance' => 0, 'currency' => 'INR', 'is_active' => true]
                                );

                                $balanceBefore = $wallet->balance;
                                $wallet->increment('balance', $record->amount);
                                $balanceAfter = $wallet->balance;

                                WalletTransaction::create([
                                    'wallet_id' => $wallet->id,
                                    'type' => 'credit',
                                    'source' => 'refund',
                                    'amount' => $record->amount,
                                    'balance_before' => $balanceBefore,
                                    'balance_after' => $balanceAfter,
                                    'description' => "Refund for order {$record->order->order_number}",
                                    'related_type' => Refund::class,
                                    'related_id' => $record->id,
                                ]);
                            }

                            AuditService::log(
                                'refund.approved',
                                $record,
                                ['status' => 'pending'],
                                ['status' => 'approved', 'admin_note' => $data['admin_note'] ?? null],
                                ['approved_by' => auth()->id()]
                            );
                        });
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Refund $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->action(function (Refund $record, array $data): void {
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        AuditService::log(
                            'refund.rejected',
                            $record,
                            ['status' => 'pending'],
                            ['status' => 'rejected', 'rejection_reason' => $data['rejection_reason']],
                            ['rejected_by' => auth()->id()]
                        );
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Refund $record): bool => !in_array($record->status, ['completed', 'failed'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn ($records) => 'Export functionality to be implemented'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRefunds::route('/'),
            'create' => Pages\CreateRefund::route('/create'),
            'view' => Pages\ViewRefund::route('/{record}'),
            'edit' => Pages\EditRefund::route('/{record}/edit'),
        ];
    }
}
