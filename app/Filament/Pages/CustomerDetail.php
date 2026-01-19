<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Models\WalletTransaction;
use App\Core\Services\AuditService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class CustomerDetail extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static string $view = 'filament.pages.customer-detail';
    protected static ?string $navigationGroup = 'Customers';
    protected static ?string $navigationLabel = 'Customer Detail';
    protected static bool $shouldRegisterNavigation = false; // Hidden from nav, accessed via UserResource

    public ?User $customer = null;
    public ?array $data = [];

    public function mount($record): void
    {
        abort_unless(auth()->user()->can('users.view'), 403);
        
        $this->customer = User::where('type', 'customer')->findOrFail($record);
        $this->loadCustomerData();
    }

    protected function loadCustomerData(): void
    {
        $this->data = [
            'name' => $this->customer->name,
            'email' => $this->customer->email,
            'phone' => $this->customer->phone,
            'is_active' => $this->customer->is_active,
            'metadata' => $this->customer->metadata ?? [],
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Customer Details')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Profile')
                            ->schema([
                                Forms\Components\Section::make('Basic Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->disabled(),
                                        Forms\Components\TextInput::make('email')
                                            ->disabled(),
                                        Forms\Components\TextInput::make('phone')
                                            ->disabled(),
                                        Forms\Components\Select::make('is_active')
                                            ->label('Status')
                                            ->options([
                                                true => 'Active',
                                                false => 'Blocked',
                                            ])
                                            ->required()
                                            ->afterStateUpdated(function ($state) {
                                                $before = ['is_active' => $this->customer->is_active];
                                                $this->customer->is_active = $state;
                                                $this->customer->save();
                                                
                                                \App\Core\Services\AuditService::log(
                                                    'customer.status_changed',
                                                    $this->customer,
                                                    $before,
                                                    ['is_active' => $state],
                                                    ['module' => 'customers']
                                                );
                                                
                                                Notification::make()
                                                    ->title('Status updated')
                                                    ->success()
                                                    ->send();
                                            }),
                                    ])
                                    ->columns(2),
                                Forms\Components\Section::make('Statistics')
                                    ->schema([
                                        Forms\Components\Placeholder::make('total_orders')
                                            ->label('Total Orders')
                                            ->content(fn () => $this->customer->total_orders),
                                        Forms\Components\Placeholder::make('total_spent')
                                            ->label('Total Spent')
                                            ->content(fn () => '₹' . number_format($this->customer->total_spent, 2)),
                                        Forms\Components\Placeholder::make('wallet_balance')
                                            ->label('Wallet Balance')
                                            ->content(fn () => '₹' . number_format($this->customer->wallet_balance, 2)),
                                        Forms\Components\Placeholder::make('last_login')
                                            ->label('Last Login')
                                            ->content(fn () => $this->customer->last_login_at?->format('M d, Y H:i') ?? 'Never'),
                                    ])
                                    ->columns(4),
                                Forms\Components\Section::make('Internal Notes')
                                    ->schema([
                                        Forms\Components\Textarea::make('internal_notes')
                                            ->label('Internal Notes')
                                            ->rows(4)
                                            ->maxLength(1000)
                                            ->helperText('Private notes about this customer (not visible to customer)')
                                            ->dehydrated(false)
                                            ->afterStateHydrated(function ($component) {
                                                $component->state($this->customer->metadata['internal_notes'] ?? '');
                                            })
                                            ->afterStateUpdated(function ($state) {
                                                $before = $this->customer->metadata ?? [];
                                                $metadata = $this->customer->metadata ?? [];
                                                $metadata['internal_notes'] = $state;
                                                $this->customer->metadata = $metadata;
                                                $this->customer->save();
                                                
                                                \App\Core\Services\AuditService::log(
                                                    'customer.notes_updated',
                                                    $this->customer,
                                                    ['internal_notes' => $before['internal_notes'] ?? ''],
                                                    ['internal_notes' => $state],
                                                    ['module' => 'customers']
                                                );
                                                
                                                Notification::make()
                                                    ->title('Notes saved')
                                                    ->success()
                                                    ->send();
                                            }),
                                    ])
                                    ->collapsible(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Orders')
                            ->schema([
                                Forms\Components\Placeholder::make('orders_table')
                                    ->label('Orders')
                                    ->content('Orders table will be displayed below'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Addresses')
                            ->schema([
                                Forms\Components\Placeholder::make('addresses_table')
                                    ->label('Shipping Addresses')
                                    ->content('Addresses table will be displayed below'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Wallet')
                            ->schema([
                                Forms\Components\Placeholder::make('wallet_balance')
                                    ->label('Current Balance')
                                    ->content(fn () => '₹' . number_format($this->customer->wallet_balance, 2)),
                                Forms\Components\Placeholder::make('wallet_transactions')
                                    ->label('Transaction History')
                                    ->content('Transaction history will be displayed below'),
                            ])
                            ->visible(fn () => $this->customer->wallet !== null),

                        Forms\Components\Tabs\Tab::make('Activity')
                            ->schema([
                                Forms\Components\Placeholder::make('audit_logs')
                                    ->label('Audit Logs')
                                    ->content('Recent activity will be displayed below'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function ordersTable(Table $table): Table
    {
        return $table
            ->query(Order::where('user_id', $this->customer->id))
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'delivered' => 'success',
                        'shipped' => 'info',
                        'processing' => 'warning',
                        'cancelled', 'refunded' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('INR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public function addressesTable(Table $table): Table
    {
        return $table
            ->query(ShippingAddress::where('user_id', $this->customer->id))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('address_line_1')
                    ->limit(50),
                Tables\Columns\TextColumn::make('city')
                    ->searchable(),
                Tables\Columns\TextColumn::make('state')
                    ->searchable(),
                Tables\Columns\TextColumn::make('postal_code')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->boolean()
                    ->label('Default'),
            ])
            ->defaultSort('is_default', 'desc');
    }

    public function walletTransactionsTable(Table $table): Table
    {
        if (!$this->customer->wallet) {
            return $table->query(\App\Models\WalletTransaction::whereRaw('1 = 0')); // Empty query
        }

        return $table
            ->query(WalletTransaction::where('wallet_id', $this->customer->wallet->id))
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state) => $state === 'credit' ? 'success' : 'danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('INR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Balance After')
                    ->money('INR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('block')
                ->label(fn () => $this->customer->is_active ? 'Block Customer' : 'Unblock Customer')
                ->icon(fn () => $this->customer->is_active ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open')
                ->color(fn () => $this->customer->is_active ? 'danger' : 'success')
                ->requiresConfirmation()
                ->modalHeading(fn () => $this->customer->is_active ? 'Block Customer' : 'Unblock Customer')
                ->modalDescription(fn () => $this->customer->is_active 
                    ? 'Are you sure you want to block this customer? They will not be able to login or place orders.'
                    : 'Are you sure you want to unblock this customer?')
                ->action(function () {
                    abort_unless(auth()->user()->can('users.block'), 403);
                    
                    $before = ['is_active' => $this->customer->is_active];
                    $this->customer->is_active = !$this->customer->is_active;
                    $this->customer->save();
                    
                    AuditService::log('customer.status_changed', $this->customer, $before, ['is_active' => $this->customer->is_active], ['module' => 'customers']);
                    
                    Notification::make()
                        ->title($this->customer->is_active ? 'Customer unblocked' : 'Customer blocked')
                        ->success()
                        ->send();
                    
                    $this->loadCustomerData();
                })
                ->visible(auth()->user()->can('users.block')),
            \Filament\Actions\Action::make('adjust_wallet')
                ->label('Adjust Wallet')
                ->icon('heroicon-o-wallet')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Adjust Customer Wallet')
                ->modalDescription('Add or subtract money from this customer\'s wallet balance.')
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label('Amount (₹)')
                        ->numeric()
                        ->required()
                        ->helperText('Enter positive amount to add money, negative amount to deduct money')
                        ->prefix('₹'),
                    Forms\Components\Textarea::make('reason')
                        ->label('Reason')
                        ->required()
                        ->rows(3)
                        ->maxLength(500)
                        ->placeholder('e.g., Refund for order #12345, Manual adjustment, etc.')
                        ->helperText('This reason will be visible in the transaction history'),
                ])
                ->action(function (array $data) {
                    abort_unless(auth()->user()->can('users.wallet.adjust'), 403);
                    
                    $wallet = $this->customer->wallet ?? \App\Models\Wallet::create([
                        'user_id' => $this->customer->id,
                        'balance' => 0,
                        'currency' => 'INR',
                    ]);

                    $balanceBefore = $wallet->balance;
                    $amount = (float)$data['amount'];
                    
                    if ($amount == 0) {
                        Notification::make()
                            ->title('Invalid amount')
                            ->body('Amount cannot be zero')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    $balanceAfter = $balanceBefore + $amount;

                    DB::transaction(function () use ($wallet, $amount, $balanceBefore, $balanceAfter, $data) {
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

                        AuditService::log('wallet.adjusted', $this->customer, [
                            'balance_before' => $balanceBefore,
                            'amount' => $amount,
                        ], [
                            'balance_after' => $balanceAfter,
                            'reason' => $data['reason'],
                        ], ['module' => 'customers', 'admin_id' => auth()->id()]);
                    });

                    Notification::make()
                        ->title('Wallet adjusted successfully')
                        ->body('Balance: ₹' . number_format($balanceBefore, 2) . ' → ₹' . number_format($balanceAfter, 2))
                        ->success()
                        ->send();
                    
                    $this->customer->refresh();
                    $this->loadCustomerData();
                })
                ->visible(auth()->user()->can('users.wallet.adjust')),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('users.view') ?? false;
    }

    public function getTitle(): string
    {
        return 'Customer: ' . ($this->customer?->name ?? 'N/A');
    }
}
