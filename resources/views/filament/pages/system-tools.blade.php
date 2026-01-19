<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <x-filament::section>
            <x-slot name="heading">
                Cache Management
            </x-slot>
            <x-slot name="description">
                Clear specific caches or optimize the system
            </x-slot>
            
            <div class="flex gap-2 flex-wrap">
                @foreach($this->getFormActions() as $action)
                    {{ $action }}
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
