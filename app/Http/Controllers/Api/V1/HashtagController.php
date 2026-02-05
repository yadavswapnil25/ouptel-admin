<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HashtagController extends Controller
{
    /**
     * Get trending hashtags
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getTrending(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'No session sent.'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'Invalid session.'
                ]
            ], 401);
        }

        // Check if Wo_Hashtags table exists
        if (!Schema::hasTable('Wo_Hashtags')) {
            return response()->json([
                'api_status' => '200',
                'api_text' => 'success',
                'api_version' => '1.0',
                'data' => [],
                'message' => 'Hashtags table not found'
            ]);
        }

        // Get pagination parameters
        $limit = (int) ($request->input('limit', $request->query('limit', 10)));
        $limit = max(1, min($limit, 50)); // Limit between 1 and 50

        // Get trending hashtags from Wo_Hashtags table
        // Note: 'hash' column contains MD5 hash, 'tag' column contains the actual hashtag text
        // Order by trend_use_num DESC (number of times used)
        $hashtags = DB::table('Wo_Hashtags')
            ->select('hash', 'tag', 'trend_use_num', 'last_trend_time')
            ->where(function($q) {
                $q->whereNotNull('tag')
                  ->where('tag', '!=', '');
            })
            ->where('trend_use_num', '>', 0)
            ->orderByDesc('trend_use_num')
            ->orderByDesc('last_trend_time')
            ->limit($limit * 2) // Get more to filter out ones with 0 posts
            ->get();

        // If Wo_Hashtags table is empty or doesn't have trend_use_num data,
        // fall back to counting hashtags from posts
        if ($hashtags->isEmpty()) {
            $hashtags = $this->getTrendingFromPosts($limit);
        } else {
            // Enrich with actual post counts from Wo_Posts
            // Use 'tag' column (actual hashtag text), not 'hash' (MD5 hash)
            $hashtags = $hashtags->map(function($hashtag) {
                $hashtagName = $hashtag->tag ?? '';
                $hashtagName = trim($hashtagName);
                $hashtagName = ltrim($hashtagName, '#');
                
                if (empty($hashtagName)) {
                    return null;
                }
                
                // Count actual posts containing this hashtag
                $postCount = DB::table('Wo_Posts')
                    ->where('active', 1)
                    ->where(function($q) use ($hashtagName) {
                        $q->where('postText', 'LIKE', '%#' . $hashtagName . '%')
                          ->orWhere('postText', 'LIKE', '%# ' . $hashtagName . '%')
                          ->orWhere('postText', 'LIKE', '%#' . $hashtagName . ' %')
                          ->orWhere('postText', 'LIKE', '%#' . $hashtagName . PHP_EOL . '%');
                    })
                    ->count();

                return [
                    'hashtag' => '#' . $hashtagName,
                    'hashtag_name' => $hashtagName,
                    'posts_count' => $postCount,
                    'trend_use_num' => $hashtag->trend_use_num ?? 0,
                    'last_trend_time' => $hashtag->last_trend_time ?? 0,
                ];
            })
            ->filter(function($hashtag) {
                // Only include hashtags that have at least 1 post and are not null
                return $hashtag !== null && $hashtag['posts_count'] > 0;
            })
            ->sortByDesc('posts_count')
            ->values()
            ->take($limit);
        }

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'api_version' => '1.0',
            'data' => $hashtags->values()->all(),
            'count' => $hashtags->count(),
        ]);
    }

    /**
     * Get trending hashtags by counting from posts directly
     * Fallback method when Wo_Hashtags table is empty
     * 
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    private function getTrendingFromPosts(int $limit)
    {
        // Extract hashtags from postText and count occurrences
        // This is a simplified approach - in production, you might want to use a more efficient method
        
        $posts = DB::table('Wo_Posts')
            ->where('active', 1)
            ->whereNotNull('postText')
            ->where('postText', '!=', '')
            ->where('postText', 'LIKE', '%#%')
            ->select('postText')
            ->get();

        $hashtagCounts = [];
        
        foreach ($posts as $post) {
            // Extract hashtags from post text
            preg_match_all('/#(\w+)/', $post->postText, $matches);
            
            if (!empty($matches[1])) {
                foreach ($matches[1] as $hashtag) {
                    $hashtag = strtolower(trim($hashtag));
                    if (!empty($hashtag)) {
                        if (!isset($hashtagCounts[$hashtag])) {
                            $hashtagCounts[$hashtag] = 0;
                        }
                        $hashtagCounts[$hashtag]++;
                    }
                }
            }
        }

        // Sort by count descending and take top N
        arsort($hashtagCounts);
        $hashtagCounts = array_slice($hashtagCounts, 0, $limit, true);

        // Format response
        return collect($hashtagCounts)->map(function($count, $hashtag) {
            return [
                'hashtag' => '#' . $hashtag,
                'hashtag_name' => $hashtag,
                'posts_count' => $count,
                'trend_use_num' => $count,
                'last_trend_time' => time(),
            ];
        })->values();
    }

    /**
     * Get posts for a specific hashtag
     * 
     * @param Request $request
     * @param string $hashtag
     * @return JsonResponse
     */
    public function getHashtagPosts(Request $request, string $hashtag): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'No session sent.'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '5',
                    'error_text' => 'Invalid session.'
                ]
            ], 401);
        }

        // Remove # from hashtag if present
        $hashtagName = ltrim($hashtag, '#');
        
        if (empty($hashtagName)) {
            return response()->json([
                'api_status' => '400',
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => '8',
                    'error_text' => 'Hashtag name is required.'
                ]
            ], 400);
        }

        // Pagination parameters
        $page = (int) ($request->input('page', $request->query('page', 1)));
        $page = max(1, $page);
        
        $perPage = (int) ($request->input('per_page', $request->input('limit', $request->query('limit', 20))));
        $perPage = max(1, min($perPage, 50));

        $offset = ($page - 1) * $perPage;

        // Get posts containing this hashtag
        $query = DB::table('Wo_Posts')
            ->where('active', 1)
            ->where(function($q) use ($hashtagName) {
                $q->where('postText', 'LIKE', '%#' . $hashtagName . '%')
                  ->orWhere('postText', 'LIKE', '%# ' . $hashtagName . '%')
                  ->orWhere('postText', 'LIKE', '%#' . $hashtagName . ' %')
                  ->orWhere('postText', 'LIKE', '%#' . $hashtagName . PHP_EOL . '%');
            })
            ->orderByDesc('time');

        $total = $query->count();
        $posts = $query->offset($offset)->limit($perPage)->get();

        // Format posts (reuse ProfileController's formatTimelinePosts logic)
        $profileController = new ProfileController();
        $formattedPosts = [];
        
        foreach ($posts as $post) {
            $formattedPosts[] = $this->formatHashtagPost($post, $tokenUserId);
        }

        return response()->json([
            'api_status' => '200',
            'api_text' => 'success',
            'api_version' => '1.0',
            'hashtag' => '#' . $hashtagName,
            'hashtag_name' => $hashtagName,
            'posts' => $formattedPosts,
            'count' => count($formattedPosts),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
                'has_more' => $page < (int) ceil($total / $perPage),
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? min($offset + $perPage, $total) : 0,
            ],
        ]);
    }

    /**
     * Format a post for hashtag response
     * 
     * @param object $post
     * @param int $loggedUserId
     * @return array
     */
    private function formatHashtagPost($post, int $loggedUserId): array
    {
        $user = DB::table('Wo_Users')->where('user_id', $post->user_id)->first();
        
        // Use post_id for reactions
        $postIdForReactions = $post->post_id ?? $post->id;
        
        // Get reaction counts
        $reactionCounts = $this->getPostReactionCounts($postIdForReactions);
        $totalReactions = array_sum($reactionCounts);
        
        // Get user's reaction
        $userReaction = $this->getUserReaction($postIdForReactions, $loggedUserId);
        $isLiked = $userReaction !== null;

        // Get comments count
        $commentsCount = 0;
        if (Schema::hasTable('Wo_Comments')) {
            try {
                $commentsCount = DB::table('Wo_Comments')
                    ->where('post_id', $post->id)
                    ->count();
            } catch (\Exception $e) {
                $commentsCount = (int) ($post->post_comments ?? 0);
            }
        } else {
            $commentsCount = (int) ($post->post_comments ?? 0);
        }

        // Determine postType
        $postType = $post->postType ?? 'text';
        $colorId = $post->color_id ?? 0;
        if ($colorId > 0) {
            $postType = 'colored';
        }
        
        // Check if it's an album post
        $isAlbumPost = (!empty($post->album_name)) || 
                      (!empty($post->multi_image_post) && ($post->multi_image_post == 1 || $post->multi_image_post == '1'));
        if ($isAlbumPost && $postType !== 'colored') {
            $postType = 'album';
        }
        
        // Get album images if it's an album post
        $albumImages = [];
        if ($isAlbumPost) {
            $albumImages = $this->getAlbumImages($post->id);
        }

        return [
            'id' => $post->id,
            'post_id' => $post->post_id ?? $post->id,
            'user_id' => $post->user_id,
            'user' => [
                'user_id' => $user->user_id ?? 0,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? $user->username ?? ''),
                'username' => $user->username ?? '',
                'avatar' => $user->avatar ?? '',
                'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                'verified' => (bool) ($user->verified ?? false),
            ],
            'postText' => $post->postText ?? '',
            'postType' => $postType,
            'postPrivacy' => $post->postPrivacy ?? '0',
            'postPhoto' => $post->postPhoto ?? '',
            'post_photo_url' => $post->postPhoto ? asset('storage/' . $post->postPhoto) : null,
            'postFile' => isset($post->postFile) ? $post->postFile : '',
            'post_file_url' => (isset($post->postFile) && !empty($post->postFile)) ? asset('storage/' . $post->postFile) : null,
            'postVideo' => (($post->postType ?? '') === 'video' && !empty($post->postFile)) ? $post->postFile : '',
            'post_video_url' => (($post->postType ?? '') === 'video' && !empty($post->postFile)) ? asset('storage/' . $post->postFile) : null,
            'postYoutube' => $post->postYoutube ?? '',
            'postVimeo' => $post->postVimeo ?? '',
            'postLink' => $post->postLink ?? '',
            'postLinkTitle' => $post->postLinkTitle ?? '',
            'postLinkImage' => $post->postLinkImage ?? '',
            'postLinkContent' => $post->postLinkContent ?? '',
            'album_name' => $post->album_name ?? '',
            'multi_image_post' => (bool) ($post->multi_image_post ?? false),
            'album_images' => $albumImages,
            'album_images_count' => count($albumImages),
            'time' => $post->time ?? time(),
            'created_at' => $post->time ? date('c', $post->time) : null,
            'reactions_count' => $totalReactions,
            'reactions' => [
                'total' => $totalReactions,
                'like' => $reactionCounts[1] ?? 0,
                'love' => $reactionCounts[2] ?? 0,
                'haha' => $reactionCounts[3] ?? 0,
                'wow' => $reactionCounts[4] ?? 0,
                'sad' => $reactionCounts[5] ?? 0,
                'angry' => $reactionCounts[6] ?? 0,
                'user_reaction' => $userReaction,
            ],
            'comments_count' => $commentsCount,
            'shares_count' => (int) ($post->postShare ?? 0),
            'is_liked' => $isLiked ? 1 : 0,
            'is_saved' => $this->isPostSaved($post->id, $loggedUserId) ? 1 : 0,
            'is_owner' => ($post->user_id == $loggedUserId) ? 1 : 0,
            'color_id' => $post->color_id ?? null,
            'color' => $colorId > 0 ? $this->getColorData($colorId) : null,
        ];
    }

    /**
     * Get post reaction counts by type
     */
    private function getPostReactionCounts($postId): array
    {
        if (!Schema::hasTable('Wo_Reactions')) {
            return [];
        }

        $reactions = DB::table('Wo_Reactions')
            ->where('post_id', $postId)
            ->select('reaction', DB::raw('count(*) as count'))
            ->groupBy('reaction')
            ->pluck('count', 'reaction')
            ->toArray();

        return [
            1 => $reactions[1] ?? 0, // like
            2 => $reactions[2] ?? 0, // love
            3 => $reactions[3] ?? 0, // haha
            4 => $reactions[4] ?? 0, // wow
            5 => $reactions[5] ?? 0, // sad
            6 => $reactions[6] ?? 0, // angry
        ];
    }

    /**
     * Get user's reaction to a post
     */
    private function getUserReaction($postId, int $userId): ?int
    {
        if (!Schema::hasTable('Wo_Reactions')) {
            return null;
        }

        $reaction = DB::table('Wo_Reactions')
            ->where('post_id', $postId)
            ->where('user_id', $userId)
            ->value('reaction');

        return $reaction ? (int) $reaction : null;
    }

    /**
     * Check if post is saved by user
     */
    private function isPostSaved(int $postId, int $userId): bool
    {
        if (!Schema::hasTable('Wo_SavedPosts')) {
            return false;
        }

        return DB::table('Wo_SavedPosts')
            ->where('post_id', $postId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Get album images for a post
     */
    private function getAlbumImages(int $postId): array
    {
        if (!Schema::hasTable('Wo_Albums_Media')) {
            return [];
        }

        $images = DB::table('Wo_Albums_Media')
            ->where('post_id', $postId)
            ->orderBy('id')
            ->get();

        return $images->map(function($image) {
            return [
                'id' => $image->id ?? null,
                'image' => $image->image ?? '',
                'image_url' => !empty($image->image) ? asset('storage/' . $image->image) : null,
            ];
        })->toArray();
    }

    /**
     * Get color data
     */
    private function getColorData(int $colorId): ?array
    {
        if (!Schema::hasTable('Wo_Colors')) {
            return null;
        }

        $color = DB::table('Wo_Colors')
            ->where('id', $colorId)
            ->first();

        if (!$color) {
            return null;
        }

        return [
            'id' => $color->id ?? null,
            'color_1' => $color->color_1 ?? null,
            'color_2' => $color->color_2 ?? null,
            'text' => $color->text ?? null,
            'time' => $color->time ?? null,
        ];
    }
}

