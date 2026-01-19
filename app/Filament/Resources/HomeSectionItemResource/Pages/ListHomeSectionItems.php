<?php

namespace App\Filament\Resources\HomeSectionItemResource\Pages;

use App\Filament\Resources\HomeSectionItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHomeSectionItems extends ListRecords
{
    protected static string $resource = HomeSectionItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
