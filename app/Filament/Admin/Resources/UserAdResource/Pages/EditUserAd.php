<?php

namespace App\Filament\Admin\Resources\UserAdResource\Pages;

use App\Filament\Admin\Resources\UserAdResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserAd extends EditRecord
{
    protected static string $resource = UserAdResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['status'] = $this->record?->status_label ?? 'paused';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
