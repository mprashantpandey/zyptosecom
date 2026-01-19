<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 space-y-4">
            <x-filament::section>
                <x-slot name="heading">
                    Storage Tools
                </x-slot>
                <x-slot name="description">
                    Manage storage links and check writable paths
                </x-slot>

                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div>
                            <p class="font-medium">Storage Link</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Status: <span class="font-semibold">{{ $this->storageLinkActive ? 'Active ✅' : 'Inactive ❌' }}</span>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <x-filament::button
                                wire:click="createStorageLink"
                                color="success"
                                size="sm"
                            >
                                Create Link
                            </x-filament::button>
                            <x-filament::button
                                wire:click="removeStorageLink"
                                wire:confirm="Are you sure you want to remove the storage link? This may break file access."
                                color="danger"
                                size="sm"
                            >
                                Remove Link
                            </x-filament::button>
                        </div>
                    </div>

                    <div>
                        <p class="font-medium mb-2">Writable Paths Check</p>
                        <div class="space-y-2">
                            @php
                                $paths = [
                                    ['path' => 'storage/', 'full' => storage_path(), 'hint' => 'Required for file uploads'],
                                    ['path' => 'bootstrap/cache/', 'full' => base_path('bootstrap/cache'), 'hint' => 'Required for caching'],
                                    ['path' => 'public/', 'full' => public_path(), 'hint' => 'Required for public assets'],
                                ];
                            @endphp
                            @foreach($paths as $pathInfo)
                                @php
                                    $writable = is_writable($pathInfo['full']);
                                @endphp
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded">
                                    <div>
                                        <p class="font-mono text-sm">{{ $pathInfo['path'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $pathInfo['hint'] }}</p>
                                    </div>
                                    <div>
                                        @if($writable)
                                            <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                Writable
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-red-600 dark:text-red-400">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                                </svg>
                                                Not Writable
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </x-filament::section>
        </div>

        <x-filament-panels::form.actions
            :actions="$this->getFormActions()"
        />
    </form>
</x-filament-panels::page>

