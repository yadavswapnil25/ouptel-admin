<x-filament-panels::page>
    <div class="space-y-6">
        {{-- System settings --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Colored Posts System</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Enable colored posts and choose who can use them.
            </p>
            <div class="mt-4">
                <form wire:submit="saveSettings">
                    {{ $this->settingsForm }}
                    <div class="mt-4 flex justify-end">
                        <x-filament::button type="submit">
                            Save Settings
                        </x-filament::button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Create presets --}}
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            {{-- Gradient preset --}}
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add Colored Post</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Pick Color 1, Color 2, and text color for a gradient background.
                </p>

                <div class="mt-4">
                    {{ $this->gradientForm }}
                </div>

                <div
                    class="mt-4 flex min-h-[220px] items-center justify-center rounded-xl p-6 text-center"
                    wire:key="gradient-preview-{{ md5(json_encode($gradientData ?? [])) }}"
                    style="@if(!empty($gradientData['color_1']) && !empty($gradientData['color_2']))background: linear-gradient(135deg, {{ $gradientData['color_1'] }} 0%, {{ $gradientData['color_2'] }} 100%);@else background: #e5e7eb; @endif"
                >
                    <h2
                        class="text-2xl font-semibold"
                        style="color: {{ $gradientData['text_color'] ?? '#111827' }};"
                    >
                        Hello World !!
                    </h2>
                </div>

                <div class="mt-4 flex justify-end">
                    <x-filament::button wire:click="createGradientColor" color="primary">
                        Create Color
                    </x-filament::button>
                </div>
            </div>

            {{-- Image preset --}}
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add Image Post</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Upload a background image and choose the text color.
                </p>

                <div class="mt-4">
                    {{ $this->imageForm }}
                </div>

                @php
                    $previewImage = $imageData['image'] ?? null;
                    if (is_array($previewImage)) {
                        $previewImage = $previewImage[0] ?? null;
                    }
                    $previewImageUrl = $previewImage ? \Illuminate\Support\Facades\Storage::disk('public')->url($previewImage) : null;
                @endphp

                <div
                    class="mt-4 flex min-h-[220px] items-center justify-center rounded-xl bg-cover bg-center p-6 text-center"
                    style="@if($previewImageUrl)background-image: url('{{ $previewImageUrl }}');@else background: #e5e7eb; @endif"
                >
                    <h2
                        class="text-2xl font-semibold drop-shadow"
                        style="color: {{ $imageData['text_color'] ?? '#111827' }};"
                    >
                        Hello World !!
                    </h2>
                </div>

                <div class="mt-4 flex justify-end">
                    <x-filament::button wire:click="createImageColor" color="primary">
                        Create Image Preset
                    </x-filament::button>
                </div>
            </div>
        </div>

        {{-- Existing presets --}}
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Color Presets</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                These presets appear in the Create Post color picker on the website.
            </p>

            @if(empty($coloredPosts))
                <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">
                    No color presets yet. Create one using the forms above.
                </p>
            @else
                <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach($coloredPosts as $color)
                        <div class="group relative overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-gray-700">
                            <button
                                type="button"
                                wire:click="deleteColor({{ $color['id'] }})"
                                wire:confirm="Delete this color preset?"
                                class="absolute right-2 top-2 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-black/50 text-white opacity-0 transition hover:bg-black/70 group-hover:opacity-100"
                                title="Delete"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>

                            <div
                                class="flex min-h-[140px] items-center justify-center p-4 text-center"
                                @if($color['is_gradient'])
                                    style="background: linear-gradient(135deg, {{ $color['color_1'] }} 0%, {{ $color['color_2'] }} 100%);"
                                @elseif($color['is_image'] && !empty($color['image_url']))
                                    style="background-image: url('{{ $color['image_url'] }}'); background-size: cover; background-position: center;"
                                @else
                                    style="background: #e5e7eb;"
                                @endif
                            >
                                <h3
                                    class="text-lg font-semibold drop-shadow"
                                    style="color: {{ $color['text_color'] ?? '#ffffff' }};"
                                >
                                    Hello World !!
                                </h3>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
