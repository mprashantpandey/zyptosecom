<?php

namespace App\Filament\Resources;

use App\Core\Services\AuditService;
use App\Core\Services\NotificationService;
use App\Filament\Resources\NotificationLogResource\Pages;
use Filament\Notifications\Notification;
use App\Models\NotificationLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NotificationLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Notifications';
    protected static ?string $navigationLabel = 'Notification Logs';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Log Details')
                    ->schema([
                        Forms\Components\Placeholder::make('channel')
                            ->label('Channel')
                            ->content(fn (NotificationLog $record) => ucfirst($record->channel)),
                        Forms\Components\Placeholder::make('event_key')
                            ->label('Event')
                            ->content(fn (NotificationLog $record) => $record->event_key ?? 'N/A'),
                        Forms\Components\Placeholder::make('recipient')
                            ->label('Recipient')
                            ->content(fn (NotificationLog $record) => $record->recipient),
                        Forms\Components\Placeholder::make('status')
                            ->label('Status')
                            ->content(fn (NotificationLog $record) => ucfirst($record->status))
                            ->badge()
                            ->color(fn (NotificationLog $record) => match($record->status) {
                                'sent' => 'success',
                                'failed' => 'danger',
                                'queued' => 'warning',
                                default => 'gray',
                            }),
                        Forms\Components\Placeholder::make('error_message')
                            ->label('Error Message')
                            ->content(fn (NotificationLog $record) => $record->error_message ?? 'N/A')
                            ->visible(fn (NotificationLog $record) => !empty($record->error_message)),
                        Forms\Components\Placeholder::make('sent_at')
                            ->label('Sent At')
                            ->content(fn (NotificationLog $record) => $record->sent_at?->format('M d, Y H:i') ?? 'N/A'),
                        Forms\Components\KeyValue::make('payload')
                            ->label('Payload (Safe)')
                            ->disabled()
                            ->default(fn (NotificationLog $record) => $record->payload ?? []),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(NotificationLog::query())
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_key')
                    ->label('Event')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('channel')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'email' => 'primary',
                        'sms' => 'info',
                        'push' => 'warning',
                        'whatsapp' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('recipient')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'queued' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('provider_key')
                    ->label('Provider')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(50)
                    ->wrap()
                    ->visible(fn (NotificationLog $record) => $record->status === 'failed'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'queued' => 'Queued',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('channel')
                    ->options([
                        'email' => 'Email',
                        'sms' => 'SMS',
                        'push' => 'Push',
                        'whatsapp' => 'WhatsApp',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
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
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (NotificationLog $record) => $record->status === 'failed')
                    ->action(function (NotificationLog $record) {
                        abort_unless(auth()->user()->can('notifications.logs.retry'), 403);
                        
                        try {
                            $notificationService = app(NotificationService::class);
                            $result = $notificationService->send(
                                $record->event_key ?? 'test',
                                $record->channel,
                                $record->recipient,
                                $record->payload ?? []
                            );
                            
                            if ($result->status === 'sent') {
                                Notification::make()
                                    ->title('Notification retried successfully')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Retry failed')
                                    ->body($result->error_message ?? 'Unknown error')
                                    ->danger()
                                    ->send();
                            }
                            
                            AuditService::log('notifications.retry', $record, [], [
                                'new_status' => $result->status,
                            ], ['module' => 'notifications']);
                            
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Retry failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportAction::make()
                        ->label('Export CSV'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationLogs::route('/'),
            'view' => Pages\ViewNotificationLog::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('notifications.logs.view') ?? false;
    }
}
