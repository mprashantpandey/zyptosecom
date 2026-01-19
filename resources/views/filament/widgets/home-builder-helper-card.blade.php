<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-information-circle class="w-5 h-5 text-primary-500" />
                <span>About Home Builder</span>
            </div>
        </x-slot>
        <x-slot name="description">
            Use sections to design your home screen layout.
        </x-slot>
        <div class="text-sm text-gray-700 dark:text-gray-300 space-y-2">
            <p><strong>What you can do:</strong></p>
            <ul class="list-disc list-inside space-y-1 ml-2">
                <li>Create sections using templates (Hero Banner, Category Grid, Product Carousel, Deals Slider, Image + CTA)</li>
                <li>Add items to each section (products, categories, images, deals)</li>
                <li>Control when sections appear with scheduling</li>
                <li>Preview your home page layout before publishing</li>
                <li>Reorder sections by dragging</li>
            </ul>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                <strong>Tip:</strong> Make sure each section has at least one item, or it won't be visible to customers.
            </p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

