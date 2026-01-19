<x-filament-panels::page>
    <form wire:submit="save">
        <div class="mb-6">
            {{ $this->form }}
        </div>

        <div class="mt-6">
            <x-filament-panels::form.actions
                :actions="$this->getFormActions()"
            />
        </div>
    </form>
</x-filament-panels::page>
