<?php

namespace App\Filament\Admin\Pages\Manage;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Setting;

class ManageFund extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Manage Fund';
    protected static ?string $title = 'Manage Fund';
    protected static ?string $navigationGroup = 'Manage Features';
    protected static bool $shouldRegisterNavigation = true;
    protected static string $view = 'filament.pages.manage.manage-fund';

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = [
            'manage-fund' => Setting::get('manage-fund', '1'),
            'fund-creation' => Setting::get('fund-creation', '1'),
            'fund-approval' => Setting::get('fund-approval', '1'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Fund Management')
                    ->description('Configure fundraising management features.')
                    ->schema([
                        Toggle::make('manage-fund')
                            ->label('Enable Fund Management')
                            ->helperText('Allow administrators to manage fundraising campaigns.')
                            ->default(true),
                        
                        Toggle::make('fund-creation')
                            ->label('Fund Creation')
                            ->helperText('Allow users to create fundraising campaigns.')
                            ->default(true),
                        
                        Toggle::make('fund-approval')
                            ->label('Fund Approval')
                            ->helperText('Require admin approval for new fundraising campaigns.')
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
                ->title('Fund management settings saved successfully!')
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
