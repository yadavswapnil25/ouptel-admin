<?php

namespace App\Filament\Admin\Resources\PostResource\Pages;

use App\Filament\Admin\Resources\PostResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;
    protected array $albumImages = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $candidateIds = array_values(array_unique(array_filter([
            (string) $this->record->id,
            isset($this->record->post_id) ? (string) $this->record->post_id : null,
        ], fn ($v) => $v !== null && $v !== '')));

        $data['album_images'] = DB::table('Wo_Albums_Media')
            ->whereIn('post_id', $candidateIds)
            ->orderBy('id')
            ->pluck('image')
            ->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // album_images is intentionally dehydrated(false), so read from raw Livewire state.
        $rawAlbumImages = $this->data['album_images'] ?? [];
        $this->albumImages = array_values(array_filter($rawAlbumImages));

        if (!empty($this->albumImages)) {
            $data['multi_image_post'] = 1;
            if (empty($data['album_name'])) {
                $data['album_name'] = 'Album ' . date('Y-m-d H:i');
            }
        } else {
            $data['multi_image_post'] = 0;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (!$this->record) {
            return;
        }

        $candidateIds = array_values(array_unique(array_filter([
            (string) $this->record->id,
            isset($this->record->post_id) ? (string) $this->record->post_id : null,
        ], fn ($v) => $v !== null && $v !== '')));

        DB::table('Wo_Albums_Media')->whereIn('post_id', $candidateIds)->delete();

        foreach ($this->albumImages as $imagePath) {
            DB::table('Wo_Albums_Media')->insert([
                'post_id' => $this->record->id,
                'image' => $imagePath,
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}



