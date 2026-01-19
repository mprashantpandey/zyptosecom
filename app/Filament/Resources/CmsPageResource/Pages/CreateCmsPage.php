<?php

namespace App\Filament\Resources\CmsPageResource\Pages;

use App\Core\Services\AuditService;
use App\Filament\Resources\CmsPageResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCmsPage extends CreateRecord
{
    protected static string $resource = CmsPageResource::class;

    protected function afterCreate(): void
    {
        AuditService::log('cms_page.created', $this->record, [], $this->record->toArray(), ['module' => 'cms']);
    }
}
