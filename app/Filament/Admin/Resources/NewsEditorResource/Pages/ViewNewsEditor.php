<?php

namespace App\Filament\Admin\Resources\NewsEditorResource\Pages;

use App\Filament\Admin\Resources\NewsEditorResource;
use App\Models\NewsEditor;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewNewsEditor extends ViewRecord
{
    protected static string $resource = NewsEditorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('revoke')
                ->label('Revoke Access')
                ->color('danger')
                ->icon('heroicon-o-no-symbol')
                ->form([
                    \Filament\Forms\Components\Textarea::make('revoke_note')
                        ->label('Reason')
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->visible(fn () => $this->record instanceof NewsEditor && $this->record->isActive())
                ->action(function (array $data) {
                    if ($this->record->revoke($data['revoke_note'] ?? null)) {
                        Notification::make()->title('Editor access revoked')->warning()->send();
                        $this->refreshFormData(['status', 'revoked_at', 'revoke_note']);
                    }
                }),
        ];
    }
}
