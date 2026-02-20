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
use App\Filament\Admin\Concerns\HasPageAccess;

class MassNotifications extends Page
{
    use HasPageAccess;

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
                            ->multiple()
                            ->searchable()
                            ->preload(false)
                            ->getSearchResultsUsing(function (string $search): array {
                                if (strlen($search) < 2) {
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
                            ->getOptionLabelUsing(function ($value): ?string {
                                $user = User::where('user_id', $value)->first();
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
            // Check if specific users are selected
            $selectedUsers = $data['selected_users'] ?? [];
            $hasSelectedUsers = !empty($selectedUsers);

            // Count total recipients first
            $totalCount = $this->getRecipientCount($selectedUsers);

            if ($totalCount === 0) {
                Notification::make()
                    ->title('No recipients found')
                    ->body('No users found to send notifications to.')
                    ->warning()
                    ->send();
                return;
            }

            // Get admin user ID
            $adminUser = Auth::user();
            $adminUserId = $adminUser->user_id ?? $adminUser->id ?? 0;
            $currentTime = time();

            // Prepare notification data
            $notificationData = [
                'notifier_id' => $adminUserId,
                'type' => 'admin_notification',
                'text' => $data['description'],
                'url' => $data['url'],
                'time' => $currentTime,
            ];

            // Build bulk insert data
            $notificationsToInsert = [];
            $chunkSize = 500; // Insert 500 notifications at a time

            if ($hasSelectedUsers) {
                // Process selected users in chunks
                User::whereIn('user_id', $selectedUsers)
                    ->where('active', '1')
                    ->chunk($chunkSize, function ($users) use ($notificationData, &$notificationsToInsert, $chunkSize) {
                        foreach ($users as $user) {
                            $notificationsToInsert[] = array_merge($notificationData, [
                                'recipient_id' => $user->user_id,
                            ]);
                        }

                        // Bulk insert when chunk is full
                        if (count($notificationsToInsert) >= $chunkSize) {
                            DB::table('Wo_Notifications')->insert($notificationsToInsert);
                            $notificationsToInsert = [];
                        }
                    });
            } else {
                // Process all active users in chunks
                User::where('active', '1')
                    ->chunk($chunkSize, function ($users) use ($notificationData, &$notificationsToInsert, $chunkSize) {
                        foreach ($users as $user) {
                            $notificationsToInsert[] = array_merge($notificationData, [
                                'recipient_id' => $user->user_id,
                            ]);
                        }

                        // Bulk insert when chunk is full
                        if (count($notificationsToInsert) >= $chunkSize) {
                            DB::table('Wo_Notifications')->insert($notificationsToInsert);
                            $notificationsToInsert = [];
                        }
                    });
            }

            // Insert remaining notifications
            if (!empty($notificationsToInsert)) {
                DB::table('Wo_Notifications')->insert($notificationsToInsert);
            }

            Notification::make()
                ->title('Notifications sent successfully')
                ->body("Successfully sent {$totalCount} notifications.")
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

    private function getRecipientCount(array $selectedUsers = []): int
    {
        // If specific users are selected, count them
        if (!empty($selectedUsers)) {
            return User::whereIn('user_id', $selectedUsers)
                ->where('active', '1')
                ->count();
        }

        // Otherwise, count all active users
        return User::where('active', '1')->count();
    }

}
