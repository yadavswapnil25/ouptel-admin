<?php

namespace App\Filament\Admin\Resources\NewsEditorApplicationResource\Pages;

use App\Filament\Admin\Resources\NewsEditorApplicationResource;
use App\Models\NewsEditorApplication;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewNewsEditorApplication extends ViewRecord
{
    protected static string $resource = NewsEditorApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->visible(fn () => $this->record instanceof NewsEditorApplication && $this->record->isPending())
                ->action(function () {
                    $adminId = Auth::user()?->user_id ?? Auth::id();
                    if ($this->record->approve($adminId ? (int) $adminId : null)) {
                        Notification::make()->title('Editor approved')->success()->send();
                        $this->refreshFormData(['status', 'reviewed_at', 'reviewed_by', 'review_note']);
                    }
                }),
            Actions\Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->form([
                    \Filament\Forms\Components\Textarea::make('review_note')
                        ->label('Rejection reason')
                        ->required()
                        ->rows(3),
                ])
                ->visible(fn () => $this->record instanceof NewsEditorApplication && $this->record->isPending())
                ->action(function (array $data) {
                    $adminId = Auth::user()?->user_id ?? Auth::id();
                    if ($this->record->reject($adminId ? (int) $adminId : null, $data['review_note'] ?? null)) {
                        Notification::make()->title('Application rejected')->warning()->send();
                        $this->refreshFormData(['status', 'reviewed_at', 'reviewed_by', 'review_note']);
                    }
                }),
        ];
    }
}
