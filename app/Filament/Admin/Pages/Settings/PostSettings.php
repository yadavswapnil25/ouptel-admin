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

class PostSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Post Settings';
    protected static ?string $title = 'Post Settings';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.settings.post';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'post_approval' => Setting::get('post_approval', false),
            'post_editing' => Setting::get('post_editing', true),
            'post_deletion' => Setting::get('post_deletion', true),
            'max_post_length' => Setting::get('max_post_length', '640'),
            'post_hashtags' => Setting::get('post_hashtags', true),
            'post_mentions' => Setting::get('post_mentions', true),
            'post_emojis' => Setting::get('post_emojis', true),
            'post_links' => Setting::get('post_links', true),
            'post_images' => Setting::get('post_images', true),
            'post_videos' => Setting::get('post_videos', true),
            'post_audio' => Setting::get('post_audio', true),
            'post_files' => Setting::get('post_files', true),
            'post_polls' => Setting::get('post_polls', true),
            'post_events' => Setting::get('post_events', true),
            'post_funding' => Setting::get('post_funding', true),
            'post_jobs' => Setting::get('post_jobs', true),
            'post_products' => Setting::get('post_products', true),
            'post_offers' => Setting::get('post_offers', true),
            'post_blogs' => Setting::get('post_blogs', true),
            'post_stories' => Setting::get('post_stories', true),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Post Management')
                    ->description('Configure post creation, editing, and management settings.')
                    ->schema([
                        Toggle::make('post_approval')
                            ->label('Post Approval Required')
                            ->helperText('Require admin approval before posts are published.'),
                        Toggle::make('post_editing')
                            ->label('Allow Post Editing')
                            ->helperText('Allow users to edit their posts after publishing.'),
                        Toggle::make('post_deletion')
                            ->label('Allow Post Deletion')
                            ->helperText('Allow users to delete their own posts.'),
                        TextInput::make('max_post_length')
                            ->label('Maximum Post Length')
                            ->numeric()
                            ->helperText('Maximum number of characters allowed in a post.'),
                    ])
                    ->columns(2),

                Section::make('Post Features')
                    ->description('Enable or disable various post features and content types.')
                    ->schema([
                        Toggle::make('post_hashtags')
                            ->label('Hashtags')
                            ->helperText('Allow users to use hashtags in their posts.'),
                        Toggle::make('post_mentions')
                            ->label('User Mentions')
                            ->helperText('Allow users to mention other users in their posts.'),
                        Toggle::make('post_emojis')
                            ->label('Emojis')
                            ->helperText('Allow users to use emojis in their posts.'),
                        Toggle::make('post_links')
                            ->label('Links')
                            ->helperText('Allow users to share links in their posts.'),
                        Toggle::make('post_images')
                            ->label('Images')
                            ->helperText('Allow users to upload images in their posts.'),
                        Toggle::make('post_videos')
                            ->label('Videos')
                            ->helperText('Allow users to upload videos in their posts.'),
                        Toggle::make('post_audio')
                            ->label('Audio')
                            ->helperText('Allow users to upload audio files in their posts.'),
                        Toggle::make('post_files')
                            ->label('Files')
                            ->helperText('Allow users to upload files in their posts.'),
                        Toggle::make('post_polls')
                            ->label('Polls')
                            ->helperText('Allow users to create polls in their posts.'),
                        Toggle::make('post_events')
                            ->label('Events')
                            ->helperText('Allow users to create events in their posts.'),
                        Toggle::make('post_funding')
                            ->label('Funding')
                            ->helperText('Allow users to create funding campaigns in their posts.'),
                        Toggle::make('post_jobs')
                            ->label('Jobs')
                            ->helperText('Allow users to post job listings.'),
                        Toggle::make('post_products')
                            ->label('Products')
                            ->helperText('Allow users to post products for sale.'),
                        Toggle::make('post_offers')
                            ->label('Offers')
                            ->helperText('Allow users to post special offers.'),
                        Toggle::make('post_blogs')
                            ->label('Blogs')
                            ->helperText('Allow users to create blog posts.'),
                        Toggle::make('post_stories')
                            ->label('Stories')
                            ->helperText('Allow users to create stories (24-hour content).'),
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
                ->title('Post settings saved successfully!')
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
