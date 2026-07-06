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

        {{-- Edit preset --}}
        @if($editingColorId)
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Edit Color Preset</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Update this preset's colors or background image.
                        </p>
                    </div>
                    <x-filament::button wire:click="cancelEditColor" color="gray" size="sm">
                        Cancel
                    </x-filament::button>
                </div>

                <div class="mt-4">
                    {{ $this->editForm }}
                </div>

                @php
                    $editPreviewImage = $editData['image'] ?? null;
                    if (is_array($editPreviewImage)) {
                        $editPreviewImage = $editPreviewImage[0] ?? null;
                    }
                    $editPreviewImageUrl = $editPreviewImage
                        ? \Illuminate\Support\Facades\Storage::disk('public')->url($editPreviewImage)
                        : null;
                @endphp

                <div
                    class="mt-4 flex min-h-[220px] items-center justify-center rounded-xl bg-cover bg-center p-6 text-center"
                    wire:key="edit-preview-{{ $editingColorId }}-{{ md5(json_encode($editData ?? [])) }}"
                    style="@if($editingIsImage && $editPreviewImageUrl)background-image: url('{{ $editPreviewImageUrl }}');@elseif(!$editingIsImage && !empty($editData['color_1']) && !empty($editData['color_2']))background: linear-gradient(135deg, {{ $editData['color_1'] }} 0%, {{ $editData['color_2'] }} 100%);@else background: #e5e7eb; @endif"
                >
                    <h2
                        class="text-2xl font-semibold drop-shadow"
                        style="color: {{ $editData['text_color'] ?? '#111827' }};"
                    >
                        Hello World !!
                    </h2>
                </div>

                <div class="mt-4 flex justify-end gap-3">
                    <x-filament::button wire:click="cancelEditColor" color="gray">
                        Cancel
                    </x-filament::button>
                    <x-filament::button wire:click="saveEditedColor" color="primary">
                        Save Changes
                    </x-filament::button>
                </div>
            </div>
        @endif

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
                        <div class="overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-gray-700">
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

                            <div class="flex items-center justify-end gap-2 border-t border-gray-100 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                                <x-filament::button
                                    wire:click="editColor({{ $color['id'] }})"
                                    color="gray"
                                    size="sm"
                                >
                                    Edit
                                </x-filament::button>
                                <x-filament::button
                                    wire:click="deleteColor({{ $color['id'] }})"
                                    wire:confirm="Delete this color preset? This cannot be undone."
                                    color="danger"
                                    size="sm"
                                >
                                    Delete
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
