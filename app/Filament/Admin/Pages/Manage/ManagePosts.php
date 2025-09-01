<?php

namespace App\Filament\Admin\Pages\Manage;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Setting;

class ManagePosts extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Manage Posts';
    protected static ?string $title = 'Manage Posts';
    protected static ?string $navigationGroup = 'Manage Features';
    protected static bool $shouldRegisterNavigation = true;
    protected static string $view = 'filament.pages.manage.manage-posts';

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = [
            'manage-posts' => Setting::get('manage-posts', '1'),
            'post-creation' => Setting::get('post-creation', '1'),
            'post-approval' => Setting::get('post-approval', '1'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Post Management')
                    ->description('Configure post management features.')
                    ->schema([
                        Toggle::make('manage-posts')
                            ->label('Enable Post Management')
                            ->helperText('Allow administrators to manage posts.')
                            ->default(true),
                        
                        Toggle::make('post-creation')
                            ->label('Post Creation')
                            ->helperText('Allow users to create new posts.')
                            ->default(true),
                        
                        Toggle::make('post-approval')
                            ->label('Post Approval')
                            ->helperText('Require admin approval for new posts.')
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
                ->title('Post management settings saved successfully!')
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
