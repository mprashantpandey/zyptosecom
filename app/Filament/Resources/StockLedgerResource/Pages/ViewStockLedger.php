<?php

namespace App\Filament\Resources\StockLedgerResource\Pages;

use App\Filament\Resources\StockLedgerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStockLedger extends ViewRecord
{
    protected static string $resource = StockLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
