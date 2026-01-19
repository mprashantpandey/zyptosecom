<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Core\Services\AuditService;
use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function ($record) {
                    // Audit log before deletion
                    AuditService::log(
                        'role.deleted',
                        $record,
                        ['name' => $record->name, 'guard_name' => $record->guard_name, 'permissions' => $record->permissions->pluck('name')->toArray()],
                        [],
                        ['module' => 'roles']
                    );
                }),
        ];
    }

    protected $beforeRecordState = null;

    protected function beforeFill(): void
    {
        // Store before state for audit
        $this->beforeRecordState = [
            'name' => $this->record->name,
            'guard_name' => $this->record->guard_name,
            'permissions' => $this->record->permissions->pluck('name')->toArray(),
        ];
    }

    protected function afterSave(): void
    {
        // Audit log
        $after = [
            'name' => $this->record->name,
            'guard_name' => $this->record->guard_name,
            'permissions' => $this->record->fresh()->permissions->pluck('name')->toArray(),
        ];

        AuditService::log(
            'role.updated',
            $this->record,
            $this->beforeRecordState ?? [],
            $after,
            ['module' => 'roles']
        );
    }
}
