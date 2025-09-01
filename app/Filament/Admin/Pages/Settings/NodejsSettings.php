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

class NodejsSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-server';
    protected static ?string $navigationLabel = 'NodeJS Settings';
    protected static ?string $title = 'NodeJS Settings';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.settings.nodejs';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'nodejs_enabled' => Setting::get('nodejs_enabled', false),
            'nodejs_host' => Setting::get('nodejs_host', 'localhost'),
            'nodejs_port' => Setting::get('nodejs_port', '3000'),
            'nodejs_ssl' => Setting::get('nodejs_ssl', false),
            'nodejs_ssl_key' => Setting::get('nodejs_ssl_key', ''),
            'nodejs_ssl_cert' => Setting::get('nodejs_ssl_cert', ''),
            'nodejs_secret_key' => Setting::get('nodejs_secret_key', ''),
            'nodejs_allow_origin' => Setting::get('nodejs_allow_origin', '*'),
            'nodejs_redis_enabled' => Setting::get('nodejs_redis_enabled', false),
            'nodejs_redis_host' => Setting::get('nodejs_redis_host', 'localhost'),
            'nodejs_redis_port' => Setting::get('nodejs_redis_port', '6379'),
            'nodejs_redis_password' => Setting::get('nodejs_redis_password', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('NodeJS Server Configuration')
                    ->description('Configure NodeJS server settings for real-time features like chat, notifications, and live updates.')
                    ->schema([
                        Toggle::make('nodejs_enabled')
                            ->label('Enable NodeJS Server')
                            ->helperText('Enable NodeJS server for real-time features.'),
                        TextInput::make('nodejs_host')
                            ->label('NodeJS Host')
                            ->required()
                            ->helperText('Host address for the NodeJS server.'),
                        TextInput::make('nodejs_port')
                            ->label('NodeJS Port')
                            ->numeric()
                            ->required()
                            ->helperText('Port number for the NodeJS server.'),
                        Toggle::make('nodejs_ssl')
                            ->label('Enable SSL')
                            ->helperText('Enable SSL encryption for NodeJS server.'),
                        TextInput::make('nodejs_ssl_key')
                            ->label('SSL Private Key Path')
                            ->helperText('Path to SSL private key file.'),
                        TextInput::make('nodejs_ssl_cert')
                            ->label('SSL Certificate Path')
                            ->helperText('Path to SSL certificate file.'),
                        TextInput::make('nodejs_secret_key')
                            ->label('NodeJS Secret Key')
                            ->password()
                            ->helperText('Secret key for NodeJS server authentication.'),
                        TextInput::make('nodejs_allow_origin')
                            ->label('Allowed Origins')
                            ->helperText('Comma-separated list of allowed origins for CORS.'),
                    ])
                    ->columns(2),

                Section::make('Redis Configuration')
                    ->description('Configure Redis for NodeJS session storage and caching.')
                    ->schema([
                        Toggle::make('nodejs_redis_enabled')
                            ->label('Enable Redis')
                            ->helperText('Enable Redis for NodeJS session storage.'),
                        TextInput::make('nodejs_redis_host')
                            ->label('Redis Host')
                            ->helperText('Host address for Redis server.'),
                        TextInput::make('nodejs_redis_port')
                            ->label('Redis Port')
                            ->numeric()
                            ->helperText('Port number for Redis server.'),
                        TextInput::make('nodejs_redis_password')
                            ->label('Redis Password')
                            ->password()
                            ->helperText('Password for Redis server authentication.'),
                    ])
                    ->columns(2),

                Section::make('Real-time Features')
                    ->description('Configure which real-time features to enable.')
                    ->schema([
                        Toggle::make('chat_system')
                            ->label('Chat System')
                            ->helperText('Enable real-time chat functionality.'),
                        Toggle::make('live_notifications')
                            ->label('Live Notifications')
                            ->helperText('Enable real-time notifications.'),
                        Toggle::make('live_typing')
                            ->label('Live Typing Indicators')
                            ->helperText('Show typing indicators in real-time.'),
                        Toggle::make('live_online_status')
                            ->label('Live Online Status')
                            ->helperText('Show online status in real-time.'),
                        Toggle::make('live_post_updates')
                            ->label('Live Post Updates')
                            ->helperText('Show new posts in real-time.'),
                        Toggle::make('live_comments')
                            ->label('Live Comments')
                            ->helperText('Show new comments in real-time.'),
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
                ->title('NodeJS settings saved successfully!')
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
