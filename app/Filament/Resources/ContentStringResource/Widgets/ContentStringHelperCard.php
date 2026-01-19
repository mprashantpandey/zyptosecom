<?php

namespace App\Filament\Resources\ContentStringResource\Widgets;

use Filament\Widgets\Widget;

class ContentStringHelperCard extends Widget
{
    protected static string $view = 'filament.widgets.content-string-helper-card';
    protected int | string | array $columnSpan = 'full';
}

