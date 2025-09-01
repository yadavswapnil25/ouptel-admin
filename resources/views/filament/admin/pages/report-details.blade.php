<div class="space-y-4">
    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Report Information</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Report ID</p>
                <p class="text-sm text-gray-900 dark:text-white">#{{ $report->id }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Report Type</p>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                    {{ $report->report_type_display }}
                </span>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Reporter</p>
                <p class="text-sm text-gray-900 dark:text-white">{{ $report->reporter ? $report->reporter->username : 'Unknown' }}</p>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Reported At</p>
                <p class="text-sm text-gray-900 dark:text-white">{{ $report->reported_at_human }}</p>
            </div>
        </div>
    </div>

    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Report Reason</h4>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
            {{ $report->report_reason_display }}
        </span>
    </div>

    @if($report->text)
    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Report Description</h4>
        <p class="text-sm text-gray-900 dark:text-white">{{ $report->text }}</p>
    </div>
    @endif

    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Reported Content</h4>
        <div class="space-y-2">
            <p class="text-sm text-gray-900 dark:text-white">
                <strong>Content:</strong> {{ $report->reported_content }}
            </p>
            @if($report->reported_content_link !== '#')
            <p class="text-sm">
                <a href="{{ $report->reported_content_link }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                    View Content →
                </a>
            </p>
            @endif
        </div>
    </div>

    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Report Status</h4>
        <div class="flex items-center space-x-2">
            @if($report->is_seen)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    Seen
                </span>
            @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    Unseen
                </span>
            @endif
        </div>
    </div>
</div>

