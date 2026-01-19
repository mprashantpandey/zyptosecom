<?php

namespace App\Filament\Resources\HomeSectionItemResource\Pages;

use App\Core\Services\AuditService;
use App\Core\Services\AppConfigService;
use App\Filament\Resources\HomeSectionItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHomeSectionItem extends EditRecord
{
    protected static string $resource = HomeSectionItemResource::class;

    protected $beforeRecordState = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function ($record) {
                    $before = $record->only(['id', 'title', 'action_type', 'action_payload', 'home_section_id']);
                    AuditService::log('home_item.deleted', $record, $before, [], ['module' => 'home_builder']);
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Extract action_payload JSON to temporary form fields
        $payload = $data['action_payload'] ?? [];
        $actionType = $data['action_type'] ?? 'none';

        if ($actionType === 'product' && isset($payload['product_id'])) {
            $data['action_product_id'] = $payload['product_id'];
        } elseif ($actionType === 'category' && isset($payload['category_id'])) {
            $data['action_category_id'] = $payload['category_id'];
        } elseif ($actionType === 'search' && isset($payload['query'])) {
            $data['action_search_query'] = $payload['query'];
        } elseif ($actionType === 'url' && isset($payload['url'])) {
            $data['action_url'] = $payload['url'];
        }

        // Handle platform_scope inherit display
        $section = \App\Models\HomeSection::find($data['home_section_id'] ?? null);
        if (($data['platform_scope'] ?? null) === ($section?->platform_scope ?? 'both')) {
            $data['platform_scope'] = 'inherit';
        }

        return $data;
    }

    protected function beforeFill(): void
    {
        // Store before state for audit
        $this->beforeRecordState = $this->record->only([
            'title', 'subtitle', 'action_type', 'action_payload', 'image_path', 'platform_scope', 'starts_at', 'ends_at', 'sort_order'
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
            $section = \App\Models\HomeSection::find($data['home_section_id'] ?? $this->record->home_section_id);
            $data['platform_scope'] = $section?->platform_scope ?? 'both';
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Audit log
        $after = $this->record->fresh()->only([
            'title', 'subtitle', 'action_type', 'action_payload', 'image_path', 'platform_scope', 'starts_at', 'ends_at', 'sort_order'
        ]);
        
        AuditService::log('home_item.updated', $this->record, $this->beforeRecordState ?? [], $after, ['module' => 'home_builder']);
        
        // Clear cache
        app(AppConfigService::class)->clearCache();
        \Illuminate\Support\Facades\Cache::forget('home_layout:v1:web');
        \Illuminate\Support\Facades\Cache::forget('home_layout:v1:app');
    }

    protected function afterReorder(): void
    {
        // Audit log for reordering
        AuditService::log('home_item.reordered', $this->record, [], ['sort_order' => $this->record->sort_order], ['module' => 'home_builder']);
        
        // Clear cache
        app(AppConfigService::class)->clearCache();
        \Illuminate\Support\Facades\Cache::forget('home_layout:v1:web');
        \Illuminate\Support\Facades\Cache::forget('home_layout:v1:app');
    }
}
