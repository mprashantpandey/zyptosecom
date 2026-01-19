<?php

namespace App\Filament\Resources\HomeSectionResource\Pages;

use App\Core\Services\AuditService;
use App\Core\Services\AppConfigService;
use App\Filament\Resources\HomeSectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHomeSection extends EditRecord
{
    protected static string $resource = HomeSectionResource::class;

    protected $beforeRecordState = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->before(function ($record) {
                    $before = $record->only(['id', 'key', 'title']);
                    AuditService::log('home_section.deleted', $record, $before, [], ['module' => 'home_builder']);
                }),
        ];
    }

    protected function beforeFill(): void
    {
        // Store before state for audit
        $this->beforeRecordState = $this->record->only([
            'title', 'key', 'type', 'platform_scope', 'is_enabled', 'starts_at', 'ends_at', 'sort_order', 'settings_json'
        ]);
    }

    protected function afterSave(): void
    {
        // Audit log
        $after = $this->record->fresh()->only([
            'title', 'key', 'type', 'platform_scope', 'is_enabled', 'starts_at', 'ends_at', 'sort_order', 'settings_json'
        ]);
        
        AuditService::log('home_section.updated', $this->record, $this->beforeRecordState ?? [], $after, ['module' => 'home_builder']);
        
        // Clear cache
        app(AppConfigService::class)->clearCache();
        \Illuminate\Support\Facades\Cache::forget('home_layout:v1:web');
        \Illuminate\Support\Facades\Cache::forget('home_layout:v1:app');
    }

    protected function afterReorder(): void
    {
        // Audit log for reordering
        AuditService::log('home_section.reordered', $this->record, [], ['sort_order' => $this->record->sort_order], ['module' => 'home_builder']);
        
        // Clear cache
        app(AppConfigService::class)->clearCache();
        \Illuminate\Support\Facades\Cache::forget('home_layout:v1:web');
        \Illuminate\Support\Facades\Cache::forget('home_layout:v1:app');
    }
}
