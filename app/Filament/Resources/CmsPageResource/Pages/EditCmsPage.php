<?php

namespace App\Filament\Resources\CmsPageResource\Pages;

use App\Core\Services\AuditService;
use App\Filament\Resources\CmsPageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCmsPage extends EditRecord
{
    protected static string $resource = CmsPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->before(function ($record) {
                    if ($record->isSystemPage()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Cannot delete system page')
                            ->body('System pages (Terms, Privacy) cannot be deleted for legal compliance.')
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
        AuditService::log('cms_page.updated', $this->record, $this->before ?? [], $this->record->getChanges(), ['module' => 'cms']);
    }
}
