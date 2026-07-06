<?php

namespace App\Filament\Admin\Pages\Settings;

use App\Filament\Admin\Concerns\HasPageAccess;
use App\Models\ColoredPost;
use App\Models\Setting;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ManageColoredPosts extends Page
{
    use HasPageAccess;

    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationLabel = 'Manage Colored Posts';

    protected static ?string $title = 'Manage Colored Posts';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 12;

    protected static bool $shouldRegisterNavigation = true;

    protected static string $view = 'filament.pages.settings.manage-colored-posts';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->admin == '1') {
            return true;
        }

        return $user->hasAdminPermission('manage-colored-posts');
    }

    public ?array $settingsData = [];

    public ?array $gradientData = [];

    public ?array $imageData = [];

    public ?int $editingColorId = null;

    public bool $editingIsImage = false;

    public ?array $editData = [];

    /** @var array<int, array<string, mixed>> */
    public array $coloredPosts = [];

    public function mount(): void
    {
        $this->settingsForm->fill([
            'colored_posts_system' => Setting::get('colored_posts_system', '0') === '1' || Setting::get('colored_posts_system', false) === true,
            'colored_posts_request' => Setting::get('colored_posts_request', 'all'),
        ]);

        $this->gradientForm->fill([
            'color_1' => '#26ACE2',
            'color_2' => '#0B7CBD',
            'text_color' => '#FFFFFF',
        ]);

        $this->imageForm->fill([
            'text_color' => '#000000',
            'image' => null,
        ]);

        $this->loadColoredPosts();
    }

    protected function getForms(): array
    {
        return [
            'settingsForm',
            'gradientForm',
            'imageForm',
            'editForm',
        ];
    }

    public function settingsForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Colored Posts System')
                    ->description('Configure the colored posts feature for your website.')
                    ->schema([
                        Toggle::make('colored_posts_system')
                            ->label('Enable Colored Posts System')
                            ->helperText('Allow users to create colored posts with custom backgrounds.')
                            ->default(false),

                        Select::make('colored_posts_request')
                            ->label('Who can use colored posts?')
                            ->options([
                                'admin' => 'Admin Only',
                                'all' => 'All Users',
                                'verified' => 'Verified Users Only',
                                'pro' => 'Pro Users Only',
                            ])
                            ->default('all')
                            ->helperText('Select which user types can create colored posts.'),
                    ])
                    ->columns(1),
            ])
            ->statePath('settingsData');
    }

    public function gradientForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Add Colored Post')
                    ->description('Create a gradient background preset (Color 1 + Color 2).')
                    ->schema([
                        ColorPicker::make('color_1')
                            ->label('Color 1')
                            ->live()
                            ->required(),
                        ColorPicker::make('color_2')
                            ->label('Color 2')
                            ->live()
                            ->required(),
                        ColorPicker::make('text_color')
                            ->label('Text Color')
                            ->live()
                            ->required(),
                    ])
                    ->columns(3),
            ])
            ->statePath('gradientData');
    }

    public function imageForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Add Image Post')
                    ->description('Create a background image preset for colored posts.')
                    ->schema([
                        FileUpload::make('image')
                            ->label('Background Image')
                            ->image()
                            ->live()
                            ->disk('public')
                            ->directory('colored-posts')
                            ->visibility('public')
                            ->required()
                            ->maxSize(5120)
                            ->helperText('JPEG, PNG, GIF, or WebP. Max 5 MB.'),

                        ColorPicker::make('text_color')
                            ->label('Text Color')
                            ->live()
                            ->required(),
                    ])
                    ->columns(1),
            ])
            ->statePath('imageData');
    }

    public function editForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Edit Color Preset')
                    ->description('Update gradient colors or replace the background image.')
                    ->schema([
                        ColorPicker::make('color_1')
                            ->label('Color 1')
                            ->live()
                            ->visible(fn (): bool => !$this->editingIsImage)
                            ->required(fn (): bool => !$this->editingIsImage),

                        ColorPicker::make('color_2')
                            ->label('Color 2')
                            ->live()
                            ->visible(fn (): bool => !$this->editingIsImage)
                            ->required(fn (): bool => !$this->editingIsImage),

                        FileUpload::make('image')
                            ->label('Background Image')
                            ->image()
                            ->live()
                            ->disk('public')
                            ->directory('colored-posts')
                            ->visibility('public')
                            ->visible(fn (): bool => $this->editingIsImage)
                            ->maxSize(5120)
                            ->helperText('Leave unchanged to keep the current image, or upload a new one.'),

                        ColorPicker::make('text_color')
                            ->label('Text Color')
                            ->live()
                            ->required(),
                    ])
                    ->columns(1),
            ])
            ->statePath('editData');
    }

    public function saveSettings(): void
    {
        try {
            $data = $this->settingsForm->getState();
            Setting::set('colored_posts_system', !empty($data['colored_posts_system']) ? '1' : '0');
            Setting::set('colored_posts_request', $data['colored_posts_request'] ?? 'all');

            Notification::make()
                ->title('Colored posts settings saved successfully!')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error saving settings')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function editColor(int $id): void
    {
        if (!$this->ensureColoredPostsTable()) {
            return;
        }

        $color = ColoredPost::find($id);
        if (!$color) {
            Notification::make()
                ->title('Color preset not found')
                ->warning()
                ->send();
            return;
        }

        $this->editingColorId = $id;
        $this->editingIsImage = $color->isImageBackground();

        $this->editForm->fill([
            'color_1' => $color->color_1 ?: '#26ACE2',
            'color_2' => $color->color_2 ?: '#0B7CBD',
            'text_color' => $color->text_color ?: '#FFFFFF',
            'image' => $color->image ? [$color->image] : null,
        ]);
    }

    public function cancelEditColor(): void
    {
        $this->editingColorId = null;
        $this->editingIsImage = false;
        $this->editData = [];
        $this->editForm->fill([]);
    }

    public function saveEditedColor(): void
    {
        if (!$this->editingColorId || !$this->ensureColoredPostsTable()) {
            return;
        }

        try {
            $color = ColoredPost::find($this->editingColorId);
            if (!$color) {
                Notification::make()
                    ->title('Color preset not found')
                    ->warning()
                    ->send();
                $this->cancelEditColor();
                return;
            }

            $data = $this->editForm->getState();

            if ($color->isImageBackground()) {
                $newImagePath = $this->resolveUploadedImagePath($data['image'] ?? null);
                if ($newImagePath && $newImagePath !== $color->image) {
                    $color->deleteStoredImage();
                    $color->image = $newImagePath;
                }

                $color->color_1 = '';
                $color->color_2 = '';
                $color->text_color = $data['text_color'];
            } else {
                $color->color_1 = $data['color_1'];
                $color->color_2 = $data['color_2'];
                $color->text_color = $data['text_color'];
                $color->image = '';
            }

            $color->save();

            Notification::make()
                ->title('Color preset updated successfully!')
                ->success()
                ->send();

            $this->cancelEditColor();
            $this->loadColoredPosts();
        } catch (\Exception $e) {
            Log::error('Failed to update colored post: ' . $e->getMessage(), ['id' => $this->editingColorId]);

            Notification::make()
                ->title('Failed to update color preset')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function createGradientColor(): void
    {
        if (!$this->ensureColoredPostsTable()) {
            return;
        }

        try {
            $data = $this->gradientForm->getState();

            ColoredPost::create([
                'color_1' => $data['color_1'],
                'color_2' => $data['color_2'],
                'text_color' => $data['text_color'],
                'image' => '',
                'time' => time(),
            ]);

            Notification::make()
                ->title('Color preset created successfully!')
                ->success()
                ->send();

            $this->gradientForm->fill([
                'color_1' => '#26ACE2',
                'color_2' => '#0B7CBD',
                'text_color' => '#FFFFFF',
            ]);

            $this->loadColoredPosts();
        } catch (\Exception $e) {
            Log::error('Failed to create gradient colored post: ' . $e->getMessage());

            Notification::make()
                ->title('Failed to create color preset')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function createImageColor(): void
    {
        if (!$this->ensureColoredPostsTable()) {
            return;
        }

        try {
            $data = $this->imageForm->getState();
            $imagePath = $this->resolveUploadedImagePath($data['image'] ?? null);

            if (empty($imagePath)) {
                Notification::make()
                    ->title('Please upload an image')
                    ->warning()
                    ->send();
                return;
            }

            ColoredPost::create([
                'color_1' => '',
                'color_2' => '',
                'text_color' => $data['text_color'],
                'image' => $imagePath,
                'time' => time(),
            ]);

            Notification::make()
                ->title('Image preset created successfully!')
                ->success()
                ->send();

            $this->imageForm->fill([
                'text_color' => '#000000',
                'image' => null,
            ]);

            $this->loadColoredPosts();
        } catch (\Exception $e) {
            Log::error('Failed to create image colored post: ' . $e->getMessage());

            Notification::make()
                ->title('Failed to create image preset')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function deleteColor(int $id): void
    {
        if (!$this->ensureColoredPostsTable()) {
            return;
        }

        try {
            $color = ColoredPost::find($id);
            if (!$color) {
                Notification::make()
                    ->title('Color preset not found')
                    ->warning()
                    ->send();
                return;
            }

            if ($this->editingColorId === $id) {
                $this->cancelEditColor();
            }

            $color->deleteStoredImage();
            $color->delete();

            Notification::make()
                ->title('Color preset deleted')
                ->success()
                ->send();

            $this->loadColoredPosts();
        } catch (\Exception $e) {
            Log::error('Failed to delete colored post: ' . $e->getMessage(), ['id' => $id]);

            Notification::make()
                ->title('Failed to delete color preset')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function loadColoredPosts(): void
    {
        if (!Schema::hasTable('Wo_Colored_Posts')) {
            $this->coloredPosts = [];
            return;
        }

        $this->coloredPosts = ColoredPost::query()
            ->orderBy('id')
            ->get()
            ->map(function (ColoredPost $post) {
                return [
                    'id' => $post->id,
                    'color_1' => $post->color_1,
                    'color_2' => $post->color_2,
                    'text_color' => $post->text_color,
                    'image' => $post->image,
                    'image_url' => $post->image_url,
                    'is_gradient' => $post->isGradient(),
                    'is_image' => $post->isImageBackground(),
                ];
            })
            ->all();
    }

    private function resolveUploadedImagePath(mixed $imageField): ?string
    {
        if (empty($imageField)) {
            return null;
        }

        if (is_array($imageField)) {
            $first = $imageField[0] ?? null;
            return $first ? (string) $first : null;
        }

        return (string) $imageField;
    }

    private function ensureColoredPostsTable(): bool
    {
        if (Schema::hasTable('Wo_Colored_Posts')) {
            return true;
        }

        Notification::make()
            ->title('Colored posts table not found')
            ->body('Run: php artisan migrate --path=database/migrations/2026_07_03_000001_create_wo_colored_posts_table.php')
            ->warning()
            ->send();

        return false;
    }
}
