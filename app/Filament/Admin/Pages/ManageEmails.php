<?php

namespace App\Filament\Admin\Pages;

use App\Models\EmailTemplate;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class ManageEmails extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Manage Emails';

    protected static ?string $title = 'Manage Emails';

    protected static ?string $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.admin.pages.manage-emails';

    public ?array $data = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action('save')
                ->color('primary')
                ->size('lg'),
        ];
    }

    public function mount(): void
    {
        $this->loadEmailTemplates();
    }

    protected function loadEmailTemplates(): void
    {
        $this->data = EmailTemplate::getAllTemplatesWithDefaults();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Edit System E-mails')
                    ->schema([
                        Section::make('Activate Account (HTML Allowed)')
                            ->schema([
                                Textarea::make('activate')
                                    ->label('')
                                    ->rows(10)
                                    ->columnSpanFull()
                                    ->extraAttributes(['class' => 'tinymce-editor'])
                                    ->extraInputAttributes(['id' => 'activate'])
                            ])
                            ->description('Make sure to add {{USERNAME}} , {{SITE_URL}} , {{EMAIL}} , {{CODE}} , {{SITE_NAME}} in the right place')
                            ->collapsible(),

                        Section::make('Invite Email (HTML Allowed)')
                            ->schema([
                                Textarea::make('invite')
                                    ->label('')
                                    ->rows(10)
                                    ->columnSpanFull()
                                    ->extraAttributes(['class' => 'tinymce-editor'])
                                    ->extraInputAttributes(['id' => 'invite'])
                            ])
                            ->description('Make sure to add {{USERNAME}} , {{SITE_URL}} , {{NAME}} , {{URL}} , {{SITE_NAME}} , {{BACKGOUND_COLOR}} in the right place')
                            ->collapsible(),

                        Section::make('Login With (HTML Allowed)')
                            ->schema([
                                Textarea::make('login_with')
                                    ->label('')
                                    ->rows(10)
                                    ->columnSpanFull()
                                    ->extraAttributes(['class' => 'tinymce-editor'])
                                    ->extraInputAttributes(['id' => 'login_with'])
                            ])
                            ->description('Make sure to add {{FIRST_NAME}} , {{SITE_NAME}} , {{USERNAME}} , {{EMAIL}} in the right place')
                            ->collapsible(),

                        Section::make('Notification (HTML Allowed)')
                            ->schema([
                                Textarea::make('notification')
                                    ->label('')
                                    ->rows(10)
                                    ->columnSpanFull()
                                    ->extraAttributes(['class' => 'tinymce-editor'])
                                    ->extraInputAttributes(['id' => 'notification'])
                            ])
                            ->description('Make sure to add {{SITE_NAME}} , {{NOTIFY_URL}} , {{NOTIFY_AVATAR}} , {{NOTIFY_NAME}} , {{TEXT_TYPE}} , {{TEXT}} , {{URL}} in the right place')
                            ->collapsible(),

                        Section::make('Payment Declined (HTML Allowed)')
                            ->schema([
                                Textarea::make('payment_declined')
                                    ->label('')
                                    ->rows(10)
                                    ->columnSpanFull()
                                    ->extraAttributes(['class' => 'tinymce-editor'])
                                    ->extraInputAttributes(['id' => 'payment_declined'])
                            ])
                            ->description('Make sure to add {{name}} , {{amount}} , {{site_name}} in the right place')
                            ->collapsible(),

                        Section::make('Payment Approved (HTML Allowed)')
                            ->schema([
                                Textarea::make('payment_approved')
                                    ->label('')
                                    ->rows(10)
                                    ->columnSpanFull()
                                    ->extraAttributes(['class' => 'tinymce-editor'])
                                    ->extraInputAttributes(['id' => 'payment_approved'])
                            ])
                            ->description('Make sure to add {{name}} , {{amount}} , {{site_name}} in the right place')
                            ->collapsible(),

                        Section::make('Recover (HTML Allowed)')
                            ->schema([
                                Textarea::make('recover')
                                    ->label('')
                                    ->rows(10)
                                    ->columnSpanFull()
                                    ->extraAttributes(['class' => 'tinymce-editor'])
                                    ->extraInputAttributes(['id' => 'recover'])
                            ])
                            ->description('Make sure to add {{USERNAME}} , {{SITE_NAME}} , {{LINK}} in the right place')
                            ->collapsible(),

                        Section::make('Unusual Login (HTML Allowed)')
                            ->schema([
                                Textarea::make('unusual_login')
                                    ->label('')
                                    ->rows(10)
                                    ->columnSpanFull()
                                    ->extraAttributes(['class' => 'tinymce-editor'])
                                    ->extraInputAttributes(['id' => 'unusual_login'])
                            ])
                            ->description('Make sure to add {{USERNAME}} , {{SITE_NAME}} , {{CODE}} , {{DATE}} , {{EMAIL}} , {{COUNTRY_CODE}} , {{IP_ADDRESS}} , {{CITY}} in the right place')
                            ->collapsible(),

                        Section::make('Account Deleted (HTML Allowed)')
                            ->schema([
                                Textarea::make('account_deleted')
                                    ->label('')
                                    ->rows(10)
                                    ->columnSpanFull()
                                    ->extraAttributes(['class' => 'tinymce-editor'])
                                    ->extraInputAttributes(['id' => 'account_deleted'])
                            ])
                            ->description('Make sure to add {{USERNAME}} , {{SITE_NAME}} in the right place')
                            ->collapsible(),
                    ])
                    ->columns(1)
                    ->statePath('data'),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        try {
            foreach ($data as $templateType => $content) {
                if (empty($content)) continue;

                EmailTemplate::updateOrCreate(
                    ['email_to' => $templateType],
                    [
                        'subject' => $this->getDefaultSubject($templateType),
                        'message' => $content,
                    ]
                );
            }

            Notification::make()
                ->title('Emails saved successfully')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error saving emails')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function getDefaultSubject(string $templateType): string
    {
        $subjects = [
            'activate' => 'Activate Your Account',
            'invite' => 'You have been invited',
            'login_with' => 'Login Notification',
            'notification' => 'New Notification',
            'payment_declined' => 'Payment Declined',
            'payment_approved' => 'Payment Approved',
            'recover' => 'Password Recovery',
            'unusual_login' => 'Unusual Login Detected',
            'account_deleted' => 'Account Deleted',
        ];

        return $subjects[$templateType] ?? 'Email Notification';
    }
}
