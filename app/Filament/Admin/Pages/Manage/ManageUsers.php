<?php

namespace App\Filament\Admin\Pages\Manage;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Setting;
use App\Filament\Admin\Concerns\HasPageAccess;

class ManageUsers extends Page
{
    use HasPageAccess;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'User Settings';
    protected static ?string $title = 'User Settings';
    protected static ?string $navigationGroup = 'Manage Features';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.manage.manage-users';

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = [
            'manage-users' => Setting::get('manage-users', '1'),
            'user-registration' => Setting::get('user-registration', '1'),
            'user-verification' => Setting::get('user-verification', '1'),
            'user-approval' => Setting::get('user-approval', '1'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('User Management')
                    ->description('Configure user management features.')
                    ->schema([
                        Toggle::make('manage-users')
                            ->label('Enable User Management')
                            ->helperText('Allow administrators to manage users.')
                            ->default(true),
                        
                        Toggle::make('user-registration')
                            ->label('User Registration')
                            ->helperText('Allow new users to register on the platform.')
                            ->default(true),
                        
                        Toggle::make('user-verification')
                            ->label('User Verification')
                            ->helperText('Require email verification for new users.')
                            ->default(true),
                        
                        Toggle::make('user-approval')
                            ->label('User Approval')
                            ->helperText('Require admin approval for new user registrations.')
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
                ->title('User management settings saved successfully!')
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
