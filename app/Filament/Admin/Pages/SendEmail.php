<?php

namespace App\Filament\Admin\Pages;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Filament\Admin\Concerns\HasPageAccess;

class SendEmail extends Page
{
    use HasPageAccess;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Send E-mail';

    protected static ?string $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.admin.pages.send-email';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Send E-mail To Users')
                    ->description('Send emails to users based on their activity and login status')
                    ->schema([
                        Forms\Components\TextInput::make('subject')
                            ->label('Subject')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter email subject')
                            ->helperText('Choose the title for your message'),

                        Forms\Components\RichEditor::make('message')
                            ->label('Message (HTML Allowed)')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'blockquote',
                                'codeBlock',
                            ])
                            ->helperText('Write your message here. HTML is allowed.'),

                        Forms\Components\Select::make('send_to')
                            ->label('Send E-mail To')
                            ->options([
                                'all' => 'All users',
                                'active' => 'All Active users',
                                'inactive' => 'All Inactive users',
                                'week' => 'Users who didn\'t login for a week',
                                'month' => 'Users who didn\'t login for a month',
                                '3month' => 'Users who didn\'t login for a 3 month',
                                '6month' => 'Users who didn\'t login for a 6 month',
                                '9month' => 'Users who didn\'t login for a 9 month',
                                'year' => 'Users who didn\'t login for a year',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $count = $this->getUserCount($state);
                                $set('user_count', $count);
                            }),

                        Forms\Components\TextInput::make('user_count')
                            ->label('Approximate Users')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Approximate number of users who will receive this email'),

                        Forms\Components\Select::make('selected_users')
                            ->label('Search Users (Optional)')
                            ->options(User::pluck('username', 'user_id'))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Send only to selected users, leave empty to send to all users based on criteria above'),

                        Forms\Components\Toggle::make('test_message')
                            ->label('Test Message (Send to my email first)')
                            ->helperText('Send a test email to your email address before sending to all users'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label('Send Email')
                ->color('primary')
                ->icon('heroicon-o-paper-airplane')
                ->action('sendEmail')
                ->requiresConfirmation()
                ->modalHeading('Send Email')
                ->modalDescription('Are you sure you want to send this email? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, send it'),
        ];
    }

    public function sendEmail(): void
    {
        $data = $this->form->getState();

        try {
            // Get recipients based on criteria
            $recipients = $this->getRecipients($data['send_to'], $data['selected_users'] ?? []);

            if (empty($recipients)) {
                Notification::make()
                    ->title('No recipients found')
                    ->body('No users match the selected criteria.')
                    ->warning()
                    ->send();
                return;
            }

            // If test message is enabled, send to admin first
            if ($data['test_message'] ?? false) {
                $this->sendTestEmail($data);
            }

            // Send emails to recipients
            $sentCount = 0;
            foreach ($recipients as $user) {
                if ($this->sendEmailToUser($user, $data)) {
                    $sentCount++;
                }
            }

            Notification::make()
                ->title('Emails sent successfully')
                ->body("Successfully sent {$sentCount} emails out of " . count($recipients) . " recipients.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error sending emails')
                ->body('An error occurred while sending emails: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function getUserCount(string $criteria): int
    {
        $query = User::query();

        switch ($criteria) {
            case 'all':
                return $query->count();
            case 'active':
                return $query->where('active', 1)->count();
            case 'inactive':
                return $query->where('active', 0)->count();
            case 'week':
                $weekAgo = time() - (7 * 24 * 60 * 60);
                return $query->where('lastseen', '<', $weekAgo)->count();
            case 'month':
                $monthAgo = time() - (30 * 24 * 60 * 60);
                return $query->where('lastseen', '<', $monthAgo)->count();
            case '3month':
                $threeMonthsAgo = time() - (90 * 24 * 60 * 60);
                return $query->where('lastseen', '<', $threeMonthsAgo)->count();
            case '6month':
                $sixMonthsAgo = time() - (180 * 24 * 60 * 60);
                return $query->where('lastseen', '<', $sixMonthsAgo)->count();
            case '9month':
                $nineMonthsAgo = time() - (270 * 24 * 60 * 60);
                return $query->where('lastseen', '<', $nineMonthsAgo)->count();
            case 'year':
                $yearAgo = time() - (365 * 24 * 60 * 60);
                return $query->where('lastseen', '<', $yearAgo)->count();
            default:
                return 0;
        }
    }

    private function getRecipients(string $criteria, array $selectedUsers = []): array
    {
        // If specific users are selected, use them
        if (!empty($selectedUsers)) {
            return User::whereIn('user_id', $selectedUsers)->get()->toArray();
        }

        $query = User::query();

        switch ($criteria) {
            case 'all':
                return $query->get()->toArray();
            case 'active':
                return $query->where('active', 1)->get()->toArray();
            case 'inactive':
                return $query->where('active', 0)->get()->toArray();
            case 'week':
                $weekAgo = time() - (7 * 24 * 60 * 60);
                return $query->where('lastseen', '<', $weekAgo)->get()->toArray();
            case 'month':
                $monthAgo = time() - (30 * 24 * 60 * 60);
                return $query->where('lastseen', '<', $monthAgo)->get()->toArray();
            case '3month':
                $threeMonthsAgo = time() - (90 * 24 * 60 * 60);
                return $query->where('lastseen', '<', $threeMonthsAgo)->get()->toArray();
            case '6month':
                $sixMonthsAgo = time() - (180 * 24 * 60 * 60);
                return $query->where('lastseen', '<', $sixMonthsAgo)->get()->toArray();
            case '9month':
                $nineMonthsAgo = time() - (270 * 24 * 60 * 60);
                return $query->where('lastseen', '<', $nineMonthsAgo)->get()->toArray();
            case 'year':
                $yearAgo = time() - (365 * 24 * 60 * 60);
                return $query->where('lastseen', '<', $yearAgo)->get()->toArray();
            default:
                return [];
        }
    }

    private function sendTestEmail(array $data): void
    {
        $adminEmail = auth()->user()->email ?? config('mail.from.address');
        
        Mail::raw($data['message'], function ($message) use ($data, $adminEmail) {
            $message->to($adminEmail)
                   ->subject('[TEST] ' . $data['subject']);
        });
    }

    private function sendEmailToUser(array $user, array $data): bool
    {
        try {
            Mail::raw($data['message'], function ($message) use ($user, $data) {
                $message->to($user['email'])
                       ->subject($data['subject']);
            });
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send email to user ' . $user['user_id'] . ': ' . $e->getMessage());
            return false;
        }
    }
}
