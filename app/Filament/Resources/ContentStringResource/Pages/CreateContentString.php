<?php

namespace App\Filament\Resources\ContentStringResource\Pages;

use App\Core\Services\AuditService;
use App\Filament\Resources\ContentStringResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateContentString extends CreateRecord
{
    protected static string $resource = ContentStringResource::class;

    protected function afterCreate(): void
    {
        AuditService::log('content_string.created', $this->record, [], $this->record->toArray(), ['module' => 'cms']);
    }
}
