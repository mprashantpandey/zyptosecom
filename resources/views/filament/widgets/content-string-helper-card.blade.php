<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-information-circle class="w-5 h-5 text-primary-500" />
                <span>About Text & Language</span>
            </div>
        </x-slot>
        <x-slot name="description">
            Use this to change app text and support multiple languages.
        </x-slot>
        <div class="text-sm text-gray-700 dark:text-gray-300 space-y-2">
            <p><strong>What you can do:</strong></p>
            <ul class="list-disc list-inside space-y-1 ml-2">
                <li>Customize all text strings used throughout your store (buttons, labels, messages)</li>
                <li>Organize strings by groups (General, Authentication, Checkout, Orders, Cart, Errors, Notifications)</li>
                <li>Support multiple languages with locale-specific translations</li>
                <li>Use variables for dynamic content (e.g., {{product_name}}, {{price}})</li>
                <li>Preview how text appears before saving</li>
            </ul>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                <strong>Note:</strong> System strings (core functionality) cannot be deleted but can be edited.
            </p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

