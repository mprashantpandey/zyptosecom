<?php

namespace App\Filament\Resources\ProviderResource\Pages;

use App\Filament\Resources\ProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Http\Response;

class CreateProvider extends CreateRecord
{
    protected static string $resource = ProviderResource::class;

    public function mount(): void
    {
        // Providers are managed via ProviderRegistry, not manually created
        abort(403, 'Providers are managed via ProviderRegistry. Run `php artisan providers:sync` to sync from registry.');
    }
}
