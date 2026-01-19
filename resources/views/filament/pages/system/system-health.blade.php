<x-filament-panels::page>
    @php
        $status = $this->getSystemStatus();
        $extensions = $this->checkPhpExtensions();
        $limits = $this->checkServerLimits();
    @endphp

    <div class="space-y-6">
        {{-- Quick Status --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-2">Quick Status</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">System health indicators</p>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Environment</p>
                    <p class="text-lg font-semibold">{{ ucfirst($status['env']) }}</p>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Debug Mode</p>
                    <p class="text-lg font-semibold {{ $status['debug'] ? 'text-orange-600' : 'text-green-600' }}">
                        {{ $status['debug'] ? 'ON' : 'OFF' }}
                    </p>
                    @if($status['debug'] && $status['env'] === 'production')
                        <p class="text-xs text-red-600 mt-1">⚠️ Warning: Debug ON in production</p>
                    @endif
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Cache Driver</p>
                    <p class="text-lg font-semibold">{{ ucfirst($status['cache_driver']) }}</p>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Queue Driver</p>
                    <p class="text-lg font-semibold">{{ ucfirst($status['queue_driver']) }}</p>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Database</p>
                    <p class="text-lg font-semibold {{ $status['db_ok'] ? 'text-green-600' : 'text-red-600' }}">
                        {{ $status['db_ok'] ? 'OK ✅' : 'FAIL ❌' }}
                    </p>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Storage</p>
                    <p class="text-lg font-semibold {{ $status['storage_writable'] ? 'text-green-600' : 'text-red-600' }}">
                        {{ $status['storage_writable'] ? 'Writable ✅' : 'Not Writable ❌' }}
                    </p>
                </div>
                <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Last Cron Run</p>
                    <p class="text-lg font-semibold">
                        {{ $status['last_cron'] ? \Carbon\Carbon::parse($status['last_cron'])->diffForHumans() : 'Never' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Cache & Optimize Tools --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-2">Cache & Optimize Tools</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Clear various caches and optimize the application</p>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                <x-filament::button
                    wire:click="clearCache('cache')"
                    wire:confirm="Are you sure you want to clear the application cache?"
                    color="gray"
                    size="sm"
                >
                    Clear Application Cache
                </x-filament::button>
                <x-filament::button
                    wire:click="clearCache('config')"
                    wire:confirm="Are you sure you want to clear the config cache?"
                    color="gray"
                    size="sm"
                >
                    Clear Config Cache
                </x-filament::button>
                <x-filament::button
                    wire:click="clearCache('route')"
                    wire:confirm="Are you sure you want to clear the route cache?"
                    color="gray"
                    size="sm"
                >
                    Clear Route Cache
                </x-filament::button>
                <x-filament::button
                    wire:click="clearCache('view')"
                    wire:confirm="Are you sure you want to clear the view cache?"
                    color="gray"
                    size="sm"
                >
                    Clear View Cache
                </x-filament::button>
                <x-filament::button
                    wire:click="clearCache('optimize')"
                    wire:confirm="Are you sure you want to clear all optimized caches?"
                    color="primary"
                    size="sm"
                >
                    Optimize Clear (All)
                </x-filament::button>
            </div>
        </div>

        {{-- Logs Viewer --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-2">Application Logs</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">View recent application logs (last 200 lines)</p>

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <x-filament::button
                        wire:click="loadLogs"
                        color="info"
                        size="sm"
                    >
                        Load Logs
                    </x-filament::button>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            wire:model="errorsOnly"
                            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Errors Only</span>
                    </label>
                </div>

                @if($this->logContent)
                    <div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-xs overflow-x-auto max-h-96 overflow-y-auto">
                        <pre>{{ $this->logContent }}</pre>
                    </div>
                @else
                    <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg text-center text-gray-500">
                        Click "Load Logs" to view recent application logs
                    </div>
                @endif
            </div>
        </div>

        {{-- Tools Center (Super Admin Only) --}}
        @if(auth()->user()?->hasRole('super_admin'))
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-2">Tools Center</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Advanced system tools (Super Admin only)</p>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <x-filament::button
                            wire:click="rebuildPermissions"
                            wire:confirm="Are you sure you want to rebuild all permissions? This will re-run PermissionSeeder."
                            color="warning"
                            size="sm"
                        >
                            Rebuild Permissions
                        </x-filament::button>
                        <x-filament::button
                            wire:click="syncIntegrations"
                            wire:confirm="Are you sure you want to sync integrations from registry?"
                            color="info"
                            size="sm"
                        >
                            Sync Integrations List
                        </x-filament::button>
                    </div>

                    <div>
                        <p class="font-medium mb-2">PHP Extensions Check</p>
                        <div class="space-y-2">
                            @foreach($extensions as $ext => $loaded)
                                <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-900 rounded">
                                    <span class="text-sm">{{ $ext }}</span>
                                    <span class="{{ $loaded ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $loaded ? '✅ Installed' : '❌ Missing' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <p class="font-medium mb-2">Server Limits</p>
                        <div class="space-y-2">
                            @foreach($limits as $key => $value)
                                <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-900 rounded">
                                    <span class="text-sm">{{ str_replace('_', ' ', ucwords($key, '_')) }}</span>
                                    <span class="font-mono text-sm">{{ $value }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
