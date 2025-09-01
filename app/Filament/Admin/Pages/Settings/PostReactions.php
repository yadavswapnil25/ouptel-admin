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

class PostReactions extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-heart';
    protected static ?string $navigationLabel = 'Post Reactions';
    protected static ?string $title = 'Post Reactions Settings';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.settings.post-reactions';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'second_post_button' => Setting::get('second_post_button', 'wonder'),
            'reaction_system' => Setting::get('reaction_system', '1'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Post Reactions Configuration')
                    ->description('Configure how users can react to posts.')
                    ->schema([
                        Select::make('second_post_button')
                            ->label('Second Post Button Type')
                            ->options([
                                'wonder' => 'Wonder',
                                'dislike' => 'Dislike',
                                'reaction' => 'Reaction System',
                                'disabled' => 'Disable Button (Just Like)',
                            ])
                            ->default('wonder')
                            ->helperText('Choose what type of reaction you want to use beside the like button.'),
                        
                        Toggle::make('reaction_system')
                            ->label('Enable Reaction System')
                            ->helperText('Allow users to use multiple reaction types on posts.')
                            ->default(true),
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
                ->title('Post Reactions settings saved successfully!')
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





