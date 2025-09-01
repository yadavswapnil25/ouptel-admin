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

class GeneralSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'General Configuration';
    protected static ?string $title = 'General Configuration';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.settings.general';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'developer_mode' => Setting::get('developer_mode', false),
            'cacheSystem' => Setting::get('cacheSystem', false),
            'maintenance_mode' => Setting::get('maintenance_mode', false),
            'useSeoFrindly' => Setting::get('useSeoFrindly', true),
            'developers_page' => Setting::get('developers_page', false),
            'profile_privacy' => Setting::get('profile_privacy', true),
            'defualtLang' => Setting::get('defualtLang', 'english'),
            'date_style' => Setting::get('date_style', 'Y-m-d'),
            'directory_landing_page' => Setting::get('directory_landing_page', 'welcome'),
            'online_sidebar' => Setting::get('online_sidebar', true),
            'user_lastseen' => Setting::get('user_lastseen', true),
            'deleteAccount' => Setting::get('deleteAccount', true),
            'profile_back' => Setting::get('profile_back', true),
            'connectivitySystem' => Setting::get('connectivitySystem', false),
            'connectivitySystemLimit' => Setting::get('connectivitySystemLimit', '5000'),
            'invite_links_system' => Setting::get('invite_links_system', true),
            'user_links_limit' => Setting::get('user_links_limit', '10'),
            'expire_user_links' => Setting::get('expire_user_links', 'day'),
            'censored_words' => Setting::get('censored_words', ''),
            'cache_sidebar' => Setting::get('cache_sidebar', '1'),
            'update_user_profile' => Setting::get('update_user_profile', '30'),
            'exchangerate_key' => Setting::get('exchangerate_key', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('General Configuration')
                    ->schema([
                        Toggle::make('developer_mode')
                            ->label('Developer Mode')
                            ->helperText('By enabling developer mode, error reporting will be enabled. Not recommended without developer help.'),
                        Toggle::make('cacheSystem')
                            ->label('Cache System')
                            ->helperText('By enabling Cache System, speed up your website up to 80%!'),
                        Toggle::make('maintenance_mode')
                            ->label('Maintenance Mode')
                            ->helperText('Turn the whole site under Maintenance. You can get the site back by visiting /admincp'),
                        Toggle::make('useSeoFrindly')
                            ->label('SEO Friendly URL')
                            ->helperText('Enable smooth loading to save bandwidth.'),
                        Toggle::make('developers_page')
                            ->label('Developers (API System)')
                            ->helperText('Show /developers page to all users for API requests.'),
                        Toggle::make('profile_privacy')
                            ->label('Welcome Page Users')
                            ->helperText('Allow non logged users to view user profiles on welcome page.'),
                    ])
                    ->columns(2),

                Section::make('Localization')
                    ->schema([
                        Select::make('defualtLang')
                            ->label('Default Language')
                            ->options([
                                'english' => 'English',
                                'spanish' => 'Spanish',
                                'french' => 'French',
                                'german' => 'German',
                                'italian' => 'Italian',
                                'portuguese' => 'Portuguese',
                                'russian' => 'Russian',
                                'japanese' => 'Japanese',
                                'korean' => 'Korean',
                                'chinese' => 'Chinese',
                            ]),
                        Select::make('date_style')
                            ->label('Date Format')
                            ->options([
                                'm-d-y' => 'mm-dd-yy',
                                'd-m-y' => 'dd-mm-yy',
                                'y-m-d' => 'yy-mm-dd',
                                'M-d-y' => 'mmm-dd-yy',
                                'd-F-y' => 'dd-mmmm-yy',
                                'Y-m-d' => 'yyyy-mm-dd',
                                'd-M-Y' => 'dd-mmm-yyyy',
                                'd-F-Y' => 'dd-mmmm-yyyy',
                            ]),
                        Select::make('directory_landing_page')
                            ->label('Landing Page')
                            ->options([
                                'welcome' => 'Login Page',
                                'register' => 'Register Page',
                                'home' => 'NewsFeed Page',
                                'directory' => 'Directory Page',
                            ])
                            ->helperText('If people are not logged in they will be redirected to this page.'),
                    ])
                    ->columns(1),

                Section::make('User Configuration')
                    ->schema([
                        Toggle::make('online_sidebar')
                            ->label('Online Users')
                            ->helperText('Show current active users in home page.'),
                        Toggle::make('user_lastseen')
                            ->label('User Last Seen Status')
                            ->helperText('Allow users to set their status, online & last active.'),
                        Toggle::make('deleteAccount')
                            ->label('User Account Deletion')
                            ->helperText('Allow users to delete their accounts.'),
                        Toggle::make('profile_back')
                            ->label('Profile Background Change')
                            ->helperText('Allow users to change their profile backgrounds by uploading an image.'),
                        Toggle::make('connectivitySystem')
                            ->label('Friends System')
                            ->helperText('Choose between Follow & Friend system.'),
                        TextInput::make('connectivitySystemLimit')
                            ->label('Connectivity System Limit')
                            ->numeric()
                            ->helperText('How many friends can have each user?'),
                    ])
                    ->columns(2),

                Section::make('User Invite System')
                    ->schema([
                        Toggle::make('invite_links_system')
                            ->label('User Invite System')
                            ->helperText('Allow users to invite other users to your site.'),
                        TextInput::make('user_links_limit')
                            ->label('How many links can a user generate?')
                            ->numeric(),
                        Select::make('expire_user_links')
                            ->label('User can generate X links within?')
                            ->options([
                                'hour' => '1 Hour',
                                'day' => '1 Day',
                                'week' => '1 Week',
                                'month' => '1 Month',
                                'year' => '1 Year',
                            ]),
                    ])
                    ->columns(1),

                Section::make('Other Settings')
                    ->schema([
                        Textarea::make('censored_words')
                            ->label('Censored Words')
                            ->rows(3)
                            ->helperText('Words to be censored and replaced with *** in messages, posts, comments etc, separated by a comma.'),
                        Select::make('cache_sidebar')
                            ->label('Home Page Caching')
                            ->options([
                                '1' => 'Update home page sidebar data every 2 minutes (faster load)',
                                '0' => 'Never cache, always fetch new data',
                            ])
                            ->helperText('Enable this feature to save MySQL usage and increase the speed of home page.'),
                        Select::make('update_user_profile')
                            ->label('Profile Page Caching')
                            ->options([
                                '30' => 'Every 30 seconds',
                                '120' => 'Every 2 minutes',
                                '3600' => 'Every 1 hour',
                                '7200' => 'Every 2 hours',
                                '43200' => 'Every 12 hours',
                                '86400' => 'Every 24 hours',
                            ])
                            ->helperText('Update sidebar data every X, this is related to cache system to save MySQL usage.'),
                        TextInput::make('exchangerate_key')
                            ->label('Exchangerate API Key')
                            ->helperText('Your Exchangerate API Key from exchangerate-api.com'),
                    ])
                    ->columns(1),
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
                ->title('General settings saved successfully!')
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
