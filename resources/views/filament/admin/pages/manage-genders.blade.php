<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Gender Statistics
            </h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                This page shows gender distribution based on user data from the database.
            </p>
            
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>


