<?php

namespace App\Filament\Resources\HomeSectionResource\Widgets;

use Filament\Widgets\Widget;

class HomeBuilderHelperCard extends Widget
{
    protected static string $view = 'filament.widgets.home-builder-helper-card';
    protected int | string | array $columnSpan = 'full';
}

