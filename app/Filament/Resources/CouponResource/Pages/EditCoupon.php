<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Core\Services\AuditService;
use App\Filament\Resources\CouponResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

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
        AuditService::log('coupon.updated', $this->record, $oldValues, $newValues, ['module' => 'promotions']);
    }
}
