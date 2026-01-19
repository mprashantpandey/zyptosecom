<?php

namespace App\Filament\Resources\HomeSectionItemResource\Pages;

use App\Core\Services\AuditService;
use App\Core\Services\AppConfigService;
use App\Filament\Resources\HomeSectionItemResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateHomeSectionItem extends CreateRecord
{
    protected static string $resource = HomeSectionItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Convert temporary action fields to action_payload JSON
        $actionType = $data['action_type'] ?? 'none';
        $payload = null;

        if ($actionType === 'product' && !empty($data['action_product_id'])) {
            $payload = ['product_id' => (int)$data['action_product_id']];
        } elseif ($actionType === 'category' && !empty($data['action_category_id'])) {
            $payload = ['category_id' => (int)$data['action_category_id']];
        } elseif ($actionType === 'search' && !empty($data['action_search_query'])) {
            $payload = ['query' => $data['action_search_query']];
        } elseif ($actionType === 'url' && !empty($data['action_url'])) {
            $payload = ['url' => $data['action_url']];
        }

        $data['action_payload'] = $payload;

        // Remove temporary fields
        unset($data['action_product_id'], $data['action_category_id'], $data['action_search_query'], $data['action_url']);

        // Handle platform_scope inherit
        if (($data['platform_scope'] ?? null) === 'inherit') {
            $section = \App\Models\HomeSection::find($data['home_section_id'] ?? null);
            $data['platform_scope'] = $section?->platform_scope ?? 'both';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Audit log
        AuditService::log('home_item.created', $this->record, [], [
            'id' => $this->record->id,
            'title' => $this->record->title,
            'action_type' => $this->record->action_type,
            'home_section_id' => $this->record->home_section_id,
        ], ['module' => 'home_builder']);
        
        // Clear cache
        app(AppConfigService::class)->clearCache();
        \Illuminate\Support\Facades\Cache::forget('home_layout:v1:web');
        \Illuminate\Support\Facades\Cache::forget('home_layout:v1:app');
    }
}
