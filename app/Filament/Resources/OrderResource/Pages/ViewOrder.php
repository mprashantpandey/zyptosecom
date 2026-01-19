<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Core\Services\AuditService;
use App\Filament\Resources\OrderResource;
use App\Models\OrderStatusHistory;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('update_status')
                ->label('Update Status')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->form([
                    Forms\Components\Select::make('status')
                        ->label('New Status')
                        ->options([
                            'pending' => 'Pending',
                            'confirmed' => 'Confirmed',
                            'processing' => 'Processing',
                            'shipped' => 'Shipped',
                            'delivered' => 'Delivered',
                            'cancelled' => 'Cancelled',
                            'refunded' => 'Refunded',
                        ])
                        ->required()
                        ->default(fn ($record) => $record->status),
                    Forms\Components\Textarea::make('note')
                        ->label('Status Note')
                        ->required()
                        ->maxLength(500)
                        ->rows(3)
                        ->helperText('Reason for status change'),
                    Forms\Components\Toggle::make('notify_customer')
                        ->label('Notify Customer')
                        ->default(true)
                        ->helperText('Send email notification to customer'),
                ])
                ->action(function (array $data): void {
                    $record = $this->record;
                    $oldStatus = $record->status;
                    
                    DB::transaction(function () use ($record, $data, $oldStatus) {
                        $record->update([
                            'status' => $data['status'],
                            'status_note' => $data['note'],
                        ]);

                        OrderStatusHistory::create([
                            'order_id' => $record->id,
                            'status' => $data['status'],
                            'note' => $data['note'],
                            'changed_by' => auth()->id(),
                        ]);

                        AuditService::log(
                            'order.status_changed',
                            $record,
                            ['status' => $oldStatus],
                            ['status' => $data['status'], 'note' => $data['note']],
                            ['notify_customer' => $data['notify_customer'] ?? false]
                        );
                    });

                    $this->refreshFormData(['status', 'status_note']);
                })
                ->requiresConfirmation()
                ->modalHeading('Update Order Status'),
            Actions\Action::make('print_invoice')
                ->label('Print Invoice')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(fn (): string => route('admin.orders.invoice', $this->record))
                ->openUrlInNewTab(),
            Actions\EditAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            OrderResource\RelationManagers\OrderItemsRelationManager::class,
            OrderResource\RelationManagers\StatusHistoryRelationManager::class,
        ];
    }
}
