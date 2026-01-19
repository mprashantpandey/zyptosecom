<div class="space-y-4">
    <div>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
            Use this URL to configure webhooks in your {{ $provider->label }} dashboard:
        </p>
        <div class="flex items-center gap-2">
            <code class="flex-1 px-3 py-2 bg-gray-100 dark:bg-gray-800 rounded text-sm font-mono break-all">
                {{ $url }}
            </code>
            <button
                type="button"
                onclick="navigator.clipboard.writeText('{{ $url }}')"
                class="px-3 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 text-sm"
            >
                Copy
            </button>
        </div>
    </div>
    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
        <p class="text-sm text-blue-800 dark:text-blue-200">
            <strong>Note:</strong> Make sure to copy this URL exactly and configure it in your {{ $provider->label }} dashboard settings.
        </p>
    </div>
</div>
