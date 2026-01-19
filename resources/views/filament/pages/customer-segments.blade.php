<x-filament-panels::page>
    <form wire:submit="save">
        <div class="space-y-6">
            <x-filament::section>
                <x-slot name="heading">
                    Create New Segment
                </x-slot>
                <x-slot name="description">
                    Define rules to segment your customers
                </x-slot>
                {{ $this->form }}

                <x-filament::form.actions
                    :actions="$this->getFormActions()"
                />
            </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Saved Segments
            </x-slot>
            <x-slot name="description">
                Manage your customer segments
            </x-slot>
            {{ $this->table }}
        </x-filament::section>
        </div>
    </form>
</x-filament-panels::page>
