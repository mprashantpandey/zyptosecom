<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Pick & Pack Orders</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Manage order fulfillment. Mark orders as packed and ready to ship.
            </p>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
