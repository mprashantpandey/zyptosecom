<?php

namespace App\Filament\Resources\CmsPageResource\Widgets;

use Filament\Widgets\Widget;

class CmsHelperCard extends Widget
{
    protected static string $view = 'filament.widgets.cms-helper-card';
    protected int | string | array $columnSpan = 'full';
}

