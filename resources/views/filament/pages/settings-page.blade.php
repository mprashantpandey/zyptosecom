<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                ⚠️ WARNING: Advanced Users Only
            </x-slot>
            <x-slot name="description">
                This page allows direct editing of system settings using internal keys. Incorrect values may break the system. Use the Settings menu items for normal configuration.
            </x-slot>
        </x-filament::section>

        <form wire:submit="save">
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getFormActions()"
            />
        </form>
    </div>
</x-filament-panels::page>
