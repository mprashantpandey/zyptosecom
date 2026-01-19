<x-filament-panels::page>
    @php
        $cronStatus = $this->getCronStatus();
    @endphp

    <div class="space-y-6">
        {{-- Cron Command Section --}}
        <x-filament::section>
            <x-slot name="heading">Cron Command</x-slot>
            <x-slot name="description">Copy this command to your server's cron configuration</x-slot>

            <div class="space-y-4">
                <div>
                    <p class="text-sm font-medium mb-2">Main Scheduler (Recommended)</p>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 px-3 py-2 bg-gray-100 dark:bg-gray-800 rounded text-sm font-mono break-all">
                            {{ $this->getCronCommand() }}
                        </code>
                        <button
                            type="button"
                            onclick="navigator.clipboard.writeText('{{ $this->getCronCommand() }}')"
                            class="px-3 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 text-sm"
                        >
                            Copy
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">This runs every minute and executes scheduled tasks</p>
                </div>

                <div>
                    <p class="text-sm font-medium mb-2">Queue Worker (Optional)</p>
                    <p class="text-xs text-gray-500 mb-2">Run this as a separate process or supervisor service</p>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 px-3 py-2 bg-gray-100 dark:bg-gray-800 rounded text-sm font-mono break-all">
                            cd {{ $this->getProjectPath() }} && {{ $this->getQueueCommand() }}
                        </code>
                        <button
                            type="button"
                            onclick="navigator.clipboard.writeText('cd {{ $this->getProjectPath() }} && {{ $this->getQueueCommand() }}')"
                            class="px-3 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 text-sm"
                        >
                            Copy
                        </button>
                    </div>
                </div>

                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <p class="text-sm font-semibold text-blue-900 dark:text-blue-200 mb-2">How to set in cPanel:</p>
                    <ol class="list-decimal list-inside space-y-1 text-sm text-blue-800 dark:text-blue-300">
                        <li>Log in to cPanel</li>
                        <li>Go to "Cron Jobs" or "Advanced" → "Cron Jobs"</li>
                        <li>Select "Standard (cPanel)" cron style</li>
                        <li>Set frequency: Every Minute (* * * * *)</li>
                        <li>Paste the command above</li>
                        <li>Click "Add New Cron Job"</li>
                    </ol>
                </div>
            </div>
        </x-filament::section>

        {{-- Cron Health --}}
        <x-filament::section>
            <x-slot name="heading">Cron Health</x-slot>
            <x-slot name="description">Monitor if your cron scheduler is running correctly</x-slot>

            <div class="space-y-4">
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <p class="font-medium">Cron Status</p>
                        <span class="px-3 py-1 rounded text-sm font-semibold {{ 
                            $cronStatus['status'] === 'working' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                            ($cronStatus['status'] === 'stale' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200')
                        }}">
                            {{ $cronStatus['message'] }}
                        </span>
                    </div>
                    @if($cronStatus['last_ran'])
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Last ran: {{ \Carbon\Carbon::parse($cronStatus['last_ran'])->format('M d, Y H:i:s') }}
                            ({{ $cronStatus['minutes_ago'] }} minutes ago)
                        </p>
                    @else
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            No heartbeat recorded. Make sure cron is configured and running.
                        </p>
                    @endif
                </div>

                <div class="flex gap-3">
                    <x-filament::button
                        wire:click="runSchedulerNow"
                        wire:confirm="Are you sure you want to run the scheduler now? This will execute all due scheduled tasks."
                        color="primary"
                        size="sm"
                    >
                        Run Scheduler Now
                    </x-filament::button>
                    <x-filament::button
                        wire:click="runHeartbeatNow"
                        color="gray"
                        size="sm"
                    >
                        Run Heartbeat Now
                    </x-filament::button>
                </div>

                <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        <strong>Note:</strong> The heartbeat updates every minute if cron is working. If you see "Not running", 
                        verify that the cron command is properly configured on your server.
                    </p>
                </div>
            </div>
        </x-filament::section>

        {{-- Queue Health (if applicable) --}}
        @if(config('queue.default') !== 'sync')
            <x-filament::section>
                <x-slot name="heading">Queue Health</x-slot>
                <x-slot name="description">Queue system status</x-slot>

                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <p class="text-sm">
                        Queue Driver: <span class="font-semibold">{{ ucfirst(config('queue.default')) }}</span>
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        Make sure queue worker is running: <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">{{ $this->getQueueCommand() }}</code>
                    </p>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>

