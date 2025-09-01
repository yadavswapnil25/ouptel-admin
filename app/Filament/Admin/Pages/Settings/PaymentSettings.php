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

class PaymentSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Payment Settings';
    protected static ?string $title = 'Payment Settings';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.settings.payment';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'stripe_secret' => Setting::get('stripe_secret', ''),
            'stripe_id' => Setting::get('stripe_id', ''),
            'paypal_id' => Setting::get('paypal_id', ''),
            'paypal_secret' => Setting::get('paypal_secret', ''),
            'razorpay_key' => Setting::get('razorpay_key', ''),
            'razorpay_secret' => Setting::get('razorpay_secret', ''),
            'currency' => Setting::get('currency', 'USD'),
            'currency_symbol' => Setting::get('currency_symbol', '$'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Payment Gateway Configuration')
                    ->description('Configure payment gateways for your website. Users will be able to make payments using these providers.')
                    ->schema([
                        TextInput::make('stripe_secret')
                            ->label('Stripe Secret Key')
                            ->password()
                            ->helperText('Your Stripe Secret Key for processing payments.'),
                        TextInput::make('stripe_id')
                            ->label('Stripe Publishable Key')
                            ->helperText('Your Stripe Publishable Key for client-side integration.'),
                        TextInput::make('paypal_id')
                            ->label('PayPal Client ID')
                            ->helperText('Your PayPal Client ID for PayPal payments.'),
                        TextInput::make('paypal_secret')
                            ->label('PayPal Client Secret')
                            ->password()
                            ->helperText('Your PayPal Client Secret for secure payments.'),
                        TextInput::make('razorpay_key')
                            ->label('Razorpay Key ID')
                            ->helperText('Your Razorpay Key ID for Indian payments.'),
                        TextInput::make('razorpay_secret')
                            ->label('Razorpay Key Secret')
                            ->password()
                            ->helperText('Your Razorpay Key Secret for secure payments.'),
                    ])
                    ->columns(2),

                Section::make('Currency Settings')
                    ->schema([
                        Select::make('currency')
                            ->label('Default Currency')
                            ->options([
                                'USD' => 'US Dollar (USD)',
                                'EUR' => 'Euro (EUR)',
                                'GBP' => 'British Pound (GBP)',
                                'INR' => 'Indian Rupee (INR)',
                                'JPY' => 'Japanese Yen (JPY)',
                                'CAD' => 'Canadian Dollar (CAD)',
                                'AUD' => 'Australian Dollar (AUD)',
                                'CHF' => 'Swiss Franc (CHF)',
                                'CNY' => 'Chinese Yuan (CNY)',
                                'SEK' => 'Swedish Krona (SEK)',
                                'NZD' => 'New Zealand Dollar (NZD)',
                                'MXN' => 'Mexican Peso (MXN)',
                                'SGD' => 'Singapore Dollar (SGD)',
                                'HKD' => 'Hong Kong Dollar (HKD)',
                                'NOK' => 'Norwegian Krone (NOK)',
                                'TRY' => 'Turkish Lira (TRY)',
                                'RUB' => 'Russian Ruble (RUB)',
                                'ZAR' => 'South African Rand (ZAR)',
                                'BRL' => 'Brazilian Real (BRL)',
                                'KRW' => 'South Korean Won (KRW)',
                            ])
                            ->required()
                            ->helperText('Select the default currency for your website.'),
                        TextInput::make('currency_symbol')
                            ->label('Currency Symbol')
                            ->required()
                            ->helperText('The symbol that will be displayed with prices (e.g., $, €, £).'),
                    ])
                    ->columns(2),
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
                ->title('Payment settings saved successfully!')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error saving settings')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
