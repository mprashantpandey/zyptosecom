<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Core\Services\AuditService;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure type is customer
        $data['type'] = 'customer';
        return $data;
    }

    protected function afterCreate(): void
    {
        // Audit log
        AuditService::log('customer.created', $this->record, [], [
            'id' => $this->record->id,
            'name' => $this->record->name,
            'email' => $this->record->email,
            'is_active' => $this->record->is_active,
        ], ['module' => 'customers']);
    }
}
