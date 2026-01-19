<?php

namespace App\Filament\Resources\ProviderResource\Pages;

use App\Filament\Resources\ProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProvider extends EditRecord
{
    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\Action::make('configure_credentials')
                ->label('Configure Credentials')
                ->icon('heroicon-o-key')
                ->color('primary')
                ->url(fn () => \App\Filament\Pages\ProviderCredentialsPage::getUrl(['provider' => $this->record->id])),
            // DeleteAction removed - providers are registry-managed
        ];
    }
}
