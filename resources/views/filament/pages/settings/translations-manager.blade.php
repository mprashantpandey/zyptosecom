<x-filament-panels::page>
    <div class="space-y-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Select Language</label>
                    <select 
                        wire:model.live="selectedLocale"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    >
                        @foreach(\App\Models\Language::where('is_active', true)->get() as $lang)
                            <option value="{{ $lang->code }}">{{ $lang->name }} ({{ $lang->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Filter by Group</label>
                    <select 
                        wire:model.live="selectedGroup"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option value="">All Groups</option>
                        <option value="app">App</option>
                        <option value="auth">Auth</option>
                        <option value="checkout">Checkout</option>
                        <option value="products">Products</option>
                        <option value="orders">Orders</option>
                    </select>
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>

