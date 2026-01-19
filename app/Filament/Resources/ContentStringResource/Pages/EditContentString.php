<?php

namespace App\Filament\Resources\ContentStringResource\Pages;

use App\Core\Services\AuditService;
use App\Filament\Resources\ContentStringResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContentString extends EditRecord
{
    protected static string $resource = ContentStringResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->before(function ($record) {
                    if ($record->is_system) {
                        \Filament\Notifications\Notification::make()
                            ->title('Cannot delete system string')
                            ->body('System strings are required for core functionality and cannot be deleted.')
                            ->danger()
                            ->send();
                        return false;
                    }
                }),
        ];
    }

    protected function beforeSave(): void
    {
        $this->before = $this->record->getOriginal();
    }

    protected function afterSave(): void
    {
        AuditService::log('content_string.updated', $this->record, $this->before ?? [], $this->record->getChanges(), ['module' => 'cms']);
    }
}
