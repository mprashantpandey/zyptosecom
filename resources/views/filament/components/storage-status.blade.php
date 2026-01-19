<div class="space-y-2">
    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded">
        <span class="text-sm">Storage Link:</span>
        <span class="font-semibold">{{ $linkStatus }}</span>
    </div>
    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded">
        <span class="text-sm">storage/app:</span>
        <span class="font-semibold {{ $storageWritable ? 'text-green-600' : 'text-red-600' }}">
            {{ $storageWritable ? 'Writable ✅' : 'Not Writable ❌' }}
        </span>
    </div>
    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded">
        <span class="text-sm">public/:</span>
        <span class="font-semibold {{ $publicWritable ? 'text-green-600' : 'text-red-600' }}">
            {{ $publicWritable ? 'Writable ✅' : 'Not Writable ❌' }}
        </span>
    </div>
</div>

