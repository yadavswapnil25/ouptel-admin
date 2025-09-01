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

class WebsiteModeSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'Website Mode';
    protected static ?string $title = 'Website Mode Settings';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.settings.website-mode';

    public ?array $data = [];

    public function mount(): void
    {
        $this->data = [
            'website_mode' => Setting::get('website_mode', 'facebook'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Website Mode Selection')
                    ->description('Choose the mode for your website. Each mode enables different features.')
                    ->schema([
                        Select::make('website_mode')
                            ->label('Website Mode')
                            ->options([
                                'facebook' => 'WoWonder Default (Facebook) Mode',
                                'linkedin' => 'LinkedIn (Jobs) Mode',
                                'instagram' => 'Instagram Mode',
                                'twitter' => 'X (Twitter) Mode',
                                'askfm' => 'Askfm Mode',
                                'patreon' => 'TikTok Mode',
                            ])
                            ->required()
                            ->helperText('Select the mode that best fits your website purpose.'),
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
                ->title('Website Mode settings saved successfully!')
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
