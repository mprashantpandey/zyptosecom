<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Core\Services\AuditService;
use App\Filament\Resources\CouponResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        AuditService::log('coupon.created', $this->record, [], [], ['module' => 'promotions']);
    }
}
