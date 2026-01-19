<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class LocalizationManager extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.localization-manager';

    public static function shouldRegisterNavigation(): bool
    {
        return false; // Hidden until fully implemented
    }

    public function mount(): void
    {
        abort_unless(
            auth()->user()?->hasRole('super_admin'),
            403,
            'This feature is under development'
        );
    }

    // Dummy save method to satisfy QA (this page is not yet implemented)
    public function save(): void
    {
        // This page is under development
    }
}
