<?php

namespace App\Filament\Pages;

use App\Core\Services\AuditService;
use App\Core\Services\AppConfigService;
use App\Models\HomeSection;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PlacementManager extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-up-down';
    protected static string $view = 'filament.pages.placement-manager';
    protected static ?string $navigationGroup = 'Home Builder';
    protected static ?string $navigationLabel = 'Placement Manager';
    protected static ?int $navigationSort = 3;

    public ?array $data = [];
    public string $filter = 'all'; // all, enabled, scheduled_active

    public function mount(): void
    {
        abort_unless(auth()->user()->can('home_builder.placement'), 403);
        $this->loadSections();
    }

    protected function loadSections(): void
    {
        $query = HomeSection::orderBy('sort_order');
        
        if ($this->filter === 'enabled') {
            $query->where('is_enabled', true);
        } elseif ($this->filter === 'scheduled_active') {
            $now = now();
            $query->where('is_enabled', true)
                ->where(function ($q) use ($now) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                });
        }

        $sections = $query->get();
        
        $this->data = $sections->map(function ($section) {
            return [
                'id' => $section->id,
                'title' => $section->title,
                'key' => $section->key,
                'type' => $section->type,
                'is_enabled' => $section->is_enabled,
                'platform_scope' => $section->platform_scope,
                'sort_order' => $section->sort_order,
            ];
        })->toArray();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('filter')
                    ->label('Filter')
                    ->options([
                        'all' => 'All Sections',
                        'enabled' => 'Enabled Only',
                        'scheduled_active' => 'Scheduled Active Only',
                    ])
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadSections())
                    ->columnSpanFull(),
                Forms\Components\Repeater::make('sections')
                    ->label('Sections')
                    ->schema([
                        Forms\Components\Hidden::make('id'),
                        Forms\Components\TextInput::make('title')
                            ->disabled()
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('key')
                            ->disabled()
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('type')
                            ->disabled()
                            ->columnSpan(1),
                        Forms\Components\Toggle::make('is_enabled')
                            ->label('Enabled')
                            ->columnSpan(1),
                        Forms\Components\Select::make('platform_scope')
                            ->label('Platform')
                            ->options([
                                'web' => 'Web',
                                'app' => 'App',
                                'both' => 'Both',
                            ])
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Order')
                            ->numeric()
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(7)
                    ->itemLabel(fn (array $state): string => ($state['title'] ?? 'Section') . ' (' . ($state['key'] ?? '') . ')')
                    ->reorderable()
                    ->defaultItems(count($this->data))
                    ->default($this->data)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('home_builder.placement'), 403);

        $sections = $this->form->getState()['sections'] ?? [];
        
        if (empty($sections)) {
            Notification::make()
                ->title('No sections to save')
                ->warning()
                ->send();
            return;
        }

        // Build before/after for audit
        $before = HomeSection::whereIn('id', array_column($sections, 'id'))
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'sort_order' => $s->sort_order,
                'is_enabled' => $s->is_enabled,
                'platform_scope' => $s->platform_scope,
            ])
            ->toArray();

        DB::transaction(function () use ($sections) {
            foreach ($sections as $index => $sectionData) {
                HomeSection::where('id', $sectionData['id'])
                    ->update([
                        'sort_order' => $sectionData['sort_order'] ?? $index,
                        'is_enabled' => $sectionData['is_enabled'] ?? true,
                        'platform_scope' => $sectionData['platform_scope'] ?? 'both',
                    ]);
            }
        });

        $after = HomeSection::whereIn('id', array_column($sections, 'id'))
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'sort_order' => $s->sort_order,
                'is_enabled' => $s->is_enabled,
                'platform_scope' => $s->platform_scope,
            ])
            ->toArray();

        // Audit log
        AuditService::log('home_section.placement_updated', null, ['sections' => $before], ['sections' => $after], ['module' => 'home_builder']);

        // Clear cache
        app(AppConfigService::class)->clearCache();
        Cache::forget('home_layout:v1:web');
        Cache::forget('home_layout:v1:app');

        Notification::make()
            ->title('Placement updated successfully')
            ->success()
            ->send();

        $this->loadSections();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Save Changes')
                ->submit('save')
                ->color('primary'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('home_builder.placement') ?? false;
    }
}
