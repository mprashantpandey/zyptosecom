<?php

namespace App\Filament\Resources\RefundResource\Pages;

use App\Filament\Resources\RefundResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateRefund extends CreateRecord
{
    protected static string $resource = RefundResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['refund_number'] = 'REF-' . strtoupper(Str::random(8));
        $data['status'] = 'pending';
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
