<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <x-filament::section>
            <x-slot name="heading">
                Orders
            </x-slot>
            <x-slot name="description">
                Order history for this customer
            </x-slot>
            {{ $this->ordersTable }}
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Shipping Addresses
            </x-slot>
            <x-slot name="description">
                Saved addresses for this customer
            </x-slot>
            {{ $this->addressesTable }}
        </x-filament::section>

        @if($this->customer?->wallet)
            <x-filament::section>
                <x-slot name="heading">
                    Wallet Transactions
                </x-slot>
                <x-slot name="description">
                    Transaction history
                </x-slot>
                {{ $this->walletTransactionsTable }}
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
