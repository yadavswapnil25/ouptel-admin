<?php

namespace App\Filament\Admin\Resources\NewsContentReviewResource\Pages;

use App\Filament\Admin\Resources\NewsContentReviewResource;
use App\Models\NewsArticle;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewNewsContentReview extends ViewRecord
{
    protected static string $resource = NewsContentReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('publish')
                ->label('Publish')
                ->color('success')
                ->icon('heroicon-o-check-badge')
                ->requiresConfirmation()
                ->visible(fn () => $this->record instanceof NewsArticle && $this->record->status === 'pending_review')
                ->action(function () {
                    $adminId = Auth::user()?->user_id ?? Auth::id();
                    if ($this->record->publishFromReview($adminId ? (int) $adminId : null)) {
                        Notification::make()->title('Article published')->success()->send();
                        $this->redirect(NewsContentReviewResource::getUrl('index'));
                    }
                }),
            Actions\Action::make('sendBack')
                ->label('Send Back')
                ->color('warning')
                ->icon('heroicon-o-arrow-uturn-left')
                ->form([
                    \Filament\Forms\Components\Textarea::make('feedback')
                        ->label('Feedback for editor')
                        ->required()
                        ->rows(3),
                ])
                ->visible(fn () => $this->record instanceof NewsArticle && $this->record->status === 'pending_review')
                ->action(function (array $data) {
                    $adminId = Auth::user()?->user_id ?? Auth::id();
                    if ($this->record->sendBackFromReview($data['feedback'] ?? null, $adminId ? (int) $adminId : null)) {
                        Notification::make()->title('Sent back to editor')->warning()->send();
                        $this->redirect(NewsContentReviewResource::getUrl('index'));
                    }
                }),
            Actions\Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->form([
                    \Filament\Forms\Components\Textarea::make('feedback')
                        ->label('Rejection reason')
                        ->required()
                        ->rows(3),
                ])
                ->visible(fn () => $this->record instanceof NewsArticle && $this->record->status === 'pending_review')
                ->action(function (array $data) {
                    $adminId = Auth::user()?->user_id ?? Auth::id();
                    if ($this->record->rejectFromReview($data['feedback'] ?? null, $adminId ? (int) $adminId : null)) {
                        Notification::make()->title('Article rejected')->danger()->send();
                        $this->redirect(NewsContentReviewResource::getUrl('index'));
                    }
                }),
        ];
    }
}
