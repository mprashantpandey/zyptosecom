<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-information-circle class="w-5 h-5 text-primary-500" />
                <span>About CMS Pages</span>
            </div>
        </x-slot>
        <x-slot name="description">
            Use CMS pages for long content like policies, about us, help pages, and custom content.
        </x-slot>
        <div class="text-sm text-gray-700 dark:text-gray-300 space-y-2">
            <p><strong>What you can do:</strong></p>
            <ul class="list-disc list-inside space-y-1 ml-2">
                <li>Create pages for Terms & Conditions, Privacy Policy, About Us, Help/FAQ, and custom content</li>
                <li>Control visibility (Web, App, Footer, Header)</li>
                <li>Optimize for search engines with SEO settings</li>
                <li>Preview how pages appear before publishing</li>
                <li>Require login for sensitive pages</li>
            </ul>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                <strong>Note:</strong> System pages (Terms, Privacy) cannot be deleted for legal compliance.
            </p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

