<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Active and Inactive Announcements -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Active Announcements -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Active Announcements</h3>
                </div>
                <div class="p-6 space-y-4">
                    @if(empty($this->activeAnnouncements))
                        <p class="text-sm text-gray-500 dark:text-gray-400">There are no active announcements.</p>
                    @else
                        @foreach($this->activeAnnouncements as $announcement)
                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 relative">
                                <div class="flex justify-end gap-2 mb-2">
                                    <button 
                                        wire:click="disableAnnouncement({{ $announcement['id'] }})"
                                        wire:confirm="Are you sure you want to disable this announcement?"
                                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                        title="Disable">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                    <button 
                                        wire:click="deleteAnnouncement({{ $announcement['id'] }})"
                                        wire:confirm="Are you sure you want to delete this announcement? This action cannot be undone."
                                        class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200"
                                        title="Delete">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                                <div class="prose dark:prose-invert max-w-none">
                                    {!! $announcement['text'] !!}
                                </div>
                                <div class="mt-3 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ $this->getTimeElapsedString($announcement['time']) }}</span>
                                    <span>Views: {{ $announcement['views'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <!-- Inactive Announcements -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Inactive Announcements</h3>
                </div>
                <div class="p-6 space-y-4">
                    @if(empty($this->inactiveAnnouncements))
                        <p class="text-sm text-gray-500 dark:text-gray-400">There are no inactive announcements.</p>
                    @else
                        @foreach($this->inactiveAnnouncements as $announcement)
                            <div class="bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-4 relative">
                                <div class="flex justify-end gap-2 mb-2">
                                    <button 
                                        wire:click="activateAnnouncement({{ $announcement['id'] }})"
                                        wire:confirm="Are you sure you want to activate this announcement?"
                                        class="text-green-500 hover:text-green-700 dark:text-green-400 dark:hover:text-green-200"
                                        title="Activate">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </button>
                                    <button 
                                        wire:click="deleteAnnouncement({{ $announcement['id'] }})"
                                        wire:confirm="Are you sure you want to delete this announcement? This action cannot be undone."
                                        class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200"
                                        title="Delete">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                                <div class="prose dark:prose-invert max-w-none">
                                    {!! $announcement['text'] !!}
                                </div>
                                <div class="mt-3 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ $this->getTimeElapsedString($announcement['time']) }}</span>
                                    <span>Views: {{ $announcement['views'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <!-- Create Announcement Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Manage Announcements</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create new announcements that will be displayed to users</p>
            </div>
            <div class="p-6">
                {{ $this->form }}
            </div>
        </div>
    </div>
</x-filament-panels::page>

