<?php

namespace App\Filament\Admin\Resources\BoostCampaignResource\Pages;

use App\Filament\Admin\Resources\BoostCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBoostCampaign extends EditRecord
{
    protected static string $resource = BoostCampaignResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_at'] = time();

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        if (!$record) {
            return;
        }

        $boosted = $record->status === 'active' ? '1' : '0';
        \Illuminate\Support\Facades\DB::table('Wo_Posts')
            ->where('id', $record->post_id)
            ->update(['boosted' => $boosted]);
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
