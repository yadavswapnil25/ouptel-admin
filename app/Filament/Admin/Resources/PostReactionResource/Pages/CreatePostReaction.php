<?php

namespace App\Filament\Admin\Resources\PostReactionResource\Pages;

use App\Filament\Admin\Resources\PostReactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePostReaction extends CreateRecord
{
    protected static string $resource = PostReactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set the appropriate ID field based on content type
        if (isset($data['content_type'])) {
            switch ($data['content_type']) {
                case 'post':
                    $data['post_id'] = $data['post_id'] ?? null;
                    $data['comment_id'] = null;
                    $data['story_id'] = null;
                    $data['message_id'] = null;
                    break;
                case 'comment':
                    $data['comment_id'] = $data['comment_id'] ?? null;
                    $data['post_id'] = null;
                    $data['story_id'] = null;
                    $data['message_id'] = null;
                    break;
                case 'story':
                    $data['story_id'] = $data['story_id'] ?? null;
                    $data['post_id'] = null;
                    $data['comment_id'] = null;
                    $data['message_id'] = null;
                    break;
                case 'message':
                    $data['message_id'] = $data['message_id'] ?? null;
                    $data['post_id'] = null;
                    $data['comment_id'] = null;
                    $data['story_id'] = null;
                    break;
            }
        }

        return $data;
    }
}

