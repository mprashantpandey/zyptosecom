<?php

namespace App\Filament\Resources\ContentStringResource\Pages;

use App\Filament\Resources\ContentStringResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewContentString extends ViewRecord
{
    protected static string $resource = ContentStringResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
