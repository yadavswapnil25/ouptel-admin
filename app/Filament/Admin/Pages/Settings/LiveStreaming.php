<?php

namespace App\Filament\Admin\Pages\Settings;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Setting;
use App\Filament\Admin\Concerns\HasPageAccess;

class LiveStreaming extends Page
{
    use HasPageAccess;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';
    protected static ?string $navigationLabel = 'Setup Live Streaming';
    protected static ?string $title = 'Live Streaming Configuration';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.settings.live-streaming';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'live_video' => Setting::get('live_video', '0'),
            'live_request' => Setting::get('live_request', 'all'),
            'live_video_save' => Setting::get('live_video_save', '0'),
            'agora_live_video' => Setting::get('agora_live_video', '0'),
            'agora_app_id' => Setting::get('agora_app_id', ''),
            'agora_app_certificate' => Setting::get('agora_app_certificate', ''),
            'agora_customer_id' => Setting::get('agora_customer_id', ''),
            'agora_customer_certificate' => Setting::get('agora_customer_certificate', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Live Streaming Settings')
                    ->description('Configure live streaming functionality for your website.')
                    ->schema([
                        Toggle::make('live_video')
                            ->label('Enable Live Streaming')
                            ->helperText('Allow users to go live instantly.')
                            ->default(false),
                        
                        Select::make('live_request')
                            ->label('Who can use live streaming?')
                            ->options([
                                'admin' => 'Admin Only',
                                'all' => 'All Users',
                                'verified' => 'Verified Users Only',
                                'pro' => 'Pro Users Only',
                            ])
                            ->default('all')
                            ->helperText('Select which user types can use live streaming.'),
                        
                        Toggle::make('live_video_save')
                            ->label('Live Streaming Storage')
                            ->helperText('Save live streams to watch again later.')
                            ->default(false),
                    ])
                    ->columns(1),
                
                Section::make('Agora API Configuration')
                    ->description('Configure Agora for live streaming. You need to create an account at Agora.io')
                    ->schema([
                        Toggle::make('agora_live_video')
                            ->label('Enable Agora Live Streaming')
                            ->helperText('Users can go live using Agora. Note: You can only choose one provider at a time.')
                            ->default(false),
                        
                        TextInput::make('agora_app_id')
                            ->label('Agora App ID')
                            ->helperText('Your Agora App ID from the dashboard.'),
                        
                        TextInput::make('agora_app_certificate')
                            ->label('Agora App Certificate')
                            ->helperText('Your Agora App Certificate.'),
                        
                        TextInput::make('agora_customer_id')
                            ->label('Agora Customer ID')
                            ->helperText('Your Agora Customer ID.'),
                        
                        TextInput::make('agora_customer_certificate')
                            ->label('Agora Customer Secret')
                            ->helperText('Your Agora Customer Secret.'),
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
                ->title('Live Streaming settings saved successfully!')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Settings table not found')
                ->body('Please run: php artisan migrate --path=database/migrations/2024_01_15_120000_create_settings_table.php')
                ->warning()
                ->send();
        }
    }
}




