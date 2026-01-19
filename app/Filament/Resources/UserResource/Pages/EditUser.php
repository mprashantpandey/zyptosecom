<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Core\Services\AuditService;
use App\Filament\Pages\CustomerDetail;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected $beforeRecordState = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->url(fn () => CustomerDetail::getUrl(['record' => $this->record->id])),
            Actions\DeleteAction::make()
                ->before(function ($record) {
                    $before = $record->only(['id', 'name', 'email', 'is_active']);
                    AuditService::log('customer.deleted', $record, $before, [], ['module' => 'customers']);
                }),
        ];
    }

    protected function beforeFill(): void
    {
        // Store before state for audit
        $this->beforeRecordState = $this->record->only([
            'name', 'email', 'phone', 'is_active', 'metadata'
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle internal_notes if present
        if (isset($data['internal_notes'])) {
            $metadata = $this->record->metadata ?? [];
            $metadata['internal_notes'] = $data['internal_notes'];
            $this->record->metadata = $metadata;
            unset($data['internal_notes']);
        }
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Save metadata if internal_notes was updated
        if (isset($this->data['internal_notes'])) {
            $this->record->saveQuietly();
        }
        
        // Audit log
        $after = $this->record->fresh()->only([
            'name', 'email', 'phone', 'is_active', 'metadata'
        ]);
        
        AuditService::log('customer.updated', $this->record, $this->beforeRecordState ?? [], $after, ['module' => 'customers']);
    }
}
