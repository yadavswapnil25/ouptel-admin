<?php

namespace App\Filament\Admin\Resources\PostResource\Pages;

use App\Filament\Admin\Resources\PostResource;
use Illuminate\Support\Facades\DB;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected array $albumImages = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $state = $this->form->getState();
        $this->albumImages = array_values(array_filter($state['album_images'] ?? []));

        if (!empty($this->albumImages)) {
            $data['multi_image_post'] = 1;
            if (empty($data['album_name'])) {
                $data['album_name'] = 'Album ' . date('Y-m-d H:i');
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if (empty($this->albumImages) || !$this->record) {
            return;
        }

        // Reset and save current selection of album images.
        DB::table('Wo_Albums_Media')->where('post_id', $this->record->id)->delete();
        foreach ($this->albumImages as $imagePath) {
            DB::table('Wo_Albums_Media')->insert([
                'post_id' => $this->record->id,
                'image' => $imagePath,
            ]);
        }
    }
}



