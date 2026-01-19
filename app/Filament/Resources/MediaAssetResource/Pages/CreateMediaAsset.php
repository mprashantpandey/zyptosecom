<?php

namespace App\Filament\Resources\MediaAssetResource\Pages;

use App\Core\Services\AuditService;
use App\Core\Services\AppConfigService;
use App\Filament\Resources\MediaAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class CreateMediaAsset extends CreateRecord
{
    protected static string $resource = MediaAssetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Determine type and extract metadata from uploaded file
        if (isset($data['path'])) {
            $path = $data['path'];
            $fullPath = Storage::disk('public')->path($path);
            
            // Determine type from MIME
            $mime = mime_content_type($fullPath);
            $data['type'] = str_starts_with($mime, 'image/') ? 'image' : 'video';
            $data['mime'] = $mime;
            $data['size'] = filesize($fullPath);
            
            // Extract dimensions for images
            if ($data['type'] === 'image') {
                $imageInfo = getimagesize($fullPath);
                if ($imageInfo) {
                    $data['width'] = $imageInfo[0];
                    $data['height'] = $imageInfo[1];
                }
            }
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Audit log
        AuditService::log('media.uploaded', $this->record, [], [
            'path' => $this->record->path,
            'type' => $this->record->type,
            'size' => $this->record->size,
        ], ['module' => 'home_builder']);
        
        // Clear cache
        app(AppConfigService::class)->clearCache();
    }
}
