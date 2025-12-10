<?php

namespace App\Filament\Admin\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManageAnnouncements extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Manage Announcements';

    protected static ?string $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.admin.pages.manage-announcements';

    public ?array $data = [];

    public $activeAnnouncements = [];
    public $inactiveAnnouncements = [];

    public function mount(): void
    {
        $this->loadAnnouncements();
        $this->form->fill();
    }

    public function loadAnnouncements(): void
    {
        $this->activeAnnouncements = DB::table('Wo_Announcement')
            ->where('active', '1')
            ->orderBy('time', 'desc')
            ->get()
            ->map(function ($announcement) {
                return [
                    'id' => $announcement->id,
                    'text' => $announcement->text,
                    'time' => $announcement->time,
                    'views' => $this->getAnnouncementViews($announcement->id),
                ];
            })
            ->toArray();

        $this->inactiveAnnouncements = DB::table('Wo_Announcement')
            ->where('active', '0')
            ->orderBy('time', 'desc')
            ->get()
            ->map(function ($announcement) {
                return [
                    'id' => $announcement->id,
                    'text' => $announcement->text,
                    'time' => $announcement->time,
                    'views' => $this->getAnnouncementViews($announcement->id),
                ];
            })
            ->toArray();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Create New Announcement')
                    ->description('Create a new announcement that will be displayed to users. HTML is allowed.')
                    ->schema([
                        Forms\Components\RichEditor::make('announcement_text')
                            ->label('Announcement Text (HTML Allowed)')
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
                            ->helperText('Write your announcement here. HTML is allowed.'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create Announcement')
                ->color('primary')
                ->icon('heroicon-o-plus')
                ->action('createAnnouncement')
                ->requiresConfirmation()
                ->modalHeading('Create Announcement')
                ->modalDescription('Are you sure you want to create this announcement?')
                ->modalSubmitActionLabel('Yes, create it'),
        ];
    }

    public function createAnnouncement(): void
    {
        $data = $this->form->getState();

        try {
            DB::table('Wo_Announcement')->insert([
                'text' => $data['announcement_text'],
                'active' => '1',
                'time' => time(),
            ]);

            Notification::make()
                ->title('Announcement created successfully')
                ->success()
                ->send();

            // Reload announcements
            $this->loadAnnouncements();
            
            // Reset form
            $this->form->fill();

        } catch (\Exception $e) {
            Log::error('Error creating announcement: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);

            Notification::make()
                ->title('Error creating announcement')
                ->body('An error occurred while creating the announcement: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function deleteAnnouncement($id): void
    {
        try {
            DB::table('Wo_Announcement')->where('id', $id)->delete();

            Notification::make()
                ->title('Announcement deleted successfully')
                ->success()
                ->send();

            // Reload announcements
            $this->loadAnnouncements();

        } catch (\Exception $e) {
            Log::error('Error deleting announcement: ' . $e->getMessage(), [
                'id' => $id,
            ]);

            Notification::make()
                ->title('Error deleting announcement')
                ->body('An error occurred while deleting the announcement.')
                ->danger()
                ->send();
        }
    }

    public function disableAnnouncement($id): void
    {
        try {
            DB::table('Wo_Announcement')
                ->where('id', $id)
                ->update(['active' => '0']);

            Notification::make()
                ->title('Announcement disabled successfully')
                ->success()
                ->send();

            // Reload announcements
            $this->loadAnnouncements();

        } catch (\Exception $e) {
            Log::error('Error disabling announcement: ' . $e->getMessage(), [
                'id' => $id,
            ]);

            Notification::make()
                ->title('Error disabling announcement')
                ->body('An error occurred while disabling the announcement.')
                ->danger()
                ->send();
        }
    }

    public function activateAnnouncement($id): void
    {
        try {
            DB::table('Wo_Announcement')
                ->where('id', $id)
                ->update(['active' => '1']);

            Notification::make()
                ->title('Announcement activated successfully')
                ->success()
                ->send();

            // Reload announcements
            $this->loadAnnouncements();

        } catch (\Exception $e) {
            Log::error('Error activating announcement: ' . $e->getMessage(), [
                'id' => $id,
            ]);

            Notification::make()
                ->title('Error activating announcement')
                ->body('An error occurred while activating the announcement.')
                ->danger()
                ->send();
        }
    }

    private function getAnnouncementViews($id): int
    {
        return DB::table('Wo_Announcement_Views')
            ->where('announcement_id', $id)
            ->count();
    }

    public function getTimeElapsedString(int $timestamp): string
    {
        $time = time() - $timestamp;
        
        if ($time < 60) return 'Just now';
        if ($time < 3600) return floor($time / 60) . ' minutes ago';
        if ($time < 86400) return floor($time / 3600) . ' hours ago';
        if ($time < 2592000) return floor($time / 86400) . ' days ago';
        if ($time < 31536000) return floor($time / 2592000) . ' months ago';
        return floor($time / 31536000) . ' years ago';
    }
}

