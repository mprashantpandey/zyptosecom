<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            System Health
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Queue Size</p>
                        <p class="text-2xl font-bold">{{ $queueSize }}</p>
                    </div>
                    <div class="text-{{ $queueSize > 100 ? 'red' : 'green' }}-500">
                        <x-heroicon-o-queue-list class="w-8 h-8" />
                    </div>
                </div>
            </div>

            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Failed Jobs</p>
                        <p class="text-2xl font-bold">{{ $failedJobs }}</p>
                    </div>
                    <div class="text-{{ $failedJobs > 0 ? 'red' : 'green' }}-500">
                        <x-heroicon-o-exclamation-triangle class="w-8 h-8" />
                    </div>
                </div>
            </div>

            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Database</p>
                        <p class="text-2xl font-bold capitalize">{{ $dbStatus }}</p>
                    </div>
                    <div class="text-{{ $dbStatus === 'healthy' ? 'green' : 'red' }}-500">
                        <x-heroicon-o-server class="w-8 h-8" />
                    </div>
                </div>
            </div>

            <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Webhook Failures</p>
                        <p class="text-2xl font-bold">{{ $webhookFailures }}</p>
                        <p class="text-xs text-gray-500">Last 24h</p>
                    </div>
                    <div class="text-{{ $webhookFailures > 0 ? 'red' : 'green' }}-500">
                        <x-heroicon-o-x-circle class="w-8 h-8" />
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

