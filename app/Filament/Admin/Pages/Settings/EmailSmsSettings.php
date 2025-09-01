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

class EmailSmsSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Email & SMS Setup';
    protected static ?string $title = 'Email & SMS Setup';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.settings.email-sms';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'smtp_or_mail' => Setting::get('smtp_or_mail', 'mail'),
            'siteEmail' => Setting::get('siteEmail', 'contact@ouptel.com'),
            'smtp_host' => Setting::get('smtp_host', ''),
            'smtp_username' => Setting::get('smtp_username', ''),
            'smtp_password' => Setting::get('smtp_password', ''),
            'smtp_port' => Setting::get('smtp_port', '587'),
            'smtp_encryption' => Setting::get('smtp_encryption', 'tls'),
            'sms_provider' => Setting::get('sms_provider', 'twilio'),
            'sms_phone_number' => Setting::get('sms_phone_number', ''),
            'sms_username' => Setting::get('sms_username', ''),
            'sms_password' => Setting::get('sms_password', ''),
            'sms_twilio_username' => Setting::get('sms_twilio_username', ''),
            'sms_twilio_password' => Setting::get('sms_twilio_password', ''),
            'sms_t_phone_number' => Setting::get('sms_t_phone_number', ''),
            'infobip_api_key' => Setting::get('infobip_api_key', ''),
            'infobip_base_url' => Setting::get('infobip_base_url', ''),
            'msg91_authKey' => Setting::get('msg91_authKey', ''),
            'msg91_dlt_id' => Setting::get('msg91_dlt_id', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('E-mail Configuration')
                    ->schema([
                        Select::make('smtp_or_mail')
                            ->label('E-mail Server')
                            ->options([
                                'smtp' => 'SMTP Server',
                                'mail' => 'Server Mail (Default)',
                            ])
                            ->helperText('Select which E-mail server you want to use, Server Mail function is not recommended.'),
                        TextInput::make('siteEmail')
                            ->label('Website Default E-mail')
                            ->email()
                            ->helperText('This is your default website E-mail, this will be used to send E-mails to users.'),
                        TextInput::make('smtp_host')
                            ->label('SMTP Host')
                            ->helperText('Your SMTP account host name, can be IP, domain or subdomain.'),
                        TextInput::make('smtp_username')
                            ->label('SMTP Username')
                            ->helperText('Your SMTP account username.'),
                        TextInput::make('smtp_password')
                            ->label('SMTP Password')
                            ->password()
                            ->helperText('Your SMTP account password.'),
                        TextInput::make('smtp_port')
                            ->label('SMTP Port')
                            ->numeric()
                            ->helperText('Which port does your SMTP server use? most used 587 for TLS, and 465 for SSL encryption.'),
                        Select::make('smtp_encryption')
                            ->label('SMTP Encryption')
                            ->options([
                                'tls' => 'TLS (Default, Not secured)',
                                'ssl' => 'SSL (Secure)',
                            ])
                            ->helperText('Which encryption method does your SMTP server use?'),
                    ])
                    ->columns(2),

                Section::make('SMS Settings')
                    ->schema([
                        Select::make('sms_provider')
                            ->label('Default SMS Provider')
                            ->options([
                                'twilio' => 'Twilio',
                                'bulksms' => 'BulkSMS',
                                'infobip' => 'Infobip',
                                'msg91' => 'Msg91',
                            ])
                            ->helperText('Select which SMS provider you want to use, you can use only one at the same time.'),
                        TextInput::make('sms_phone_number')
                            ->label('Your Phone Number')
                            ->helperText('Set your website default number, this will be used to send SMS to users, e.g (+9053..)'),
                    ])
                    ->columns(2),

                Section::make('BulkSMS Configuration')
                    ->schema([
                        TextInput::make('sms_username')
                            ->label('BulkSMS Username'),
                        TextInput::make('sms_password')
                            ->label('BulkSMS Password')
                            ->password(),
                    ])
                    ->columns(2),

                Section::make('Twilio Configuration')
                    ->schema([
                        TextInput::make('sms_twilio_username')
                            ->label('Twilio account_sid'),
                        TextInput::make('sms_twilio_password')
                            ->label('Twilio auth_token'),
                        TextInput::make('sms_t_phone_number')
                            ->label('Twilio Phone number'),
                    ])
                    ->columns(2),

                Section::make('Infobip Configuration')
                    ->schema([
                        TextInput::make('infobip_api_key')
                            ->label('Infobip API Key'),
                        TextInput::make('infobip_base_url')
                            ->label('Infobip Base URL'),
                    ])
                    ->columns(2),

                Section::make('Msg91 Configuration')
                    ->schema([
                        TextInput::make('msg91_authKey')
                            ->label('Msg91 AuthKey'),
                        TextInput::make('msg91_dlt_id')
                            ->label('Msg91 DLT ID'),
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
                ->title('Email & SMS settings saved successfully!')
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
