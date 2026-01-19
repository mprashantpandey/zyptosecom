<?php

namespace App\Filament\Resources\ContentStringResource\Pages;

use App\Filament\Resources\ContentStringResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContentStrings extends ListRecords
{
    protected static string $resource = ContentStringResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getHeaderWidgets(): array
    {
        return [
            ContentStringResource\Widgets\ContentStringHelperCard::class,
        ];
    }
}
