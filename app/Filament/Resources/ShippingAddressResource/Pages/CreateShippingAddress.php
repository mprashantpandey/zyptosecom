<?php

namespace App\Filament\Resources\ShippingAddressResource\Pages;

use App\Filament\Resources\ShippingAddressResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateShippingAddress extends CreateRecord
{
    protected static string $resource = ShippingAddressResource::class;
}
