<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                <i class="fas fa-cog mr-2"></i>System Settings
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                Configure your Ouptel platform settings. These settings will be applied across your entire application.
            </p>
            
            {{ $this->form }}
            
            <div class="mt-6 flex justify-end">
                {{ $this->saveAction }}
            </div>
        </div>
    </div>
</x-filament-panels::page>