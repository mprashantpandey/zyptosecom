<?php

namespace App\Filament\Pages;

use App\Core\Services\AuditService;
use App\Models\NotificationEvent;
use App\Models\NotificationEventChannel;
use App\Models\NotificationTemplate;
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

class NotificationEventsToggle extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static string $view = 'filament.pages.notification-events-toggle';
    protected static ?string $navigationGroup = 'Notifications';
    protected static ?string $navigationLabel = 'Event Channels';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'notifications/events';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('notifications.events'), 403);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(NotificationEvent::query())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (NotificationEvent $record) => $record->description),
                Tables\Columns\IconColumn::make('email_enabled')
                    ->label('Email')
                    ->boolean()
                    ->getStateUsing(fn (NotificationEvent $record) => $this->isChannelEnabled($record, 'email')),
                Tables\Columns\TextColumn::make('email_template')
                    ->label('Email Template')
                    ->getStateUsing(fn (NotificationEvent $record) => $this->getTemplateName($record, 'email'))
                    ->limit(30),
                Tables\Columns\IconColumn::make('sms_enabled')
                    ->label('SMS')
                    ->boolean()
                    ->getStateUsing(fn (NotificationEvent $record) => $this->isChannelEnabled($record, 'sms')),
                Tables\Columns\TextColumn::make('sms_template')
                    ->label('SMS Template')
                    ->getStateUsing(fn (NotificationEvent $record) => $this->getTemplateName($record, 'sms'))
                    ->limit(30),
                Tables\Columns\IconColumn::make('push_enabled')
                    ->label('Push')
                    ->boolean()
                    ->getStateUsing(fn (NotificationEvent $record) => $this->isChannelEnabled($record, 'push')),
                Tables\Columns\TextColumn::make('push_template')
                    ->label('Push Template')
                    ->getStateUsing(fn (NotificationEvent $record) => $this->getTemplateName($record, 'push'))
                    ->limit(30),
                Tables\Columns\IconColumn::make('whatsapp_enabled')
                    ->label('WhatsApp')
                    ->boolean()
                    ->getStateUsing(fn (NotificationEvent $record) => $this->isChannelEnabled($record, 'whatsapp')),
                Tables\Columns\TextColumn::make('whatsapp_template')
                    ->label('WhatsApp Template')
                    ->getStateUsing(fn (NotificationEvent $record) => $this->getTemplateName($record, 'whatsapp'))
                    ->limit(30),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_system')
                    ->label('Type')
                    ->options([
                        true => 'System Events',
                        false => 'Custom Events',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('configure')
                    ->label('Configure')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->form(function (NotificationEvent $record) {
                        $channels = ['email', 'sms', 'push', 'whatsapp'];
                        $fields = [];
                        
                        foreach ($channels as $channel) {
                            $channelData = NotificationEventChannel::where('notification_event_id', $record->id)
                                ->where('channel', $channel)
                                ->first();
                            
                            $fields[] = Forms\Components\Section::make(ucfirst($channel))
                                ->schema([
                                    Forms\Components\Toggle::make("{$channel}_enabled")
                                        ->label('Enable ' . ucfirst($channel))
                                        ->default($channelData?->enabled ?? false),
                                    Forms\Components\Select::make("{$channel}_template_id")
                                        ->label('Template')
                                        ->options(fn () => NotificationTemplate::where('channel', $channel)
                                            ->where('is_active', true)
                                            ->pluck('name', 'id'))
                                        ->searchable()
                                        ->default($channelData?->template_id)
                                        ->visible(fn ($get) => $get("{$channel}_enabled")),
                                    Forms\Components\Toggle::make("{$channel}_quiet_hours")
                                        ->label('Respect Quiet Hours')
                                        ->default($channelData?->quiet_hours_respect ?? true)
                                        ->helperText('Defer non-critical notifications during quiet hours')
                                        ->visible(fn ($get) => $get("{$channel}_enabled")),
                                ])
                                ->collapsible();
                        }
                        
                        return $fields;
                    })
                    ->action(function (NotificationEvent $record, array $data) {
                        $channels = ['email', 'sms', 'push', 'whatsapp'];
                        
                        DB::transaction(function () use ($record, $data, $channels) {
                            foreach ($channels as $channel) {
                                $enabled = $data["{$channel}_enabled"] ?? false;
                                $templateId = $enabled ? ($data["{$channel}_template_id"] ?? null) : null;
                                
                                NotificationEventChannel::updateOrCreate(
                                    [
                                        'notification_event_id' => $record->id,
                                        'channel' => $channel,
                                    ],
                                    [
                                        'enabled' => $enabled,
                                        'template_id' => $templateId,
                                        'quiet_hours_respect' => $data["{$channel}_quiet_hours"] ?? true,
                                    ]
                                );
                            }
                            
                            AuditService::log('notifications.event_channel_updated', $record, [], [
                                'channels' => $channels,
                            ], ['module' => 'notifications']);
                        });
                        
                        Notification::make()
                            ->title('Event channels updated')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('name');
    }

    protected function isChannelEnabled(NotificationEvent $event, string $channel): bool
    {
        return NotificationEventChannel::where('notification_event_id', $event->id)
            ->where('channel', $channel)
            ->where('enabled', true)
            ->exists();
    }

    protected function getTemplateName(NotificationEvent $event, string $channel): ?string
    {
        $channelData = NotificationEventChannel::where('notification_event_id', $event->id)
            ->where('channel', $channel)
            ->with('template')
            ->first();
        
        return $channelData?->template?->name;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('notifications.events') ?? false;
    }
}
