<?php

namespace App\Filament\Admin\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use App\Filament\Admin\Concerns\HasPageAccess;

class FundingSettings extends Page
{
    use HasPageAccess;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Funding Settings';

    protected static ?string $title = 'Funding Settings';

    protected static ?string $navigationGroup = 'Settings';

    protected static string $view = 'filament.admin.pages.funding-settings';

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Funding Settings')
                    ->description('Allow users to create funding requests and earn donations.')
                    ->schema([
                        Forms\Components\Toggle::make('funding_enabled')
                            ->label('Enable Funding')
                            ->default(false)
                            ->columnSpanFull(),
                        
                        Forms\Components\TextInput::make('commission_percentage')
                            ->label('Commission (%)')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(12)
                            ->helperText('How much do you want to earn commissions from donations, Leave it 0 if you don\'t want to get any commissions.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function mount(): void
    {
        $this->data = [
            'funding_enabled' => $this->getFundingSetting('funding_enabled', false),
            'commission_percentage' => $this->getFundingSetting('commission_percentage', 12),
        ];
    }

    protected function getActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action('save')
                ->color('primary'),
        ];
    }

    public function save(): void
    {
        try {
            // Save funding enabled setting
            $this->saveFundingSetting('funding_enabled', $this->data['funding_enabled'] ? 1 : 0);
            
            // Save commission percentage setting
            $this->saveFundingSetting('commission_percentage', $this->data['commission_percentage']);

            Notification::make()
                ->title('Settings saved successfully!')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error saving settings')
                ->body('An error occurred while saving the settings.')
                ->danger()
                ->send();
        }
    }

    private function getFundingSetting(string $key, $default = null)
    {
        try {
            $setting = DB::table('Wo_Settings')
                ->where('name', $key)
                ->first();
            
            if ($setting) {
                return $setting->value;
            }
            
            return $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    private function saveFundingSetting(string $key, $value): void
    {
        try {
            $exists = DB::table('Wo_Settings')
                ->where('name', $key)
                ->exists();

            if ($exists) {
                DB::table('Wo_Settings')
                    ->where('name', $key)
                    ->update(['value' => $value]);
            } else {
                DB::table('Wo_Settings')
                    ->insert([
                        'name' => $key,
                        'value' => $value,
                    ]);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
