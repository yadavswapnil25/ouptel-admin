<div class="space-y-6">
    {{-- ID Proof Information --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
            {{ !empty($isPageVerification) ? 'Page Verification Documents' : 'ID Proof Information' }}
        </h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-500 dark:text-gray-400">Document Type:</span>
                <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ $idProofType }}</span>
            </div>
            <div>
                <span class="text-gray-500 dark:text-gray-400">Document Number:</span>
                <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ $idProofNumber ?: '—' }}</span>
            </div>
        </div>
    </div>

    {{-- Images Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Front Image --}}
        <div class="space-y-2">
            <h4 class="text-md font-medium text-gray-900 dark:text-white">Front Side</h4>
            @if(!empty($frontUrl))
                <a href="{{ $frontUrl }}" target="_blank" rel="noopener" class="block">
                    <img
                        src="{{ $frontUrl }}"
                        alt="ID Proof Front"
                        class="w-full h-auto max-h-80 object-contain rounded-lg border border-gray-200 dark:border-gray-700 shadow-md hover:shadow-lg transition-shadow cursor-pointer"
                    />
                </a>
                <p class="text-xs text-gray-500 dark:text-gray-400">Click image to open in new tab</p>
            @else
                <div class="w-full h-48 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                    <span class="text-gray-400 dark:text-gray-500">No front image uploaded</span>
                </div>
            @endif
        </div>

        {{-- Back Image --}}
        <div class="space-y-2">
            <h4 class="text-md font-medium text-gray-900 dark:text-white">Back Side</h4>
            @if(!empty($backUrl))
                <a href="{{ $backUrl }}" target="_blank" rel="noopener" class="block">
                    <img
                        src="{{ $backUrl }}"
                        alt="ID Proof Back"
                        class="w-full h-auto max-h-80 object-contain rounded-lg border border-gray-200 dark:border-gray-700 shadow-md hover:shadow-lg transition-shadow cursor-pointer"
                    />
                </a>
                <p class="text-xs text-gray-500 dark:text-gray-400">Click image to open in new tab</p>
            @else
                <div class="w-full h-48 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                    <span class="text-gray-400 dark:text-gray-500">No back image uploaded</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Live Photo (page verification) --}}
    @if(!empty($isPageVerification))
        <div class="space-y-2">
            <h4 class="text-md font-medium text-gray-900 dark:text-white">Live Photo</h4>
            @if(!empty($livePhotoUrl))
                <a href="{{ $livePhotoUrl }}" target="_blank" rel="noopener" class="block max-w-xl">
                    <img
                        src="{{ $livePhotoUrl }}"
                        alt="Live verification photo"
                        class="w-full h-auto max-h-96 object-contain rounded-lg border border-gray-200 dark:border-gray-700 shadow-md hover:shadow-lg transition-shadow cursor-pointer"
                    />
                </a>
                <p class="text-xs text-gray-500 dark:text-gray-400">Click image to open in new tab</p>
            @else
                <div class="w-full h-48 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                    <span class="text-gray-400 dark:text-gray-500">No live photo uploaded</span>
                </div>
            @endif
        </div>
    @endif

    {{-- Help Text --}}
    <div class="text-sm text-gray-500 dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
        <strong>Verification Tips:</strong>
        <ul class="list-disc list-inside mt-1 space-y-1">
            @if(!empty($isPageVerification))
                <li>Confirm the live photo clearly shows the page owner&apos;s face</li>
                <li>Check that the name on the ID matches the page owner&apos;s profile</li>
                <li>Ensure ID images are clear and readable</li>
                <li>Reject if the live photo looks like a still image upload or does not match the ID</li>
            @else
                <li>Ensure the photo on the ID matches the user&apos;s profile photo</li>
                <li>Check that the document number matches the submitted number</li>
                <li>Verify the document is not expired</li>
                <li>For Golden Badge, verify the user is a VIP, Celebrity, or Well-Known Person</li>
            @endif
        </ul>
    </div>
</div>
