<?php

namespace App\Filament\Resources\DealResource\Pages;

use App\Core\Services\AuditService;
use App\Filament\Resources\DealResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeal extends EditRecord
{
    protected static string $resource = DealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();
        return $data;
    }

    protected function afterSave(): void
    {
        $oldValues = $this->record->getOriginal();
        $newValues = $this->record->getChanges();
        AuditService::log('deal.updated', $this->record, $oldValues, $newValues, ['module' => 'promotions']);
    }
}
