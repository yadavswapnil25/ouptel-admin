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

class ManagePages extends Page
{
    use HasPageAccess;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationLabel = 'Manage Pages';
    protected static ?string $title = 'Manage Pages';
    protected static ?string $navigationGroup = 'Manage Features';
    protected static bool $shouldRegisterNavigation = true;
    protected static string $view = 'filament.pages.manage.manage-pages';

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = [
            'manage-pages' => Setting::get('manage-pages', '1'),
            'page-creation' => Setting::get('page-creation', '1'),
            'page-approval' => Setting::get('page-approval', '1'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Page Management')
                    ->description('Configure page management features.')
                    ->schema([
                        Toggle::make('manage-pages')
                            ->label('Enable Page Management')
                            ->helperText('Allow administrators to manage pages.')
                            ->default(true),
                        
                        Toggle::make('page-creation')
                            ->label('Page Creation')
                            ->helperText('Allow users to create new pages.')
                            ->default(true),
                        
                        Toggle::make('page-approval')
                            ->label('Page Approval')
                            ->helperText('Require admin approval for new pages.')
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
                ->title('Page management settings saved successfully!')
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
