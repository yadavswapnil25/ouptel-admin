<?php

namespace App\Filament\Admin\Pages;

use App\Models\Language;
use App\Models\LanguageKey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Grid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use App\Filament\Admin\Concerns\HasPageAccess;

class AddNewLanguageAndKey extends Page implements HasForms
{
    use HasPageAccess;

    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationLabel = 'Add New Language & Key';

    protected static ?string $navigationGroup = 'Languages';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.admin.pages.add-new-language-and-key';

    public ?array $languageData = [];
    public ?array $keyData = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Add New Language')
                    ->description('Add a new language to the system. This may take up to 5 minutes.')
                    ->schema([
                        TextInput::make('languageData.lang_key')
                            ->label('Language Name')
                            ->required()
                            ->maxLength(200)
                            ->rules(['required', 'string', 'max:200'])
                            ->helperText('Use only english letters, no spaces allowed. E.g: russian')
                            ->placeholder('russian'),

                        TextInput::make('languageData.iso')
                            ->label('Language ISO')
                            ->required()
                            ->maxLength(10)
                            ->rules(['required', 'string', 'max:10'])
                            ->helperText('Language ISO code (e.g., en, ar, fr)')
                            ->placeholder('ru'),

                        Select::make('languageData.direction')
                            ->label('Direction')
                            ->options([
                                'ltr' => 'LTR (Left To Right)',
                                'rtl' => 'RTL (Right To Left)',
                            ])
                            ->default('ltr')
                            ->required()
                            ->rules(['required', 'string', 'in:ltr,rtl'])
                            ->helperText('The direction of the language'),
                    ])
                    ->columns(2),

                Section::make('Add New Language Key')
                    ->description('Add a new translation key to the system.')
                    ->schema([
                        TextInput::make('keyData.lang_key')
                            ->label('Key Name')
                            ->required()
                            ->maxLength(160)
                            ->rules(['required', 'string', 'max:160'])
                            ->helperText('Use only english letters, no spaces allowed, example: this_is_a_key')
                            ->placeholder('this_is_a_key'),

                        TextInput::make('keyData.type')
                            ->label('Type')
                            ->maxLength(100)
                            ->rules(['nullable', 'string', 'max:100'])
                            ->helperText('Optional type classification for this key')
                            ->placeholder('general'),

                        Textarea::make('keyData.english')
                            ->label('English Translation')
                            ->rows(3)
                            ->required()
                            ->rules(['required', 'string'])
                            ->helperText('English translation for this key')
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                Textarea::make('keyData.arabic')
                                    ->label('Arabic')
                                    ->rows(3)
                                    ->rules(['nullable', 'string']),

                                Textarea::make('keyData.french')
                                    ->label('French')
                                    ->rows(3)
                                    ->rules(['nullable', 'string']),

                                Textarea::make('keyData.german')
                                    ->label('German')
                                    ->rows(3)
                                    ->rules(['nullable', 'string']),

                                Textarea::make('keyData.italian')
                                    ->label('Italian')
                                    ->rows(3)
                                    ->rules(['nullable', 'string']),

                                Textarea::make('keyData.russian')
                                    ->label('Russian')
                                    ->rows(3)
                                    ->rules(['nullable', 'string']),

                                Textarea::make('keyData.spanish')
                                    ->label('Spanish')
                                    ->rows(3)
                                    ->rules(['nullable', 'string']),
                            ]),
                    ]),
            ])
            ->statePath('');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('addLanguage')
                ->label('Add Language')
                ->color('primary')
                ->action('addLanguage'),

            Action::make('addKey')
                ->label('Add Key')
                ->color('success')
                ->action('addKey'),

            Action::make('addBoth')
                ->label('Add Both')
                ->color('warning')
                ->action('addBoth'),
        ];
    }

    public function addLanguage(): void
    {
        $data = $this->form->getState();
        
        if (empty($data['languageData']['lang_key'])) {
            Notification::make()
                ->title('Error')
                ->body('Language name is required.')
                ->danger()
                ->send();
            return;
        }

        try {
            // Create language key in format: LanguageName_Code
            $langKey = ucfirst($data['languageData']['lang_key']) . '_' . $data['languageData']['iso'];
            
            // Check if language already exists
            $existing = Language::where('lang_key', $langKey)->first();
            if ($existing) {
                Notification::make()
                    ->title('Error')
                    ->body('Language already exists.')
                    ->danger()
                    ->send();
                return;
            }

            // Create new language
            Language::create([
                'lang_key' => $langKey,
            ]);

            Notification::make()
                ->title('Success')
                ->body('Language successfully added.')
                ->success()
                ->send();

            // Clear the language form
            $this->languageData = [];

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Failed to add language: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function addKey(): void
    {
        $data = $this->form->getState();
        
        if (empty($data['keyData']['lang_key'])) {
            Notification::make()
                ->title('Error')
                ->body('Key name is required.')
                ->danger()
                ->send();
            return;
        }

        try {
            // Check if key already exists
            $existing = LanguageKey::where('lang_key', $data['keyData']['lang_key'])->first();
            if ($existing) {
                Notification::make()
                    ->title('Error')
                    ->body('Language key already exists.')
                    ->danger()
                    ->send();
                return;
            }

            // Create new language key
            LanguageKey::create([
                'lang_key' => $data['keyData']['lang_key'],
                'type' => $data['keyData']['type'] ?? '',
                'english' => $data['keyData']['english'] ?? '',
                'arabic' => $data['keyData']['arabic'] ?? '',
                'french' => $data['keyData']['french'] ?? '',
                'german' => $data['keyData']['german'] ?? '',
                'italian' => $data['keyData']['italian'] ?? '',
                'russian' => $data['keyData']['russian'] ?? '',
                'spanish' => $data['keyData']['spanish'] ?? '',
            ]);

            Notification::make()
                ->title('Success')
                ->body('Language key successfully added.')
                ->success()
                ->send();

            // Clear the key form
            $this->keyData = [];

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Failed to add language key: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function addBoth(): void
    {
        $data = $this->form->getState();
        
        if (empty($data['languageData']['lang_key']) || empty($data['keyData']['lang_key'])) {
            Notification::make()
                ->title('Error')
                ->body('Both language name and key name are required.')
                ->danger()
                ->send();
            return;
        }

        try {
            DB::beginTransaction();

            // Add language
            $langKey = ucfirst($data['languageData']['lang_key']) . '_' . $data['languageData']['iso'];
            
            // Check if language already exists
            $existingLang = Language::where('lang_key', $langKey)->first();
            if (!$existingLang) {
                Language::create([
                    'lang_key' => $langKey,
                ]);
            }

            // Add language key
            $existingKey = LanguageKey::where('lang_key', $data['keyData']['lang_key'])->first();
            if (!$existingKey) {
                LanguageKey::create([
                    'lang_key' => $data['keyData']['lang_key'],
                    'type' => $data['keyData']['type'] ?? '',
                    'english' => $data['keyData']['english'] ?? '',
                    'arabic' => $data['keyData']['arabic'] ?? '',
                    'french' => $data['keyData']['french'] ?? '',
                    'german' => $data['keyData']['german'] ?? '',
                    'italian' => $data['keyData']['italian'] ?? '',
                    'russian' => $data['keyData']['russian'] ?? '',
                    'spanish' => $data['keyData']['spanish'] ?? '',
                ]);
            }

            DB::commit();

            Notification::make()
                ->title('Success')
                ->body('Language and key successfully added.')
                ->success()
                ->send();

            // Clear both forms
            $this->languageData = [];
            $this->keyData = [];

        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()
                ->title('Error')
                ->body('Failed to add language and key: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}

