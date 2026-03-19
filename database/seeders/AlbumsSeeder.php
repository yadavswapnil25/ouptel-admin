<?php

namespace Database\Seeders;

use App\Models\AlbumMedia;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AlbumsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::query()
            ->where('active', '1')
            ->orderBy('user_id')
            ->limit(5)
            ->get();

        if ($users->isEmpty()) {
            $this->command->warn('No active users found, skipping AlbumsSeeder.');
            return;
        }

        $albumTemplates = [
            ['name' => 'Campus Life 2026', 'text' => 'Best moments from campus life and events.'],
            ['name' => 'Travel Memories', 'text' => 'Sharing some favorite travel photos.'],
            ['name' => 'Team Meetup', 'text' => 'Snapshots from our latest team meetup.'],
        ];

        $seededAlbums = 0;
        $baseTime = time();

        foreach ($users as $uIndex => $user) {
            // Create up to 2 albums per user
            foreach (array_slice($albumTemplates, 0, 2) as $aIndex => $template) {
                $albumName = $template['name'] . ' - User ' . $user->user_id;
                $albumTime = $baseTime - (($uIndex * 2 + $aIndex) * 900);
                $nextPostId = $this->generateNextPostId();

                $post = Post::query()->updateOrCreate(
                    [
                        'user_id' => (int) $user->user_id,
                        'album_name' => $albumName,
                    ],
                    [
                        'postText' => $template['text'],
                        'postType' => 'photo',
                        'postPrivacy' => '0',
                        'active' => 1,
                        'multi_image_post' => 1,
                        'time' => $albumTime,
                        'registered' => date('Y-m-d H:i:s', $albumTime),
                        'post_id' => $nextPostId,
                    ]
                );

                // Seed 3 images per album
                $images = [
                    'images/placeholders/post-avatar.svg',
                    'images/placeholders/blog-image.svg',
                    'images/placeholders/default-avatar.svg',
                ];

                foreach ($images as $img) {
                    AlbumMedia::query()->updateOrCreate(
                        [
                            'post_id' => $post->id,
                            'image' => $img,
                        ],
                        [
                            'post_id' => $post->id,
                            'image' => $img,
                        ]
                    );
                }

                $seededAlbums++;
            }
        }

        $this->command->info("Albums seeded successfully: {$seededAlbums} album posts.");
    }

    private function generateNextPostId(): int
    {
        $maxPostId = (int) DB::table('Wo_Posts')->max('post_id');
        return $maxPostId > 0 ? $maxPostId + 1 : 1;
    }
}

