<?php

namespace App\Filament\Admin\Pages\Settings;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Setting;

class ManageColoredPosts extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-paint-brush';
    protected static ?string $navigationLabel = 'Manage Colored Posts';
    protected static ?string $title = 'Manage Colored Posts';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.settings.manage-colored-posts';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'colored_posts_system' => Setting::get('colored_posts_system', '0'),
            'colored_posts_request' => Setting::get('colored_posts_request', 'all'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Colored Posts System')
                    ->description('Configure the colored posts feature for your website.')
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('colored_posts_system')
                            ->label('Enable Colored Posts System')
                            ->helperText('Allow users to create colored posts with custom backgrounds.')
                            ->default(false),
                        
                        \Filament\Forms\Components\Select::make('colored_posts_request')
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
                ->title('Colored Posts settings saved successfully!')
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





