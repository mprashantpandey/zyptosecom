<?php

namespace App\Filament\Resources\HomeSectionResource\Pages;

use App\Core\Services\AuditService;
use App\Core\Services\AppConfigService;
use App\Filament\Resources\HomeSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateHomeSection extends CreateRecord
{
    protected static string $resource = HomeSectionResource::class;

    protected function afterCreate(): void
    {
        // Audit log
        AuditService::log('home_section.created', $this->record, [], [
            'id' => $this->record->id,
            'key' => $this->record->key,
            'title' => $this->record->title,
            'type' => $this->record->type,
        ], ['module' => 'home_builder']);
        
        // Clear cache
        app(AppConfigService::class)->clearCache();
        \Illuminate\Support\Facades\Cache::forget('home_layout:v1:web');
        \Illuminate\Support\Facades\Cache::forget('home_layout:v1:app');
    }
}
