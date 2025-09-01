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

class SiteSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-information-circle';
    protected static ?string $navigationLabel = 'Website Information';
    protected static ?string $title = 'Website Information';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.settings.site';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'siteName' => Setting::get('siteName', 'Ouptel'),
            'siteTitle' => Setting::get('siteTitle', 'Ouptel - Connect with Friends'),
            'siteKeywords' => Setting::get('siteKeywords', 'social, network, community, ouptel'),
            'siteDesc' => Setting::get('siteDesc', 'Ouptel is a modern social networking platform.'),
            'googleAnalytics' => Setting::get('googleAnalytics', ''),
            'google_map' => Setting::get('google_map', false),
            'google_map_api' => Setting::get('google_map_api', ''),
            'yandex_map' => Setting::get('yandex_map', false),
            'yandex_map_api' => Setting::get('yandex_map_api', ''),
            'yandex_translate' => Setting::get('yandex_translate', false),
            'yandex_translation_api' => Setting::get('yandex_translation_api', ''),
            'google_translate' => Setting::get('google_translate', false),
            'google_translation_api' => Setting::get('google_translation_api', ''),
            'youtube_api_key' => Setting::get('youtube_api_key', ''),
            'giphy_api' => Setting::get('giphy_api', ''),
            'native_android_messenger_url' => Setting::get('native_android_messenger_url', ''),
            'native_android_timeline_url' => Setting::get('native_android_timeline_url', ''),
            'native_ios_messenger_url' => Setting::get('native_ios_messenger_url', ''),
            'native_ios_timeline_url' => Setting::get('native_ios_timeline_url', ''),
            'native_windows_messenger_url' => Setting::get('native_windows_messenger_url', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Website Information')
                    ->schema([
                        TextInput::make('siteName')
                            ->label('Website Name')
                            ->required()
                            ->helperText('Your website name, it will on website\'s footer and E-mails.'),
                        TextInput::make('siteTitle')
                            ->label('Website Title')
                            ->required()
                            ->helperText('Your website general title, it will appear on Google and on your browser tab.'),
                        TextInput::make('siteKeywords')
                            ->label('Website Keywords')
                            ->helperText('Your website\'s keyword, used mostly for SEO and search engines.'),
                        Textarea::make('siteDesc')
                            ->label('Website Description')
                            ->rows(3)
                            ->helperText('Your website\'s description, used mostly for SEO and search engines, Max of 100 characters is recommended'),
                        Textarea::make('googleAnalytics')
                            ->label('Google Analytics Code')
                            ->rows(3)
                            ->helperText('Paste your full Google Analytics Code here to track traffic.'),
                    ])
                    ->columns(1),

                Section::make('Features API Keys & Information')
                    ->schema([
                        Toggle::make('google_map')
                            ->label('Google Maps')
                            ->helperText('Show Google Map on (Posts, Profile, Settings, Ads).'),
                        TextInput::make('google_map_api')
                            ->label('Google Map API')
                            ->helperText('This key is required for GEO and viewing Google Maps.'),
                        Toggle::make('yandex_map')
                            ->label('Yandex Maps')
                            ->helperText('Show Yandex Map on (Posts, Profile, Settings, Ads).'),
                        TextInput::make('yandex_map_api')
                            ->label('Yandex Map API key')
                            ->helperText('This key is required for GEO and viewing Yandex Maps.'),
                        Toggle::make('yandex_translate')
                            ->label('Yandex Translation API')
                            ->helperText('Translate post text.'),
                        TextInput::make('yandex_translation_api')
                            ->label('Yandex Translation API Key')
                            ->helperText('This key is required for post translation.'),
                        Toggle::make('google_translate')
                            ->label('Google Translation API')
                            ->helperText('Translate post text.'),
                        TextInput::make('google_translation_api')
                            ->label('Google Translation API Key')
                            ->helperText('This key is required for post translation.'),
                        TextInput::make('youtube_api_key')
                            ->label('Youtube API Key')
                            ->helperText('This key is required for importing or posting YouTube videos.'),
                        TextInput::make('giphy_api')
                            ->label('Giphy API')
                            ->helperText('This key is required for GIFs in messages, posts and comments.'),
                    ])
                    ->columns(2),

                Section::make('Android & IOS Apps')
                    ->schema([
                        TextInput::make('native_android_messenger_url')
                            ->label('Native Android Messenger')
                            ->helperText('Your Native Android Messenger Link.'),
                        TextInput::make('native_android_timeline_url')
                            ->label('Native Android Timeline')
                            ->helperText('Your Native Android Timeline Link.'),
                        TextInput::make('native_ios_messenger_url')
                            ->label('Native iOS Messenger')
                            ->helperText('Your Native iOS Messenger Link.'),
                        TextInput::make('native_ios_timeline_url')
                            ->label('Native iOS Timeline')
                            ->helperText('Your Native iOS Timeline Link.'),
                        TextInput::make('native_windows_messenger_url')
                            ->label('Native Windows Messenger')
                            ->helperText('Your Native Windows Messenger Link.'),
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
                ->title('Website information saved successfully!')
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
