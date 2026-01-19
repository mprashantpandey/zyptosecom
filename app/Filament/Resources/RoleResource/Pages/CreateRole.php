<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Core\Services\AuditService;
use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function afterCreate(): void
    {
        // Audit log
        AuditService::log(
            'role.created',
            $this->record,
            [],
            ['name' => $this->record->name, 'guard_name' => $this->record->guard_name, 'permissions' => $this->record->permissions->pluck('name')->toArray()],
            ['module' => 'roles']
        );
    }
}
