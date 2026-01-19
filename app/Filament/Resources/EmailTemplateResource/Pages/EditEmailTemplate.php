<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Core\Services\AuditService;
use App\Filament\Resources\EmailTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected $beforeRecordState = null;

    protected function beforeFill(): void
    {
        $this->beforeRecordState = $this->record->only(['name', 'subject', 'body', 'is_active']);
    }

    protected function afterSave(): void
    {
        $after = $this->record->fresh()->only(['name', 'subject', 'body', 'is_active']);
        AuditService::log('email.template_updated', $this->record, $this->beforeRecordState ?? [], $after, ['module' => 'email']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function () {
                    $before = $this->record->only(['id', 'name', 'subject']);
                    AuditService::log('email.template_deleted', $this->record, $before, [], ['module' => 'email']);
                }),
        ];
    }
}
