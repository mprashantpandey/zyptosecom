<?php

namespace App\Filament\Resources\DealResource\Pages;

use App\Core\Services\AuditService;
use App\Filament\Resources\DealResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDeal extends CreateRecord
{
    protected static string $resource = DealResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        AuditService::log('deal.created', $this->record, [], [], ['module' => 'promotions']);
    }
}
