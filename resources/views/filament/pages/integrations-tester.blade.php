<x-filament-panels::page>
    <form wire:submit="runTest">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit" icon="heroicon-o-play">
                Run Test
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
