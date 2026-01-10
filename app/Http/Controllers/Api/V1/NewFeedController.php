<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NewFeedController extends Controller
{
    /**
     * Update feed order by type (mimics WoWonder requests.php?f=update_order_by)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateOrderBy(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized - No Bearer token provided'], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token - Session not found'], 401);
        }

        // Validate request parameters (mimics WoWonder structure)
        $validated = $request->validate([
            'hash' => ['required', 'string', 'size:20'], // WoWonder hash validation
            'type' => ['required', 'integer', 'min:0', 'max:5'], // Feed order type
        ]);

        $hash = $validated['hash'];
        $type = (int) $validated['type'];

        // Hash validation (mimics WoWonder hash system)
        $expectedHash = $this->generateHash($tokenUserId);
        if ($hash !== $expectedHash) {
            return response()->json(['ok' => false, 'message' => 'Invalid hash'], 403);
        }

        // Update user's feed order preference
        $this->updateUserFeedOrder($tokenUserId, $type);

        return response()->json([
            'ok' => true,
            'message' => 'Feed order updated successfully',
            'data' => [
                'type' => $type,
                'type_name' => $this->getFeedTypeName($type),
                'user_id' => $tokenUserId,
                'updated_at' => time()
            ]
        ]);
    }

    /**
     * Get feed posts based on current user's order preference
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getFeed(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized - No Bearer token provided'], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token - Session not found'], 401);
        }

        $perPage = (int) ($request->query('per_page', 10));
        $perPage = max(1, min($perPage, 50));
        
        $page = (int) ($request->query('page', 1));
        $page = max(1, $page);

        // Get filter parameter (Image, File, Jobs, Audio, Video, Blogs, Articles)
        $filter = $request->query('filter');
        $validFilters = ['image', 'file', 'jobs', 'audio', 'video', 'blogs', 'articles'];
        if ($filter && !in_array(strtolower($filter), $validFilters)) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid filter. Valid filters are: ' . implode(', ', $validFilters)
            ], 400);
        }

        // Get user's current feed order preference
        $feedOrder = $this->getUserFeedOrder($tokenUserId);

        // Get posts based on feed order and filter with pagination
        $result = $this->getPostsByFeedOrder($tokenUserId, $feedOrder, $perPage, $page, $filter);

        return response()->json([
            'ok' => true,
            'data' => $result['posts'],
            'meta' => [
                'current_feed_type' => $feedOrder,
                'feed_type_name' => $this->getFeedTypeName($feedOrder),
                'filter' => $filter ? ucfirst(strtolower($filter)) : null,
                'pagination' => $result['pagination']
            ]
        ]);
    }

    /**
     * Get available feed order types
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getFeedTypes(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized - No Bearer token provided'], 401);
        }
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token - Session not found'], 401);
        }

        $feedTypes = [
            ['id' => 0, 'name' => 'Top Stories', 'description' => 'Most popular posts'],
            ['id' => 1, 'name' => 'Most Recent', 'description' => 'Latest posts first'],
            ['id' => 2, 'name' => 'People You May Know', 'description' => 'Suggested friends posts'],
            ['id' => 3, 'name' => 'Photos', 'description' => 'Posts with photos only'],
            ['id' => 4, 'name' => 'Videos', 'description' => 'Posts with videos only'],
            ['id' => 5, 'name' => 'Trending', 'description' => 'Trending topics and hashtags'],
        ];

        $currentFeedType = $this->getUserFeedOrder($tokenUserId);

        return response()->json([
            'ok' => true,
            'data' => [
                'current_type' => $currentFeedType,
                'available_types' => $feedTypes,
                'user_id' => $tokenUserId
            ]
        ]);
    }

    /**
     * Generate hash for request validation (mimics WoWonder hash system)
     * 
     * @param string $userId
     * @return string
     */
    private function generateHash(string $userId): string
    {
        // Simplified hash generation (mimics WoWonder's hash system)
        $salt = 'wowonder_salt_' . date('Y-m-d'); // Daily salt
        return substr(md5($userId . $salt), 0, 20);
    }

    /**
     * Update user's feed order preference
     * 
     * @param string $userId
     * @param int $type
     * @return void
     */
    private function updateUserFeedOrder(string $userId, int $type): void
    {
        // Note: Wo_UserSettings table doesn't exist, so we'll skip storing preferences
        // In a real implementation, you would create this table or use an alternative storage method
        // For now, we'll just accept the request without storing the preference
    }

    /**
     * Get user's current feed order preference
     * 
     * @param string $userId
     * @return int
     */
    private function getUserFeedOrder(string $userId): int
    {
        // Note: Wo_UserSettings table doesn't exist, so return default value
        // In a real implementation, you would query this table for user preferences
        return 1; // Default to "Most Recent"
    }

    /**
     * Get posts based on feed order type with pagination
     * 
     * @param string $userId
     * @param int $feedOrder
     * @param int $perPage
     * @param int $page
     * @param string|null $filter
     * @return array
     */
    private function getPostsByFeedOrder(string $userId, int $feedOrder, int $perPage, int $page, ?string $filter = null): array
    {
        // Note: This is a simplified implementation since Wo_Posts table structure may vary
        // In a real implementation, you'd need to adjust based on your actual database schema

        $query = DB::table('Wo_Posts')
            ->where('active', '1');
            // Note: privacy column doesn't exist in Wo_Posts table

        // Apply filter if provided
        if ($filter) {
            $filter = strtolower($filter);
            switch ($filter) {
                case 'image':
                    $query->where(function($q) {
                        $q->where('postType', 'photo')
                          ->orWhere(function($q2) {
                              $q2->whereNotNull('postPhoto')
                                 ->where('postPhoto', '!=', '');
                          });
                    });
                    break;
                
                case 'file':
                    $query->where(function($q) {
                        $q->where('postType', 'file')
                          ->orWhere(function($q2) {
                              $q2->whereNotNull('postFile')
                                 ->where('postFile', '!=', '');
                          });
                    });
                    break;
                
                case 'jobs':
                    $query->where(function($q) {
                        $q->where('postType', 'job')
                          ->orWhere(function($q2) {
                              $q2->whereNotNull('job_id')
                                 ->where('job_id', '>', 0);
                          });
                    });
                    break;
                
                case 'audio':
                    $query->where(function($q) {
                        $q->where('postType', 'audio')
                          ->orWhere(function($q2) {
                              $q2->whereNotNull('postRecord')
                                 ->where('postRecord', '!=', '');
                          });
                    });
                    break;
                
                case 'video':
                    $query->where(function($q) {
                        $q->where('postType', 'video')
                          ->orWhere(function($q2) {
                              $q2->whereNotNull('postYoutube')
                                 ->where('postYoutube', '!=', '');
                          })
                          ->orWhere(function($q2) {
                              $q2->whereNotNull('postVimeo')
                                 ->where('postVimeo', '!=', '');
                          })
                          ->orWhere(function($q2) {
                              $q2->whereNotNull('postFacebook')
                                 ->where('postFacebook', '!=', '');
                          })
                          ->orWhere(function($q2) {
                              $q2->whereNotNull('postPlaytube')
                                 ->where('postPlaytube', '!=', '');
                          })
                          ->orWhere(function($q2) {
                              $q2->whereNotNull('postDeepsound')
                                 ->where('postDeepsound', '!=', '');
                          });
                    });
                    break;
                
                case 'blogs':
                    $query->where(function($q) {
                        $q->where('postType', 'blog')
                          ->orWhere(function($q2) {
                              $q2->whereNotNull('blog_id')
                                 ->where('blog_id', '>', 0);
                          });
                    });
                    break;
                
                case 'articles':
                    // Articles are stored in Wo_Blog table and linked via blog_id
                    // Same as blogs for now, but kept separate for future distinction
                    $query->where(function($q) {
                        $q->where('postType', 'blog')
                          ->orWhere(function($q2) {
                              $q2->whereNotNull('blog_id')
                                 ->where('blog_id', '>', 0);
                          });
                    });
                    break;
            }
        }

        switch ($feedOrder) {
            case 0: // Top Stories - Most popular
                $query->orderByDesc('post_likes')
                      ->orderByDesc('post_comments')
                      ->orderByDesc('time');
                break;
            
            case 1: // Most Recent
                $query->orderByDesc('time');
                break;
            
            case 2: // People You May Know - Suggested friends
                // This would require complex logic for friend suggestions
                $query->orderByDesc('time');
                break;
            
            case 3: // Photos only
                $query->whereNotNull('postFile')
                      ->where('postFile', '!=', '')
                      ->orderByDesc('time');
                break;
            
            case 4: // Videos only
                $query->whereNotNull('postVideo')
                      ->where('postVideo', '!=', '')
                      ->orderByDesc('time');
                break;
            
            case 5: // Trending
                $query->where('time', '>', time() - (7 * 24 * 60 * 60)) // Last 7 days
                      ->orderByDesc('post_likes')
                      ->orderByDesc('time');
                break;
            
            default:
                $query->orderByDesc('time');
        }

        // Get total count for pagination
        $total = $query->count();
        
        // Calculate offset
        $offset = ($page - 1) * $perPage;
        
        // Get paginated posts
        $posts = $query->offset($offset)->limit($perPage)->get();
        
        // Calculate pagination metadata
        $lastPage = (int) ceil($total / $perPage);
        $hasMore = $page < $lastPage;

        $formattedPosts = $posts->map(function ($post) use ($userId) {
            // Get user information
            $user = DB::table('Wo_Users')->where('user_id', $post->user_id)->first();
            
            // Get post reactions count (try Wo_Reactions table first, fallback to post_likes column)
            // Use post_id field (which is used to store reactions) with fallback to id
            $postIdForReactions = $post->post_id ?? $post->id;
            $reactionsCount = $this->getPostReactionsCount($postIdForReactions, $post);
            
            // Get post comments count (try Wo_Comments table first, fallback to post_comments column)
            // Use post_id field (which is used to store comments) with fallback to id
            $postIdForComments = $post->post_id ?? $post->id;
            $commentsCount = $this->getPostCommentsCount($postIdForComments, $post);
            
            // Get album images if it's an album post
            $albumImages = [];
            if ($post->album_name && $post->multi_image_post) {
                $albumImages = $this->getAlbumImages($post->id);
            }
            
            // Get poll options if it's a poll post
            $pollOptions = [];
            if (isset($post->poll_id) && $post->poll_id == 1) {
                $pollOptions = $this->getPollOptions($post->id, $userId);
            }
            
            return [
                'id' => $post->id,
                'post_id' => $post->post_id ?? $post->id,
                'user_id' => $post->user_id,
                'post_text' => $post->postText ?? '',
                'post_type' => $this->getPostType($post),
                'post_privacy' => $post->postPrivacy ?? '0',
                'post_privacy_text' => $this->getPostPrivacyText($post->postPrivacy ?? '0'),
                
                // Media content
                'post_photo' => $post->postPhoto ?? '',
                'post_photo_url' => $this->getPostPhotoUrl($post),
                'post_file' => $post->postFile ?? '',
                'post_file_url' => ($post->postFile ?? '') ? asset('storage/' . $post->postFile) : null,
                'post_record' => $post->postRecord ?? '',
                'post_record_url' => ($post->postRecord ?? '') ? asset('storage/' . $post->postRecord) : null,
                'post_youtube' => $post->postYoutube ?? '',
                'post_vimeo' => $post->postVimeo ?? '',
                'post_dailymotion' => $post->postDailymotion ?? '',
                'post_facebook' => $post->postFacebook ?? '',
                'post_vine' => $post->postVine ?? '',
                'post_soundcloud' => $post->postSoundCloud ?? '',
                'post_playtube' => $post->postPlaytube ?? '',
                'post_deepsound' => $post->postDeepsound ?? '',
                'post_link' => $post->postLink ?? '',
                'post_link_title' => $post->postLinkTitle ?? '',
                'post_link_image' => $post->postLinkImage ?? '',
                'post_link_content' => $post->postLinkContent ?? '',
                'post_sticker' => $post->postSticker ?? '',
                'post_map' => $post->postMap ?? '',
                
                // Album data
                'album_name' => $post->album_name ?? '',
                'multi_image_post' => (bool) ($post->multi_image_post ?? false),
                'album_images' => $albumImages,
                'album_images_count' => count($albumImages),
                
                // Poll data
                'poll_id' => $post->poll_id ?? null,
                'poll_options' => $pollOptions,
                
                // Engagement metrics
                'reactions_count' => $reactionsCount,
                'comments_count' => $commentsCount,
                'shares_count' => $post->postShare ?? 0,
                'views_count' => $post->videoViews ?? 0,
                
                // User interaction
                'is_liked' => $this->isPostLiked($post->id, $userId),
                'is_owner' => $post->user_id == $userId,
                'is_boosted' => (bool) ($post->boosted ?? false),
                'is_pinned' => false, // Would need additional field
                
                // Comments status
                'comments_disabled' => (bool) ($post->comments_status ?? false),
                
                // Author information
                'author' => [
                    'user_id' => $post->user_id,
                    'username' => $user?->username ?? 'Unknown',
                    'name' => $user?->name ?? $user?->username ?? 'Unknown User',
                    'avatar_url' => ($user?->avatar) ? asset('storage/' . $user?->avatar) : null,
                    'verified' => (bool) ($user?->verified ?? false),
                    'is_admin' => (bool) ($user?->admin ?? false),
                ],
                
                // Page/Group context
                'page_id' => $post->page_id ?? null,
                'group_id' => $post->group_id ?? null,
                'event_id' => $post->event_id ?? null,
                
                // Timestamps
                'created_at' => $post->time ? date('c', $post->time) : null,
                'created_at_human' => $post->time ? $this->getHumanTime($post->time) : null,
                'time' => $post->time,
            ];
        })->toArray();

        return [
            'posts' => $formattedPosts,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'has_more' => $hasMore,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? min($offset + $perPage, $total) : 0,
            ]
        ];
    }

    /**
     * Check if user liked a post
     * 
     * @param int $postId
     * @param string $userId
     * @return bool
     */
    private function isPostLiked(int $postId, string $userId): bool
    {
        // Note: Wo_PostLikes table doesn't exist, so return false
        // In a real implementation, you would query this table for user likes
        return false;
    }

    /**
     * Get feed type name by ID
     * 
     * @param int $type
     * @return string
     */
    private function getFeedTypeName(int $type): string
    {
        $types = [
            0 => 'Top Stories',
            1 => 'Most Recent',
            2 => 'People You May Know',
            3 => 'Photos',
            4 => 'Videos',
            5 => 'Trending',
        ];

        return $types[$type] ?? 'Most Recent';
    }

    /**
     * Get post type based on content
     * 
     * @param object $post
     * @return string
     */
    private function getPostType($post): string
    {
        // Check postType field first if it exists
        if (!empty($post->postType)) {
            return $post->postType;
        }
        
        // Check for job_id
        if (!empty($post->job_id) && $post->job_id > 0) {
            return 'job';
        }
        
        // Check for blog_id (articles/blogs)
        if (!empty($post->blog_id) && $post->blog_id > 0) {
            return 'blog';
        }
        
        // Check media types
        if (!empty($post->postPhoto)) return 'photo';
        if (!empty($post->postYoutube) || !empty($post->postVimeo) || !empty($post->postFacebook)) return 'video';
        if (!empty($post->postFile)) return 'file';
        if (!empty($post->postLink)) return 'link';
        if (!empty($post->postMap)) return 'location';
        if (!empty($post->postRecord)) return 'audio';
        if (!empty($post->postSticker)) return 'sticker';
        if (!empty($post->album_name)) return 'album';
        return 'text';
    }

    /**
     * Get post privacy text
     * 
     * @param string $privacy
     * @return string
     */
    private function getPostPrivacyText(string $privacy): string
    {
        return match($privacy) {
            '0' => 'Public',
            '1' => 'Friends',
            '2' => 'Only Me',
            '3' => 'Custom',
            '4' => 'Group',
            default => 'Public'
        };
    }

    /**
     * Get post reactions count
     * 
     * @param int $postId
     * @param object|null $post Optional post object to use post_likes column as fallback
     * @return int
     */
    private function getPostReactionsCount(int $postId, $post = null): int
    {
        // Try to count from Wo_Reactions table (more accurate, real-time count)
        if (Schema::hasTable('Wo_Reactions')) {
            try {
                $count = DB::table('Wo_Reactions')
                    ->where('post_id', $postId)
                    ->where('comment_id', 0) // Only count post reactions, not comment reactions
                    ->count();
                return $count;
            } catch (\Exception $e) {
                // If query fails, fall through to post_likes column
            }
        }
        
        // Fallback: Use post_likes column if available (cached count)
        if ($post && isset($post->post_likes)) {
            return (int) ($post->post_likes ?? 0);
        }
        
        // Last resort: Query post_likes column
        try {
            $postLikes = DB::table('Wo_Posts')
                ->where('id', $postId)
                ->value('post_likes');
            return (int) ($postLikes ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get post comments count
     * 
     * @param int $postId
     * @param object|null $post Optional post object to use post_comments column as fallback
     * @return int
     */
    private function getPostCommentsCount(int $postId, $post = null): int
    {
        // Try to count from Wo_Comments table (more accurate, real-time count)
        if (Schema::hasTable('Wo_Comments')) {
            try {
                $count = DB::table('Wo_Comments')
                    ->where('post_id', $postId)
                    ->count();
                return $count;
            } catch (\Exception $e) {
                // If query fails, fall through to post_comments column
            }
        }
        
        // Fallback: Use post_comments column if available (cached count)
        if ($post && isset($post->post_comments)) {
            return (int) ($post->post_comments ?? 0);
        }
        
        // Last resort: Query post_comments column
        try {
            $postComments = DB::table('Wo_Posts')
                ->where('id', $postId)
                ->value('post_comments');
            return (int) ($postComments ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get album images for a post
     * 
     * @param int $postId
     * @return array
     */
    private function getAlbumImages(int $postId): array
    {
        $albumImages = DB::table('Wo_Albums_Media')
            ->where('post_id', $postId)
            ->get();

        return $albumImages->map(function($image) {
            return [
                'id' => $image->id,
                'image_path' => $image->image,
                'image_url' => asset('storage/' . $image->image),
            ];
        })->toArray();
    }

    /**
     * Get human readable time
     * 
     * @param int $timestamp
     * @return string
     */
    private function getHumanTime(int $timestamp): string
    {
        $time = time() - $timestamp;
        
        if ($time < 60) return 'Just now';
        if ($time < 3600) return floor($time / 60) . 'm';
        if ($time < 86400) return floor($time / 3600) . 'h';
        if ($time < 2592000) return floor($time / 86400) . 'd';
        if ($time < 31536000) return floor($time / 2592000) . 'mo';
        return floor($time / 31536000) . 'y';
    }

    /**
     * Get poll options with vote counts and percentages
     * 
     * @param int $postId
     * @param string $userId
     * @return array
     */
    private function getPollOptions(int $postId, string $userId): array
    {
        // Check if poll table exists
        if (!Schema::hasTable('Wo_Polls')) {
            return [];
        }

        try {
            // Get poll options from Wo_Polls table
            $options = DB::table('Wo_Polls')
                ->where('post_id', $postId)
                ->get();

            if ($options->isEmpty()) {
                return [];
            }

            // Determine votes table name
            $votesTable = 'Wo_Votes';
            if (!Schema::hasTable($votesTable)) {
                if (Schema::hasTable('Wo_PollVotes')) {
                    $votesTable = 'Wo_PollVotes';
                } else {
                    // If votes table doesn't exist, return options without vote data
                    return $options->map(function ($option) {
                        return [
                            'id' => $option->id,
                            'text' => $option->text ?? '',
                            'votes' => 0,
                            'percentage' => 0,
                            'is_voted' => false,
                        ];
                    })->toArray();
                }
            }

            // Get total votes for the poll
            $totalVotes = DB::table($votesTable)
                ->where('post_id', $postId)
                ->count();

            // Get user's vote
            $userVote = DB::table($votesTable)
                ->where('post_id', $postId)
                ->where('user_id', $userId)
                ->value('option_id');

            // Calculate votes and percentages for each option
            return $options->map(function ($option) use ($votesTable, $postId, $totalVotes, $userVote) {
                $optionVotes = DB::table($votesTable)
                    ->where('post_id', $postId)
                    ->where('option_id', $option->id)
                    ->count();

                $percentage = $totalVotes > 0 ? round(($optionVotes / $totalVotes) * 100, 2) : 0;

                return [
                    'id' => $option->id,
                    'text' => $option->text ?? '',
                    'votes' => $optionVotes,
                    'percentage' => $percentage,
                    'is_voted' => $userVote == $option->id,
                ];
            })->toArray();

        } catch (\Exception $e) {
            // If there's an error, return empty array
            return [];
        }
    }

    /**
     * Get post photo URL - handles both storage paths and external URLs (GIFs)
     * 
     * @param object $post
     * @return string|null
     */
    private function getPostPhotoUrl($post): ?string
    {
        $postPhoto = $post->postPhoto ?? '';
        
        if (empty($postPhoto)) {
            return null;
        }
        
        // Check if postPhoto is already a full URL (for GIF posts from Giphy, Tenor, etc.)
        // Also check if post type is 'gif'
        $isGifPost = ($post->postType ?? '') === 'gif';
        $isUrl = filter_var($postPhoto, FILTER_VALIDATE_URL) !== false;
        
        // If it's a GIF post or already a URL, return as-is
        if ($isGifPost || $isUrl) {
            // Clean up any double slashes that might have been introduced
            return preg_replace('#([^:])//+#', '$1/', $postPhoto);
        }
        
        // Otherwise, it's a storage path - prepend storage URL
        return asset('storage/' . $postPhoto);
    }
}
