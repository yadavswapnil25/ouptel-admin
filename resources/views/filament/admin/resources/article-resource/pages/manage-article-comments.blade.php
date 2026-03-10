<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Article Information</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Title</p>
                    <p class="font-medium">{{ $this->record->title }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Comments</p>
                    <p class="font-medium">{{ $this->record->comments_count }}</p>
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>

