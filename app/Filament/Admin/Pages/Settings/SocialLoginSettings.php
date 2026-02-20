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
use App\Filament\Admin\Concerns\HasPageAccess;

class SocialLoginSettings extends Page
{
    use HasPageAccess;

    protected static ?string $navigationIcon = 'heroicon-o-share';
    protected static ?string $navigationLabel = 'Social Login';
    protected static ?string $title = 'Social Login Settings';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.settings.social-login';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'AllLogin' => Setting::get('AllLogin', false),
            'googleLogin' => Setting::get('googleLogin', false),
            'facebookLogin' => Setting::get('facebookLogin', false),
            'twitterLogin' => Setting::get('twitterLogin', false),
            'linkedinLogin' => Setting::get('linkedinLogin', false),
            'VkontakteLogin' => Setting::get('VkontakteLogin', false),
            'instagramLogin' => Setting::get('instagramLogin', false),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Social Login Configuration')
                    ->description('Configure social login providers for your website. Users will be able to register and login using their social media accounts.')
                    ->schema([
                        Toggle::make('AllLogin')
                            ->label('Enable All Social Login')
                            ->helperText('Enable or disable all social login providers at once.'),
                        Toggle::make('googleLogin')
                            ->label('Google Login')
                            ->helperText('Allow users to register and login using their Google account.'),
                        Toggle::make('facebookLogin')
                            ->label('Facebook Login')
                            ->helperText('Allow users to register and login using their Facebook account.'),
                        Toggle::make('twitterLogin')
                            ->label('Twitter Login')
                            ->helperText('Allow users to register and login using their Twitter account.'),
                        Toggle::make('linkedinLogin')
                            ->label('LinkedIn Login')
                            ->helperText('Allow users to register and login using their LinkedIn account.'),
                        Toggle::make('VkontakteLogin')
                            ->label('VKontakte Login')
                            ->helperText('Allow users to register and login using their VKontakte account.'),
                        Toggle::make('instagramLogin')
                            ->label('Instagram Login')
                            ->helperText('Allow users to register and login using their Instagram account.'),
                    ])
                    ->columns(2),

                Section::make('API Configuration')
                    ->description('Configure API keys and credentials for social login providers.')
                    ->schema([
                        TextInput::make('google_client_id')
                            ->label('Google Client ID')
                            ->helperText('Your Google OAuth Client ID.'),
                        TextInput::make('google_client_secret')
                            ->label('Google Client Secret')
                            ->password()
                            ->helperText('Your Google OAuth Client Secret.'),
                        TextInput::make('facebook_app_id')
                            ->label('Facebook App ID')
                            ->helperText('Your Facebook App ID.'),
                        TextInput::make('facebook_app_secret')
                            ->label('Facebook App Secret')
                            ->password()
                            ->helperText('Your Facebook App Secret.'),
                        TextInput::make('twitter_api_key')
                            ->label('Twitter API Key')
                            ->helperText('Your Twitter API Key.'),
                        TextInput::make('twitter_api_secret')
                            ->label('Twitter API Secret')
                            ->password()
                            ->helperText('Your Twitter API Secret.'),
                        TextInput::make('linkedin_client_id')
                            ->label('LinkedIn Client ID')
                            ->helperText('Your LinkedIn OAuth Client ID.'),
                        TextInput::make('linkedin_client_secret')
                            ->label('LinkedIn Client Secret')
                            ->password()
                            ->helperText('Your LinkedIn OAuth Client Secret.'),
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
                ->title('Social login settings saved successfully!')
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
