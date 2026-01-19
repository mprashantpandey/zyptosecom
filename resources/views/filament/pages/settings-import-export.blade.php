<x-filament-panels::page>
    <form wire:submit="previewImport">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getFormActions()"
        />
    </form>
</x-filament-panels::page>

