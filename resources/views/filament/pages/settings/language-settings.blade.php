<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="save">
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getFormActions()"
            />
        </form>

        <div class="mt-6">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>

