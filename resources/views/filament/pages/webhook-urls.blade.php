<x-filament-panels::page>
    <div class="space-y-4">
        @foreach($this->webhookProviders as $provider)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $provider['name'] }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ ucfirst($provider['category']) }} Provider
                        </p>
                    </div>
                    <div>
                        @if($provider['status_ok'])
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                OK
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                No Recent Activity
                            </span>
                        @endif
                    </div>
                </div>

                <div class="space-y-2">
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Webhook URL:</label>
                        <div class="mt-1 flex items-center gap-2">
                            <code class="flex-1 p-2 bg-gray-100 dark:bg-gray-700 rounded text-sm break-all">
                                {{ $provider['url'] }}
                            </code>
                            <button
                                type="button"
                                onclick="navigator.clipboard.writeText('{{ $provider['url'] }}').then(() => {
                                    alert('Copied to clipboard!');
                                })"
                                class="px-3 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 text-sm"
                            >
                                Copy
                            </button>
                        </div>
                    </div>

                    @if($provider['last_received'])
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            <strong>Last received:</strong> {{ \Carbon\Carbon::parse($provider['last_received'])->diffForHumans() }}
                            <span class="ml-2">(Status: {{ $provider['last_status'] }})</span>
                        </div>
                    @else
                        <div class="text-sm text-gray-500 dark:text-gray-500">
                            No webhooks received yet
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        @if(empty($this->webhookProviders))
            <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                No webhook-enabled providers configured
            </div>
        @endif
    </div>
</x-filament-panels::page>
