<?php

namespace App\Filament\Resources\ProviderResource\Pages;

use App\Filament\Resources\ProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProviders extends ListRecords
{
    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync')
                ->label('Update Integrations List')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Update Integrations List')
                ->modalDescription('This will sync integrations from the registry. New integrations will be added, but existing ones will not be modified.')
                ->action(function () {
                    \Artisan::call('providers:sync');
                    \Filament\Notifications\Notification::make()
                        ->title('Integrations list updated')
                        ->body('All integrations have been synced from the registry.')
                        ->success()
                        ->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
            Actions\Action::make('integrations_tester')
                ->label('Test Integrations')
                ->icon('heroicon-o-beaker')
                ->color('gray')
                ->url(\App\Filament\Pages\IntegrationsTester::getUrl()),
        ];
    }
}
