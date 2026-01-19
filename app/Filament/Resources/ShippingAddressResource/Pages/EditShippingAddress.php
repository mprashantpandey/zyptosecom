<?php

namespace App\Filament\Resources\ShippingAddressResource\Pages;

use App\Filament\Resources\ShippingAddressResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShippingAddress extends EditRecord
{
    protected static string $resource = ShippingAddressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
