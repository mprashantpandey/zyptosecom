<?php

namespace App\Filament\Resources\StockLedgerResource\Pages;

use App\Filament\Resources\StockLedgerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStockLedger extends EditRecord
{
    protected static string $resource = StockLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
