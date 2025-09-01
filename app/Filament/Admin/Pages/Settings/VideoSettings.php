<?php

namespace App\Filament\Admin\Pages\Settings;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Setting;

class VideoSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-video-camera';
    protected static ?string $navigationLabel = 'Video Settings';
    protected static ?string $title = 'Video Settings';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.settings.video';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'video_upload' => Setting::get('video_upload', true),
            'video_compression' => Setting::get('video_compression', true),
            'video_quality' => Setting::get('video_quality', '720p'),
            'max_video_size' => Setting::get('max_video_size', '100'),
            'allowed_video_formats' => Setting::get('allowed_video_formats', 'mp4,avi,mov,wmv,flv,webm'),
            'ffmpeg_path' => Setting::get('ffmpeg_path', ''),
            'video_thumbnail' => Setting::get('video_thumbnail', true),
            'video_watermark' => Setting::get('video_watermark', false),
            'watermark_text' => Setting::get('watermark_text', ''),
            'watermark_position' => Setting::get('watermark_position', 'bottom-right'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Video Upload Configuration')
                    ->description('Configure video upload settings and processing options.')
                    ->schema([
                        Toggle::make('video_upload')
                            ->label('Enable Video Upload')
                            ->helperText('Allow users to upload videos to your website.'),
                        Toggle::make('video_compression')
                            ->label('Enable Video Compression')
                            ->helperText('Automatically compress uploaded videos to reduce file size.'),
                        Select::make('video_quality')
                            ->label('Video Quality')
                            ->options([
                                '480p' => '480p (SD)',
                                '720p' => '720p (HD)',
                                '1080p' => '1080p (Full HD)',
                                '4k' => '4K (Ultra HD)',
                            ])
                            ->helperText('Select the default video quality for compression.'),
                        TextInput::make('max_video_size')
                            ->label('Maximum Video Size (MB)')
                            ->numeric()
                            ->helperText('Maximum file size for video uploads in megabytes.'),
                        TextInput::make('allowed_video_formats')
                            ->label('Allowed Video Formats')
                            ->helperText('Comma-separated list of allowed video formats (e.g., mp4,avi,mov).'),
                    ])
                    ->columns(2),

                Section::make('FFmpeg Configuration')
                    ->description('Configure FFmpeg for video processing and conversion.')
                    ->schema([
                        TextInput::make('ffmpeg_path')
                            ->label('FFmpeg Path')
                            ->helperText('Path to FFmpeg executable on your server.'),
                        Toggle::make('video_thumbnail')
                            ->label('Generate Video Thumbnails')
                            ->helperText('Automatically generate thumbnails for uploaded videos.'),
                    ])
                    ->columns(2),

                Section::make('Watermark Settings')
                    ->description('Configure video watermarking options.')
                    ->schema([
                        Toggle::make('video_watermark')
                            ->label('Enable Video Watermark')
                            ->helperText('Add watermark to uploaded videos.'),
                        TextInput::make('watermark_text')
                            ->label('Watermark Text')
                            ->helperText('Text to display as watermark on videos.'),
                        Select::make('watermark_position')
                            ->label('Watermark Position')
                            ->options([
                                'top-left' => 'Top Left',
                                'top-right' => 'Top Right',
                                'bottom-left' => 'Bottom Left',
                                'bottom-right' => 'Bottom Right',
                                'center' => 'Center',
                            ])
                            ->helperText('Position of the watermark on the video.'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();
            foreach ($data as $name => $value) {
                Setting::set($name, $value);
            }
            Notification::make()
                ->title('Video settings saved successfully!')
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
}
