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

        @if($this->data['currency_auto_convert'] ?? false)
            <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-2">Exchange Rates</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Manage exchange rates for currency conversion</p>
                
                <p class="text-sm text-gray-500">Exchange rates management will be available here. For now, configure rates manually in the database or via a future update.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
