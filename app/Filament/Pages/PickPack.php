<?php

namespace App\Filament\Pages;

use App\Core\Services\AuditService;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class PickPack extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Orders';
    protected static ?string $navigationLabel = 'Pick & Pack';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.pick-pack';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('fulfillment.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->whereIn('status', ['confirmed', 'processing'])
                    ->with(['user', 'items'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable(),
                Tables\Columns\TextColumn::make('shipping_address')
                    ->label('Shipping Address')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return ($state['address_line_1'] ?? '') . ', ' . ($state['city'] ?? '') . ', ' . ($state['state'] ?? '');
                        }
                        return 'N/A';
                    })
                    ->limit(50)
                    ->tooltip(fn ($record) => is_array($record->shipping_address) ? implode(', ', array_filter($record->shipping_address)) : 'N/A'),
                Tables\Columns\IconColumn::make('is_packed')
                    ->label('Packed')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->metadata['is_packed'] ?? false),
                Tables\Columns\IconColumn::make('is_ready_to_ship')
                    ->label('Ready to Ship')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->metadata['is_ready_to_ship'] ?? false),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Order Date')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'processing' => 'Processing',
                    ])
                    ->default('confirmed'),
                Tables\Filters\Filter::make('packing_status')
                    ->form([
                        Forms\Components\Select::make('packed')
                            ->label('Packed Status')
                            ->options([
                                'all' => 'All',
                                'packed' => 'Packed',
                                'not_packed' => 'Not Packed',
                            ])
                            ->default('all'),
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['packed'] === 'packed') {
                            return $query->whereJsonContains('metadata->is_packed', true);
                        } elseif ($data['packed'] === 'not_packed') {
                            return $query->where(function ($q) {
                                $q->whereJsonDoesntContain('metadata->is_packed', true)
                                  ->orWhereNull('metadata->is_packed');
                            });
                        }
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_order')
                    ->label('View Order')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Order $record): string => route('filament.admin.resources.orders.view', $record)),
                Tables\Actions\Action::make('mark_packed')
                    ->label('Mark Packed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $record): bool => !($record->metadata['is_packed'] ?? false))
                    ->requiresConfirmation()
                    ->action(function (Order $record): void {
                        $metadata = $record->metadata ?? [];
                        $metadata['is_packed'] = true;
                        $metadata['packed_at'] = now()->toDateTimeString();
                        $metadata['packed_by'] = auth()->id();

                        $record->update(['metadata' => $metadata]);

                        AuditService::log(
                            'order.packed',
                            $record,
                            ['is_packed' => false],
                            ['is_packed' => true],
                            ['packed_by' => auth()->id()]
                        );

                        Notification::make()
                            ->title('Order marked as packed')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('mark_ready_to_ship')
                    ->label('Ready to Ship')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->visible(fn (Order $record): bool => ($record->metadata['is_packed'] ?? false) && !($record->metadata['is_ready_to_ship'] ?? false))
                    ->requiresConfirmation()
                    ->action(function (Order $record): void {
                        $metadata = $record->metadata ?? [];
                        $metadata['is_ready_to_ship'] = true;
                        $metadata['ready_to_ship_at'] = now()->toDateTimeString();
                        $metadata['ready_to_ship_by'] = auth()->id();

                        $record->update(['metadata' => $metadata]);

                        AuditService::log(
                            'order.ready_to_ship',
                            $record,
                            ['is_ready_to_ship' => false],
                            ['is_ready_to_ship' => true],
                            ['ready_to_ship_by' => auth()->id()]
                        );

                        Notification::make()
                            ->title('Order marked as ready to ship')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('mark_packed_bulk')
                    ->label('Mark as Packed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records): void {
                        foreach ($records as $record) {
                            $metadata = $record->metadata ?? [];
                            $metadata['is_packed'] = true;
                            $metadata['packed_at'] = now()->toDateTimeString();
                            $metadata['packed_by'] = auth()->id();
                            $record->update(['metadata' => $metadata]);

                            AuditService::log(
                                'order.packed',
                                $record,
                                ['is_packed' => false],
                                ['is_packed' => true],
                                ['packed_by' => auth()->id()]
                            );
                        }

                        Notification::make()
                            ->title(count($records) . ' orders marked as packed')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'asc');
    }
}
