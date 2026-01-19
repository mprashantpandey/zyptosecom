<?php

namespace App\Filament\Resources\ProviderResource\Pages;

use App\Filament\Resources\ProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProvider extends ViewRecord
{
    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('configure_credentials')
                ->label('Configure Credentials')
                ->icon('heroicon-o-key')
                ->color('primary')
                ->url(fn () => \App\Filament\Pages\ProviderCredentialsPage::getUrl(['provider' => $this->record->id])),
            Actions\Action::make('test')
                ->label('Test Integration')
                ->icon('heroicon-o-beaker')
                ->color('info')
                ->url(fn () => \App\Filament\Pages\IntegrationsTester::getUrl()),
            Actions\EditAction::make(),
        ];
    }
}
