<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Users</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ \App\Models\User::count() }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Users</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ \App\Models\User::where('active', 1)->count() }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Inactive Users</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ \App\Models\User::where('active', 0)->count() }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Offline 1+ Week</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ \App\Models\User::where('lastseen', '<', time() - (7 * 24 * 60 * 60))->count() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Form -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Send E-mail To Users</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Send emails to users based on their activity and login status</p>
            </div>
            <div class="p-6">
                {{ $this->form }}
            </div>
        </div>

        <!-- User Activity Breakdown -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">User Activity Breakdown</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Overview of user activity for targeted email campaigns</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 dark:text-white">Last Week</h4>
                        <p class="text-2xl font-bold text-blue-600">{{ \App\Models\User::where('lastseen', '>=', time() - (7 * 24 * 60 * 60))->count() }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Active users</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 dark:text-white">Last Month</h4>
                        <p class="text-2xl font-bold text-green-600">{{ \App\Models\User::where('lastseen', '>=', time() - (30 * 24 * 60 * 60))->count() }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Active users</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 dark:text-white">Last 3 Months</h4>
                        <p class="text-2xl font-bold text-yellow-600">{{ \App\Models\User::where('lastseen', '>=', time() - (90 * 24 * 60 * 60))->count() }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Active users</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 dark:text-white">Last 6 Months</h4>
                        <p class="text-2xl font-bold text-orange-600">{{ \App\Models\User::where('lastseen', '>=', time() - (180 * 24 * 60 * 60))->count() }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Active users</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 dark:text-white">Last 9 Months</h4>
                        <p class="text-2xl font-bold text-red-600">{{ \App\Models\User::where('lastseen', '>=', time() - (270 * 24 * 60 * 60))->count() }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Active users</p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 dark:text-white">Last Year</h4>
                        <p class="text-2xl font-bold text-purple-600">{{ \App\Models\User::where('lastseen', '>=', time() - (365 * 24 * 60 * 60))->count() }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Active users</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>

