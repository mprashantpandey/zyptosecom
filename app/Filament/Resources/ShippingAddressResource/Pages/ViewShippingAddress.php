<?php

namespace App\Filament\Resources\ShippingAddressResource\Pages;

use App\Filament\Resources\ShippingAddressResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewShippingAddress extends ViewRecord
{
    protected static string $resource = ShippingAddressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
