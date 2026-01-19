<div class="space-y-4">
    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Preview:</div>
        <div class="text-base text-gray-900 dark:text-gray-100">
            {{ $text }}
        </div>
    </div>
    
    @if(!empty($variables))
        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
            <div class="text-sm font-medium text-blue-700 dark:text-blue-300 mb-2">Available Variables:</div>
            <div class="flex flex-wrap gap-2">
                @foreach($variables as $variable)
                    <span class="px-2 py-1 bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded text-xs font-mono">
                        {{ '{{' . $variable . '}}' }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</div>

