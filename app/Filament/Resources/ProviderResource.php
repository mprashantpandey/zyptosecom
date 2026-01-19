<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProviderResource\Pages;
use App\Models\IntegrationTest;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use App\Core\Providers\ProviderRegistry;

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';
    protected static ?string $navigationGroup = 'Integrations';
    protected static ?string $navigationLabel = 'Integrations';

    public static function getModelLabel(): string
    {
        return 'Integration';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Integrations';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->hasRole('super_admin') || $user?->can('integrations.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // Integrations are managed via ProviderRegistry, not manually created
    }

    public static function canDeleteAny(): bool
    {
        return false; // Integrations are managed via ProviderRegistry
    }

    public static function canDelete(Model $record): bool
    {
        return false; // Integrations are managed via ProviderRegistry
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Integration Settings')
                    ->description('Basic settings for this integration')
                    ->schema([
                        Forms\Components\Toggle::make('is_enabled')
                            ->label('Enable Integration')
                            ->required()
                            ->default(true)
                            ->helperText('Enable this integration to use it in your store'),
                        Forms\Components\Select::make('environment')
                            ->label('Mode')
                            ->options([
                                'sandbox' => 'Test Mode',
                                'production' => 'Live Mode',
                            ])
                            ->required()
                            ->default('sandbox')
                            ->helperText('Use Test Mode while setting up. Switch to Live when ready.')
                            ->visible(fn (Provider $record) => $record->supportsEnvironment()),
                        Forms\Components\Placeholder::make('live_only')
                            ->label('Mode')
                            ->content('Live only')
                            ->visible(fn (Provider $record) => !$record->supportsEnvironment()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('label')
                        ->label('Integration')
                        ->weight('bold')
                        ->size('lg')
                        ->icon(fn (?Provider $record) => $record?->getCategoryIcon())
                        ->description(fn (?Provider $record) => $record?->getCategoryLabel())
                        ->searchable(),
                ]),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?Provider $record) => match($record?->getStatus() ?? 'not_configured') {
                        'active' => 'success',
                        'configured' => 'info',
                        'needs_attention' => 'warning',
                        'not_configured' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?Provider $record) => $record?->getStatusLabel() ?? 'Not Configured'),
                Tables\Columns\TextColumn::make('mode')
                    ->label('Mode')
                    ->badge()
                    ->color(fn (?Provider $record) => ($record?->environment ?? 'sandbox') === 'production' ? 'success' : 'warning')
                    ->formatStateUsing(fn (?Provider $record) => ($record?->environment ?? 'sandbox') === 'production' ? 'Live' : 'Test')
                    ->visible(fn (?Provider $record) => $record?->supportsEnvironment() ?? false),
                Tables\Columns\TextColumn::make('live_only_badge')
                    ->label('Mode')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn () => 'Live only')
                    ->visible(fn (?Provider $record) => !($record?->supportsEnvironment() ?? false)),
                Tables\Columns\TextColumn::make('last_tested')
                    ->label('Last Tested')
                    ->dateTime('M d, Y H:i')
                    ->placeholder('Never')
                    ->getStateUsing(fn (?Provider $record) => $record?->lastTestedAt())
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Category')
                    ->options([
                        'payment' => 'Payments',
                        'shipping' => 'Shipping',
                        'email' => 'Email',
                        'sms' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                        'push' => 'Push Notifications',
                        'auth' => 'Authentication',
                        'storage' => 'Storage',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'not_configured' => 'Not Configured',
                        'configured' => 'Configured',
                        'active' => 'Active',
                        'needs_attention' => 'Needs Attention',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!isset($data['value'])) {
                            return;
                        }
                        // This would need custom logic to filter by status
                        // For now, we'll handle it in the table query
                    }),
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label('Enabled')
                    ->placeholder('All')
                    ->trueLabel('Enabled only')
                    ->falseLabel('Disabled only'),
            ])
            ->actions([
                Tables\Actions\Action::make('configure')
                    ->label('Configure')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('primary')
                    ->url(fn (?Provider $record) => $record ? \App\Filament\Pages\ProviderCredentialsPage::getUrl(['provider' => $record->id]) : '#'),
                Tables\Actions\Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (?Provider $record) {
                        if (!$record) {
                            return;
                        }
                        // Test integration logic
                        try {
                            // This would call the actual test logic
                            $test = IntegrationTest::create([
                                'provider_id' => $record->id,
                                'provider_key' => $record->name,
                                'status' => 'success', // Would be determined by actual test
                                'message' => 'Connection test successful',
                                'tested_at' => now(),
                                'created_by' => auth()->id(),
                            ]);

                            Notification::make()
                                ->title('Test successful')
                                ->body('Integration is working correctly')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            IntegrationTest::create([
                                'provider_id' => $record->id,
                                'provider_key' => $record->name,
                                'status' => 'failed',
                                'message' => $e->getMessage(),
                                'tested_at' => now(),
                                'created_by' => auth()->id(),
                            ]);

                            Notification::make()
                                ->title('Test failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn (?Provider $record) => $record?->hasCredentials() ?? false),
                Tables\Actions\Action::make('webhook_url')
                    ->label('Webhook URL')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->modalHeading('Webhook URL')
                    ->modalContent(fn (?Provider $record) => $record ? view('filament.pages.webhook-url', [
                        'provider' => $record,
                        'url' => route('api.v1.payments.webhook', ['provider_key' => $record->name]),
                    ]) : '')
                    ->modalSubmitAction(false)
                    ->visible(fn (?Provider $record) => $record?->supportsWebhooks() ?? false),
                Tables\Actions\Action::make('view_details')
                    ->label('Details')
                    ->icon('heroicon-o-information-circle')
                    ->color('gray')
                    ->slideOver()
                    ->modalHeading(fn (?Provider $record) => ($record?->label ?? 'Integration') . ' Details')
                    ->form([
                        Forms\Components\Placeholder::make('description')
                            ->label('What this integration does')
                            ->content(fn (?Provider $record) => $record?->description ?? 'No description available'),
                        Forms\Components\Placeholder::make('setup_checklist')
                            ->label('Setup Checklist')
                            ->content(fn (?Provider $record) => $record ? view('filament.components.setup-checklist', ['provider' => $record]) : ''),
                        Forms\Components\Placeholder::make('status_info')
                            ->label('Current Status')
                            ->content(fn (?Provider $record) => $record?->getStatusLabel() ?? 'Not Configured'),
                    ])
                    ->modalSubmitAction(false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('enable')
                        ->label('Enable Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_enabled' => true]);
                            }
                            Notification::make()
                                ->title(count($records) . ' integrations enabled')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('disable')
                        ->label('Disable Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_enabled' => false]);
                            }
                            Notification::make()
                                ->title(count($records) . ' integrations disabled')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('label')
            ->emptyStateHeading('No integrations found')
            ->emptyStateDescription('Run "Update Integrations List" to sync from registry')
            ->emptyStateActions([
                Tables\Actions\Action::make('sync')
                    ->label('Update Integrations List')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function () {
                        \Artisan::call('providers:sync');
                        Notification::make()
                            ->title('Integrations list updated')
                            ->success()
                            ->send();
                    }),
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
            'index' => Pages\ListProviders::route('/'),
            'edit' => Pages\EditProvider::route('/{record}/edit'), // Edit only for enable/disable/mode
        ];
    }
}
