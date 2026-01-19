<?php

namespace App\Filament\Resources\MediaAssetResource\Pages;

use App\Core\Services\AuditService;
use App\Core\Services\AppConfigService;
use App\Filament\Resources\MediaAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;

class EditMediaAsset extends EditRecord
{
    protected static string $resource = MediaAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function ($record) {
                    $before = $record->only(['path', 'type']);
                    AuditService::log('media.deleted', $record, $before, [], ['module' => 'home_builder']);
                    // Clear home layout cache in case this media was used
                    Cache::forget('home_layout:v1:web');
                    Cache::forget('home_layout:v1:app');
                }),
        ];
    }

    protected function afterSave(): void
    {
        // Clear cache on update (in case alt_text or tags changed)
        app(AppConfigService::class)->clearCache();
    }
}
