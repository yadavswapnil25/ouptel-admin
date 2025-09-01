<?php

namespace App\Filament\Admin\Pages\Manage;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Setting;

class ManageApps extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationLabel = 'Manage Apps';
    protected static ?string $title = 'Manage Apps';
    protected static ?string $navigationGroup = 'Manage Features';
    protected static bool $shouldRegisterNavigation = true;
    protected static string $view = 'filament.pages.manage.manage-apps';

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = [
            'manage-apps' => Setting::get('manage-apps', '1'),
            'app-creation' => Setting::get('app-creation', '1'),
            'app-approval' => Setting::get('app-approval', '1'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Application Management')
                    ->description('Configure application management features.')
                    ->schema([
                        Toggle::make('manage-apps')
                            ->label('Enable App Management')
                            ->helperText('Allow administrators to manage applications.')
                            ->default(true),
                        
                        Toggle::make('app-creation')
                            ->label('App Creation')
                            ->helperText('Allow users to create new applications.')
                            ->default(true),
                        
                        Toggle::make('app-approval')
                            ->label('App Approval')
                            ->helperText('Require admin approval for new applications.')
                            ->default(false),
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
                ->title('Application management settings saved successfully!')
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
