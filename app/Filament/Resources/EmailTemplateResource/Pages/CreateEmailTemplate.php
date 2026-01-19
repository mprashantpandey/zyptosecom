<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Core\Services\AuditService;
use App\Filament\Resources\EmailTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailTemplate extends CreateRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['channel'] = 'email';
        $data['variables'] = []; // Can be extracted from body later if needed
        return $data;
    }

    protected function afterCreate(): void
    {
        AuditService::log('email.template_created', $this->record, [], [
            'id' => $this->record->id,
            'name' => $this->record->name,
        ], ['module' => 'email']);
    }
}
