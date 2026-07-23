<?php

namespace App\Filament\Admin\Resources\NewsPressProfileResource\Pages;

use App\Filament\Admin\Resources\NewsPressProfileResource;
use App\Models\NewsPressProfile;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewNewsPressProfile extends ViewRecord
{
    protected static string $resource = NewsPressProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('Preview Live Page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => NewsPressProfileResource::pressFrontendUrl($this->record->slug))
                ->openUrlInNewTab(),
            Actions\Action::make('suspend')
                ->label('Suspend')
                ->color('danger')
                ->icon('heroicon-o-pause-circle')
                ->visible(fn () => $this->record instanceof NewsPressProfile && $this->record->isActive())
                ->form([
                    Forms\Components\Textarea::make('suspend_reason')
                        ->label('Reason (optional)')
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->modalHeading('Suspend press page?')
                ->action(function (array $data) {
                    $adminId = Auth::user()?->user_id ?? Auth::id();
                    if ($this->record->suspend($data['suspend_reason'] ?? null, $adminId ? (int) $adminId : null)) {
                        Notification::make()->title('Press page suspended')->warning()->send();
                        $this->refreshFormData(['status', 'suspend_reason', 'suspended_at']);
                    }
                }),
            Actions\Action::make('reactivate')
                ->label('Reactivate')
                ->color('success')
                ->icon('heroicon-o-play-circle')
                ->visible(fn () => $this->record instanceof NewsPressProfile && $this->record->isSuspended())
                ->requiresConfirmation()
                ->action(function () {
                    if ($this->record->reactivate()) {
                        Notification::make()->title('Press page reactivated')->success()->send();
                        $this->refreshFormData(['status', 'suspend_reason', 'suspended_at']);
                    }
                }),
            Actions\Action::make('reassignSlug')
                ->label('Reassign Slug')
                ->color('warning')
                ->icon('heroicon-o-link')
                ->form([
                    Forms\Components\TextInput::make('slug')
                        ->label('New slug')
                        ->required()
                        ->maxLength(120)
                        ->helperText('Old public link will stop working.'),
                ])
                ->fillForm(fn () => ['slug' => $this->record->slug])
                ->action(function (array $data) {
                    $slug = NewsPressProfile::normalizeSlug($data['slug'] ?? '');

                    if ($slug === '' || NewsPressProfile::isReservedSlug($slug)) {
                        Notification::make()->title('Invalid or reserved slug')->danger()->send();
                        return;
                    }

                    if (!NewsPressProfile::isSlugAvailable($slug, $this->record->id)) {
                        Notification::make()->title('Slug already taken')->danger()->send();
                        return;
                    }

                    $this->record->update(['slug' => $slug]);
                    Notification::make()->title('Slug updated to ' . $slug)->success()->send();
                    $this->refreshFormData(['slug']);
                }),
        ];
    }
}
