<?php

namespace App\Filament\Admin\Pages;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class MassNotifications extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationLabel = 'Mass Notifications';

    protected static ?string $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.admin.pages.mass-notifications';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Send Site Notifications To Users')
                    ->description('Send site notifications to users. These notifications will appear in their notification center.')
                    ->schema([
                        Forms\Components\TextInput::make('url')
                            ->label('URL')
                            ->required()
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://example.com/page')
                            ->helperText('Where you want to point this notification to? URL is required.'),

                        Forms\Components\Textarea::make('description')
                            ->label('Notification Text')
                            ->required()
                            ->rows(5)
                            ->maxLength(500)
                            ->placeholder('Write the body of your notification')
                            ->helperText('Write the body of your notification.'),

                        Forms\Components\Select::make('selected_users')
                            ->label('Selected Users (Optional)')
                            ->options(function ($search) {
                                if (empty($search)) {
                                    return [];
                                }
                                return User::where('username', 'like', "%{$search}%")
                                    ->orWhere('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($user) {
                                        return [$user->user_id => $user->username . ' (' . ($user->name ?? $user->email) . ')'];
                                    })
                                    ->toArray();
                            })
                            ->multiple()
                            ->searchable()
                            ->preload(false)
                            ->getSearchResultsUsing(function (string $search): array {
                                return User::where('username', 'like', "%{$search}%")
                                    ->orWhere('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function ($user) {
                                        return [$user->user_id => $user->username . ' (' . ($user->name ?? $user->email) . ')'];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $user = User::find($value);
                                return $user ? $user->username . ' (' . ($user->name ?? $user->email) . ')' : null;
                            })
                            ->helperText('If left empty, the notification will be sent to all active users. Search and select specific users to send only to them.'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label('Send Notification')
                ->color('primary')
                ->icon('heroicon-o-paper-airplane')
                ->action('sendNotification')
                ->requiresConfirmation()
                ->modalHeading('Send Mass Notification')
                ->modalDescription('Are you sure you want to send this notification? This action cannot be undone.')
                ->modalSubmitActionLabel('Yes, send it'),
        ];
    }

    public function sendNotification(): void
    {
        $data = $this->form->getState();

        try {
            // Get recipients
            $recipients = $this->getRecipients($data['selected_users'] ?? []);

            if (empty($recipients)) {
                Notification::make()
                    ->title('No recipients found')
                    ->body('No users found to send notifications to.')
                    ->warning()
                    ->send();
                return;
            }

            // Send notifications
            $sentCount = 0;
            $failedCount = 0;

            foreach ($recipients as $user) {
                if ($this->sendNotificationToUser($user, $data)) {
                    $sentCount++;
                } else {
                    $failedCount++;
                }
            }

            Notification::make()
                ->title('Notifications sent successfully')
                ->body("Successfully sent {$sentCount} notifications" . ($failedCount > 0 ? " ({$failedCount} failed)" : "") . ".")
                ->success()
                ->send();

            // Reset form after successful send
            $this->form->fill();

        } catch (\Exception $e) {
            Log::error('Error sending mass notifications: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);

            Notification::make()
                ->title('Error sending notifications')
                ->body('An error occurred while sending notifications: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function getRecipients(array $selectedUsers = []): array
    {
        // If specific users are selected, use them
        if (!empty($selectedUsers)) {
            return User::whereIn('user_id', $selectedUsers)
                ->where('active', '1')
                ->get()
                ->toArray();
        }

        // Otherwise, send to all active users
        return User::where('active', '1')
            ->get()
            ->toArray();
    }

    private function sendNotificationToUser(array $user, array $data): bool
    {
        try {
            // Insert notification into Wo_Notifications table
            // Matching old API structure
            $adminUser = Auth::user();
            $adminUserId = $adminUser->user_id ?? $adminUser->id ?? 0;
            
            DB::table('Wo_Notifications')->insert([
                'notifier_id' => $adminUserId, // Admin user ID
                'recipient_id' => $user['user_id'],
                'type' => 'admin_notification',
                'text' => $data['description'],
                'url' => $data['url'],
                'time' => time(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send notification to user ' . $user['user_id'] . ': ' . $e->getMessage());
            return false;
        }
    }
}

