<?php

namespace App\Filament\Admin\Pages\Manage;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Setting;

class ManageGroups extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Manage Groups';
    protected static ?string $title = 'Manage Groups';
    protected static ?string $navigationGroup = 'Manage Features';
    protected static bool $shouldRegisterNavigation = true;
    protected static string $view = 'filament.pages.manage.manage-groups';

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = [
            'manage-groups' => Setting::get('manage-groups', '1'),
            'group-creation' => Setting::get('group-creation', '1'),
            'group-approval' => Setting::get('group-approval', '1'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Group Management')
                    ->description('Configure group management features.')
                    ->schema([
                        Toggle::make('manage-groups')
                            ->label('Enable Group Management')
                            ->helperText('Allow administrators to manage groups.')
                            ->default(true),
                        
                        Toggle::make('group-creation')
                            ->label('Group Creation')
                            ->helperText('Allow users to create new groups.')
                            ->default(true),
                        
                        Toggle::make('group-approval')
                            ->label('Group Approval')
                            ->helperText('Require admin approval for new groups.')
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
                ->title('Group management settings saved successfully!')
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
