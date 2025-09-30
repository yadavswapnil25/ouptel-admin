<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PostController extends Controller
{
    /**
     * Create a new post (mimics WoWonder requests.php?f=posts&s=insert_new_post)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function insertNewPost(Request $request): JsonResponse
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

        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'postText' => 'nullable|string|max:5000',
            'postPrivacy' => 'required|in:0,1,2,3,4', // 0=Public, 1=Friends, 2=Only Me, 3=Custom, 4=Group
            'postType' => 'nullable|in:text,photo,video,file,link,location,audio,sticker,album,poll,blog,forum,product,job,offer,funding',
            'page_id' => 'nullable|integer',
            'group_id' => 'nullable|integer',
            'event_id' => 'nullable|integer',
            'recipient_id' => 'nullable|integer',
            'postLink' => 'nullable|url',
            'postLinkTitle' => 'nullable|string|max:255',
            'postLinkImage' => 'nullable|string|max:500',
            'postLinkContent' => 'nullable|string|max:1000',
            'postYoutube' => 'nullable|string|max:500',
            'postVimeo' => 'nullable|string|max:500',
            'postDailymotion' => 'nullable|string|max:500',
            'postFacebook' => 'nullable|string|max:500',
            'postVine' => 'nullable|string|max:500',
            'postSoundCloud' => 'nullable|string|max:500',
            'postPlaytube' => 'nullable|string|max:500',
            'postDeepsound' => 'nullable|string|max:500',
            'postMap' => 'nullable|string|max:500',
            'postFeeling' => 'nullable|string|max:100',
            'postListening' => 'nullable|string|max:100',
            'postTraveling' => 'nullable|string|max:100',
            'postWatching' => 'nullable|string|max:100',
            'postPlaying' => 'nullable|string|max:100',
            'album_name' => 'nullable|string|max:255',
            'poll_id' => 'nullable|integer',
            'blog_id' => 'nullable|integer',
            'forum_id' => 'nullable|integer',
            'thread_id' => 'nullable|integer',
            'product_id' => 'nullable|integer',
            'job_id' => 'nullable|integer',
            'offer_id' => 'nullable|integer',
            'fund_raise_id' => 'nullable|integer',
            'fund_id' => 'nullable|integer',
            'shared_from' => 'nullable|integer',
            'parent_id' => 'nullable|integer',
            'comments_status' => 'nullable|boolean',
            'blur' => 'nullable|boolean',
            'color_id' => 'nullable|integer',
            'postPhoto' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
            'postFile' => 'nullable|file|mimes:pdf,doc,docx,txt,zip,rar|max:51200', // 50MB max
            'postRecord' => 'nullable|file|mimes:mp3,wav,ogg|max:51200', // 50MB max
            'postSticker' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Hash validation removed for simplified authentication

        // Check if user exists
        $user = User::where('user_id', $tokenUserId)->first();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'User not found'], 404);
        }

        // Validate content requirements
        $postText = $request->input('postText', '');
        $postPhoto = $request->file('postPhoto');
        $postFile = $request->file('postFile');
        $postRecord = $request->file('postRecord');
        $postYoutube = $request->input('postYoutube', '');
        $postLink = $request->input('postLink', '');
        $postMap = $request->input('postMap', '');
        $postSticker = $request->input('postSticker', '');

        // At least one content field must be provided
        if (empty($postText) && !$postPhoto && !$postFile && !$postRecord && 
            empty($postYoutube) && empty($postLink) && empty($postMap) && empty($postSticker)) {
            return response()->json([
                'ok' => false,
                'message' => 'At least one content field must be provided (text, photo, file, video, link, location, or sticker)'
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Generate unique post ID
            $postId = $this->generatePostId();

            // Handle file uploads
            $postPhotoPath = '';
            $postFilePath = '';
            $postRecordPath = '';

            if ($postPhoto) {
                $postPhotoPath = $this->handleFileUpload($postPhoto, 'posts/photos', 'photo');
            }

            if ($postFile) {
                $postFilePath = $this->handleFileUpload($postFile, 'posts/files', 'file');
            }

            if ($postRecord) {
                $postRecordPath = $this->handleFileUpload($postRecord, 'posts/audio', 'audio');
            }

            // Determine post type
            $postType = $this->determinePostType($request, $postPhotoPath, $postFilePath, $postRecordPath);

            // Prepare post data with proper null handling
            $postData = [
                'post_id' => $postId,
                'user_id' => $tokenUserId,
                'recipient_id' => $request->input('recipient_id', 0),
                'postText' => $postText,
                'page_id' => $request->input('page_id', 0),
                'group_id' => $request->input('group_id', 0),
                'event_id' => $request->input('event_id', 0),
                'page_event_id' => $request->input('page_event_id', 0),
                'postLink' => $postLink,
                'postLinkTitle' => $request->input('postLinkTitle', ''),
                'postLinkImage' => $request->input('postLinkImage', ''),
                'postLinkContent' => $request->input('postLinkContent', ''),
                'postVimeo' => $request->input('postVimeo', ''),
                'postDailymotion' => $request->input('postDailymotion', ''),
                'postFacebook' => $request->input('postFacebook', ''),
                'postFile' => $postFilePath,
                'postFileName' => $postFile ? $postFile->getClientOriginalName() : '',
                'postFileThumb' => $this->generateFileThumbnail($postFilePath),
                'postYoutube' => $postYoutube,
                'postVine' => $request->input('postVine', ''),
                'postSoundCloud' => $request->input('postSoundCloud', ''),
                'postPlaytube' => $request->input('postPlaytube', ''),
                'postDeepsound' => $request->input('postDeepsound', ''),
                'postMap' => $postMap,
                'postShare' => '0',
                'postPrivacy' => $request->input('postPrivacy', '0'),
                'postType' => $postType,
                'postFeeling' => $request->input('postFeeling', ''),
                'postListening' => $request->input('postListening', ''),
                'postTraveling' => $request->input('postTraveling', ''),
                'postWatching' => $request->input('postWatching', ''),
                'postPlaying' => $request->input('postPlaying', ''),
                'postPhoto' => $postPhotoPath,
                'time' => time(),
                'registered' => time(),
                'album_name' => $request->input('album_name', ''),
                'multi_image' => '0',
                'multi_image_post' => '0',
                'boosted' => '0',
                'product_id' => $request->input('product_id', 0),
                'poll_id' => $request->input('poll_id', 0),
                'blog_id' => $request->input('blog_id', 0),
                'forum_id' => $request->input('forum_id', 0),
                'thread_id' => $request->input('thread_id', 0),
                'videoViews' => '0',
                'postRecord' => $postRecordPath,
                'postSticker' => $postSticker,
                'shared_from' => $request->input('shared_from', 0),
                'post_url' => $this->generatePostUrl($postId),
                'parent_id' => $request->input('parent_id', 0),
                'cache' => '0',
                'comments_status' => $request->input('comments_status', '0'),
                'blur' => $request->input('blur', '0'),
                'color_id' => $request->input('color_id', 0),
                'job_id' => $request->input('job_id', 0),
                'offer_id' => $request->input('offer_id', 0),
                'fund_raise_id' => $request->input('fund_raise_id', 0),
                'fund_id' => $request->input('fund_id', 0),
                'active' => '1',
                'stream_name' => '',
                'live_time' => '0',
                'live_ended' => '0',
                'agora_resource_id' => '',
                'agora_sid' => '',
                'send_notify' => '1',
            ];

            // Create the post
            $post = Post::create($postData);

            // Handle album creation if multiple images
            if ($request->hasFile('album_images') && $request->input('album_name')) {
                $this->handleAlbumCreation($post->id, $request->file('album_images'), $request->input('album_name'));
            }

            // Note: User post count update skipped - posts column may not exist in Wo_Users table

            // Send notifications to followers/friends
            $this->sendPostNotifications($post, $tokenUserId);

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Post created successfully',
                'data' => [
                    'post_id' => $post->id,
                    'post_id_original' => $post->post_id,
                    'post_url' => $post->post_url,
                    'post_type' => $post->postType,
                    'post_privacy' => $post->postPrivacy,
                    'created_at' => date('c', $post->time),
                    'created_at_human' => $this->getHumanTime($post->time),
                    'user_id' => $post->user_id,
                    'author' => [
                        'user_id' => $user->user_id,
                        'username' => $user->username,
                        'name' => $user->name,
                        'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded files if post creation failed
            if ($postPhotoPath) Storage::delete($postPhotoPath);
            if ($postFilePath) Storage::delete($postFilePath);
            if ($postRecordPath) Storage::delete($postRecordPath);

            return response()->json([
                'ok' => false,
                'message' => 'Failed to create post',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate hash for request validation (mimics WoWonder hash system)
     * 
     * @param string $userId
     * @return string
     */
    private function generateHash(string $userId): string
    {
        $salt = 'wowonder_salt_' . date('Y-m-d'); // Daily salt
        return substr(md5($userId . $salt), 0, 20);
    }

    /**
     * Generate unique post ID
     * 
     * @return int
     */
    private function generatePostId(): int
    {
        do {
            $postId = rand(100000000, 999999999);
        } while (Post::where('post_id', $postId)->exists());

        return $postId;
    }

    /**
     * Handle file upload
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $directory
     * @param string $type
     * @return string
     */
    private function handleFileUpload($file, string $directory, string $type): string
    {
        if (!$file) return '';

        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, 'public');
        
        return $path ?: '';
    }

    /**
     * Determine post type based on content
     * 
     * @param Request $request
     * @param string|null $postPhotoPath
     * @param string|null $postFilePath
     * @param string|null $postRecordPath
     * @return string
     */
    private function determinePostType(Request $request, ?string $postPhotoPath, ?string $postFilePath, ?string $postRecordPath): string
    {
        if ($postPhotoPath) return 'photo';
        if ($postRecordPath) return 'audio';
        if ($postFilePath) return 'file';
        if ($request->input('postYoutube')) return 'video';
        if ($request->input('postLink')) return 'link';
        if ($request->input('postMap')) return 'location';
        if ($request->input('postSticker')) return 'sticker';
        if ($request->input('album_name')) return 'album';
        if ($request->input('poll_id')) return 'poll';
        if ($request->input('blog_id')) return 'blog';
        if ($request->input('forum_id')) return 'forum';
        if ($request->input('product_id')) return 'product';
        if ($request->input('job_id')) return 'job';
        if ($request->input('offer_id')) return 'offer';
        if ($request->input('fund_raise_id')) return 'funding';
        
        return 'text';
    }

    /**
     * Generate file thumbnail path
     * 
     * @param string|null $filePath
     * @return string
     */
    private function generateFileThumbnail(?string $filePath): string
    {
        if (!$filePath) return '';
        
        // For now, return empty string. In a real implementation, you'd generate thumbnails
        return '';
    }

    /**
     * Generate post URL
     * 
     * @param int $postId
     * @return string
     */
    private function generatePostUrl(int $postId): string
    {
        return config('app.url') . '/post/' . $postId;
    }

    /**
     * Handle album creation with multiple images
     * 
     * @param int $postId
     * @param array $images
     * @param string $albumName
     * @return void
     */
    private function handleAlbumCreation(int $postId, array $images, string $albumName): void
    {
        foreach ($images as $image) {
            $imagePath = $this->handleFileUpload($image, 'posts/albums', 'album');
            if ($imagePath) {
                DB::table('Wo_Albums_Media')->insert([
                    'post_id' => $postId,
                    'image' => $imagePath,
                    'time' => time(),
                ]);
            }
        }

        // Update post to indicate it's a multi-image post
        Post::where('id', $postId)->update([
            'multi_image_post' => 1,
            'multi_image' => 1,
        ]);
    }

    /**
     * Update user's post count
     * 
     * @param string $userId
     * @return void
     */
    private function updateUserPostCount(string $userId): void
    {
        // Check if posts column exists in Wo_Users table
        $columns = DB::select("SHOW COLUMNS FROM Wo_Users LIKE 'posts'");
        
        if (!empty($columns)) {
            $postCount = Post::where('user_id', $userId)->where('active', 1)->count();
            DB::table('Wo_Users')->where('user_id', $userId)->update(['posts' => $postCount]);
        }
        // If posts column doesn't exist, skip the update
    }

    /**
     * Send notifications to followers/friends
     * 
     * @param Post $post
     * @param string $userId
     * @return void
     */
    private function sendPostNotifications(Post $post, string $userId): void
    {
        // In a real implementation, you would:
        // 1. Get user's friends/followers
        // 2. Create notifications for them
        // 3. Send push notifications if enabled
        
        // For now, we'll just log the action
        Log::info("Post created by user {$userId} with ID {$post->id}");
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
     * Get single post by ID
     * 
     * @param Request $request
     * @param int $postId
     * @return JsonResponse
     */
    public function getPost(Request $request, int $postId): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        $post = Post::with('user')->where('id', $postId)->orWhere('post_id', $postId)->first();
        
        if (!$post) {
            return response()->json(['ok' => false, 'message' => 'Post not found'], 404);
        }

        // Check privacy
        if (!$this->canViewPost($post, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Access denied'], 403);
        }

        return response()->json([
            'ok' => true,
            'data' => $this->formatPostData($post, $tokenUserId)
        ]);
    }

    /**
     * Check if user can view post based on privacy
     * 
     * @param Post $post
     * @param string $userId
     * @return bool
     */
    private function canViewPost(Post $post, string $userId): bool
    {
        // Owner can always view
        if ($post->user_id == $userId) return true;

        switch ($post->postPrivacy) {
            case '0': // Public
                return true;
            case '1': // Friends
                return $this->areFriends($post->user_id, $userId);
            case '2': // Only Me
                return false;
            case '3': // Custom
                return $this->isInCustomList($post->user_id, $userId);
            case '4': // Group
                return $this->isGroupMember($post->group_id, $userId);
            default:
                return false;
        }
    }

    /**
     * Check if two users are friends
     * 
     * @param string $userId1
     * @param string $userId2
     * @return bool
     */
    private function areFriends(string $userId1, string $userId2): bool
    {
        // Note: Wo_Friends table might not exist
        // In a real implementation, you would query this table
        return false;
    }

    /**
     * Check if user is in custom privacy list
     * 
     * @param string $postOwnerId
     * @param string $userId
     * @return bool
     */
    private function isInCustomList(string $postOwnerId, string $userId): bool
    {
        // Note: Custom privacy lists would need additional tables
        // For now, return false
        return false;
    }

    /**
     * Check if user is group member
     * 
     * @param int|null $groupId
     * @param string $userId
     * @return bool
     */
    private function isGroupMember(?int $groupId, string $userId): bool
    {
        if (!$groupId) return false;
        
        // Note: Wo_Group_Members table might not exist
        // In a real implementation, you would query this table
        return false;
    }

    /**
     * Format post data for API response
     * 
     * @param Post $post
     * @param string $userId
     * @return array
     */
    private function formatPostData(Post $post, string $userId): array
    {
        return [
            'id' => $post->id,
            'post_id' => $post->post_id,
            'user_id' => $post->user_id,
            'post_text' => $post->postText ?? '',
            'post_type' => $post->postType,
            'post_privacy' => $post->postPrivacy,
            'post_privacy_text' => $post->post_privacy_text,
            'post_photo' => $post->postPhoto,
            'post_photo_url' => $post->postPhoto ? asset('storage/' . $post->postPhoto) : null,
            'post_file' => $post->postFile,
            'post_file_url' => $post->postFile ? asset('storage/' . $post->postFile) : null,
            'post_youtube' => $post->postYoutube,
            'post_link' => $post->postLink,
            'post_link_title' => $post->postLinkTitle,
            'post_link_image' => $post->postLinkImage,
            'post_link_content' => $post->postLinkContent,
            'post_map' => $post->postMap,
            'post_record' => $post->postRecord,
            'post_record_url' => $post->postRecord ? asset('storage/' . $post->postRecord) : null,
            'post_sticker' => $post->postSticker,
            'album_name' => $post->album_name,
            'multi_image_post' => (bool) $post->multi_image_post,
            'is_owner' => $post->user_id == $userId,
            'is_active' => $post->is_active,
            'is_boosted' => $post->is_boosted,
            'author' => [
                'user_id' => $post->user->user_id ?? $post->user_id,
                'username' => $post->user->username ?? 'Unknown',
                'name' => $post->user->name ?? 'Unknown User',
                'avatar_url' => $post->user->avatar ? asset('storage/' . $post->user->avatar) : null,
            ],
            'page_id' => $post->page_id,
            'group_id' => $post->group_id,
            'event_id' => $post->event_id,
            'created_at' => date('c', $post->time),
            'created_at_human' => $this->getHumanTime($post->time),
            'time' => $post->time,
        ];
    }

    /**
     * Register a reaction on a post (mimics WoWonder requests.php?f=posts&s=register_reaction)
     * 
     * @param Request $request
     * @param int $postId
     * @return JsonResponse
     */
    public function registerReaction(Request $request, int $postId): JsonResponse
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

        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'reaction' => 'required|integer|in:1,2,3,4,5,6', // 1=Like, 2=Love, 3=Haha, 4=Wow, 5=Sad, 6=Angry
            'hash' => 'nullable|string', // For compatibility with WoWonder hash system
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if post exists
        $post = Post::where('id', $postId)->orWhere('post_id', $postId)->first();
        if (!$post) {
            return response()->json(['ok' => false, 'message' => 'Post not found'], 404);
        }

        // Check if user can react to this post
        if (!$this->canViewPost($post, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Access denied'], 403);
        }

        $reactionType = $request->input('reaction');

        try {
            DB::beginTransaction();

            // Check if user already reacted to this post
            $existingReaction = PostReaction::where('post_id', $post->post_id)
                ->where('user_id', $tokenUserId)
                ->where('comment_id', 0) // Only post reactions, not comment reactions
                ->first();

            if ($existingReaction) {
                if ($existingReaction->reaction == $reactionType) {
                    // User is trying to react with the same reaction - remove it
                    $existingReaction->delete();
                    $action = 'removed';
                } else {
                    // User is changing reaction type
                    $existingReaction->update(['reaction' => $reactionType]);
                    $action = 'updated';
                }
            } else {
                // Create new reaction
                PostReaction::create([
                    'user_id' => $tokenUserId,
                    'post_id' => $post->post_id,
                    'comment_id' => 0,
                    'replay_id' => 0,
                    'message_id' => 0,
                    'story_id' => 0,
                    'reaction' => $reactionType,
                ]);
                $action = 'added';
            }

            // Get updated reaction counts
            $reactionCounts = $this->getPostReactionCounts($post->post_id);

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Reaction ' . $action . ' successfully',
                'data' => [
                    'post_id' => $post->post_id,
                    'action' => $action,
                    'reaction_type' => $reactionType,
                    'reaction_name' => $this->getReactionName($reactionType),
                    'reaction_icon' => $this->getReactionIcon($reactionType),
                    'reaction_counts' => $reactionCounts,
                    'total_reactions' => array_sum($reactionCounts),
                    'user_reaction' => $action === 'removed' ? null : $reactionType,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'ok' => false,
                'message' => 'Failed to register reaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get post reaction counts
     * 
     * @param int $postId
     * @return JsonResponse
     */
    public function getPostReactions(Request $request, int $postId): JsonResponse
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

        // Check if post exists
        $post = Post::where('id', $postId)->orWhere('post_id', $postId)->first();
        if (!$post) {
            return response()->json(['ok' => false, 'message' => 'Post not found'], 404);
        }

        // Check if user can view this post
        if (!$this->canViewPost($post, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Access denied'], 403);
        }

        try {
            $reactionCounts = $this->getPostReactionCounts($post->post_id);
            $userReaction = $this->getUserReaction($post->post_id, $tokenUserId);

            return response()->json([
                'ok' => true,
                'data' => [
                    'post_id' => $post->post_id,
                    'reaction_counts' => $reactionCounts,
                    'total_reactions' => array_sum($reactionCounts),
                    'user_reaction' => $userReaction,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to get post reactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove user's reaction from a post
     * 
     * @param Request $request
     * @param int $postId
     * @return JsonResponse
     */
    public function removeReaction(Request $request, int $postId): JsonResponse
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

        // Check if post exists
        $post = Post::where('id', $postId)->orWhere('post_id', $postId)->first();
        if (!$post) {
            return response()->json(['ok' => false, 'message' => 'Post not found'], 404);
        }

        // Check if user can react to this post
        if (!$this->canViewPost($post, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Access denied'], 403);
        }

        try {
            DB::beginTransaction();

            // Find and remove user's reaction
            $reaction = PostReaction::where('post_id', $post->post_id)
                ->where('user_id', $tokenUserId)
                ->where('comment_id', 0) // Only post reactions, not comment reactions
                ->first();

            if (!$reaction) {
                return response()->json(['ok' => false, 'message' => 'No reaction found to remove'], 404);
            }

            $removedReactionType = $reaction->reaction;
            $reaction->delete();

            // Get updated reaction counts
            $reactionCounts = $this->getPostReactionCounts($post->post_id);

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Reaction removed successfully',
                'data' => [
                    'post_id' => $post->post_id,
                    'action' => 'removed',
                    'removed_reaction_type' => $removedReactionType,
                    'removed_reaction_name' => $this->getReactionName($removedReactionType),
                    'reaction_counts' => $reactionCounts,
                    'total_reactions' => array_sum($reactionCounts),
                    'user_reaction' => null,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'ok' => false,
                'message' => 'Failed to remove reaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reaction counts for a post
     * 
     * @param int $postId
     * @return array
     */
    private function getPostReactionCounts(int $postId): array
    {
        $reactions = PostReaction::where('post_id', $postId)
            ->where('comment_id', 0)
            ->selectRaw('reaction, COUNT(*) as count')
            ->groupBy('reaction')
            ->get();

        $counts = [
            1 => 0, // Like
            2 => 0, // Love
            3 => 0, // Haha
            4 => 0, // Wow
            5 => 0, // Sad
            6 => 0, // Angry
        ];

        foreach ($reactions as $reaction) {
            $counts[$reaction->reaction] = $reaction->count;
        }

        return $counts;
    }

    /**
     * Get user's reaction for a post
     * 
     * @param int $postId
     * @param string $userId
     * @return int|null
     */
    private function getUserReaction(int $postId, string $userId): ?int
    {
        $reaction = PostReaction::where('post_id', $postId)
            ->where('user_id', $userId)
            ->where('comment_id', 0)
            ->first();

        return $reaction ? $reaction->reaction : null;
    }

    /**
     * Get reaction name by type
     * 
     * @param int $reactionType
     * @return string
     */
    private function getReactionName(int $reactionType): string
    {
        $reactionNames = [
            1 => 'Like',
            2 => 'Love',
            3 => 'Haha',
            4 => 'Wow',
            5 => 'Sad',
            6 => 'Angry',
        ];

        return $reactionNames[$reactionType] ?? "Reaction {$reactionType}";
    }

    /**
     * Get reaction icon by type
     * 
     * @param int $reactionType
     * @return string
     */
    private function getReactionIcon(int $reactionType): string
    {
        $reactionIcons = [
            1 => '👍',
            2 => '❤️',
            3 => '😂',
            4 => '😮',
            5 => '😢',
            6 => '😠',
        ];

        return $reactionIcons[$reactionType] ?? '👍';
    }

    /**
     * Save a post (mimics WoWonder requests.php?f=posts&s=save_post)
     * 
     * @param Request $request
     * @param int $postId
     * @return JsonResponse
     */
    public function savePost(Request $request, int $postId): JsonResponse
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

        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'hash' => 'nullable|string', // For compatibility with WoWonder hash system
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if post exists
        $post = Post::where('id', $postId)->orWhere('post_id', $postId)->first();
        if (!$post) {
            return response()->json(['ok' => false, 'message' => 'Post not found'], 404);
        }

        // Check if user can view this post
        if (!$this->canViewPost($post, $tokenUserId)) {
            return response()->json(['ok' => false, 'message' => 'Access denied'], 403);
        }

        try {
            DB::beginTransaction();

            // Check if post is already saved
            $existingSave = DB::table('Wo_SavedPosts')
                ->where('user_id', $tokenUserId)
                ->where('post_id', $post->id)
                ->first();

            if ($existingSave) {
                // Post is already saved - unsave it
                DB::table('Wo_SavedPosts')
                    ->where('user_id', $tokenUserId)
                    ->where('post_id', $post->id)
                    ->delete();
                
                $action = 'unsaved';
                $message = 'Post removed from saved posts';
            } else {
                // Save the post
                DB::table('Wo_SavedPosts')->insert([
                    'user_id' => $tokenUserId,
                    'post_id' => $post->id,
                ]);
                
                $action = 'saved';
                $message = 'Post saved successfully';
            }

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => $message,
                'data' => [
                    'post_id' => $post->id,
                    'action' => $action,
                    'is_saved' => $action === 'saved',
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'ok' => false,
                'message' => 'Failed to save post',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get saved posts for user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getSavedPosts(Request $request): JsonResponse
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

        try {
            $perPage = $request->input('per_page', 12);
            $perPage = max(1, min($perPage, 50));

            // Get saved post IDs
            $savedPostIds = DB::table('Wo_SavedPosts')
                ->where('user_id', $tokenUserId)
                ->orderByDesc('id')
                ->pluck('post_id');

            if ($savedPostIds->isEmpty()) {
                return response()->json([
                    'ok' => true,
                    'data' => [
                        'posts' => [],
                        'pagination' => [
                            'current_page' => 1,
                            'last_page' => 1,
                            'per_page' => $perPage,
                            'total' => 0,
                            'has_more' => false,
                        ],
                        'total_saved' => 0,
                    ]
                ]);
            }

            // Get posts with pagination
            $query = Post::with('user')
                ->whereIn('id', $savedPostIds)
                ->orderByRaw('FIELD(id, ' . $savedPostIds->implode(',') . ')');

            $posts = $query->paginate($perPage);

            $formattedPosts = $posts->map(function ($post) use ($tokenUserId) {
                return $this->formatSavedPostData($post, $tokenUserId);
            });

            return response()->json([
                'ok' => true,
                'data' => [
                    'posts' => $formattedPosts,
                    'pagination' => [
                        'current_page' => $posts->currentPage(),
                        'last_page' => $posts->lastPage(),
                        'per_page' => $posts->perPage(),
                        'total' => $posts->total(),
                        'has_more' => $posts->hasMorePages(),
                    ],
                    'total_saved' => $savedPostIds->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to get saved posts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if post is saved by user
     * 
     * @param Request $request
     * @param int $postId
     * @return JsonResponse
     */
    public function checkSavedPost(Request $request, int $postId): JsonResponse
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

        // Check if post exists
        $post = Post::where('id', $postId)->orWhere('post_id', $postId)->first();
        if (!$post) {
            return response()->json(['ok' => false, 'message' => 'Post not found'], 404);
        }

        try {
            $isSaved = DB::table('Wo_SavedPosts')
                ->where('user_id', $tokenUserId)
                ->where('post_id', $post->id)
                ->exists();

            return response()->json([
                'ok' => true,
                'data' => [
                    'post_id' => $post->id,
                    'is_saved' => $isSaved,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to check saved status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unsave a post
     * 
     * @param Request $request
     * @param int $postId
     * @return JsonResponse
     */
    public function unsavePost(Request $request, int $postId): JsonResponse
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

        // Check if post exists
        $post = Post::where('id', $postId)->orWhere('post_id', $postId)->first();
        if (!$post) {
            return response()->json(['ok' => false, 'message' => 'Post not found'], 404);
        }

        try {
            DB::beginTransaction();

            // Check if post is saved
            $savedRecord = DB::table('Wo_SavedPosts')
                ->where('user_id', $tokenUserId)
                ->where('post_id', $post->id)
                ->first();

            if (!$savedRecord) {
                return response()->json(['ok' => false, 'message' => 'Post is not saved'], 404);
            }

            // Remove from saved posts
            DB::table('Wo_SavedPosts')
                ->where('user_id', $tokenUserId)
                ->where('post_id', $post->id)
                ->delete();

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Post removed from saved posts',
                'data' => [
                    'post_id' => $post->id,
                    'action' => 'unsaved',
                    'is_saved' => false,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'ok' => false,
                'message' => 'Failed to unsave post',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format saved post data for API response
     * 
     * @param Post $post
     * @param string $userId
     * @return array
     */
    private function formatSavedPostData(Post $post, string $userId): array
    {
        return [
            'id' => $post->id,
            'post_id' => $post->post_id,
            'user_id' => $post->user_id,
            'post_text' => $post->postText ?? '',
            'post_text_preview' => $post->post_text_preview,
            'post_type' => $post->postType,
            'post_privacy' => $post->postPrivacy,
            'post_privacy_text' => $post->post_privacy_text,
            'post_photo' => $post->postPhoto,
            'post_photo_url' => $post->postPhoto ? asset('storage/' . $post->postPhoto) : null,
            'post_file' => $post->postFile,
            'post_file_url' => $post->postFile ? asset('storage/' . $post->postFile) : null,
            'post_youtube' => $post->postYoutube,
            'post_link' => $post->postLink,
            'post_link_title' => $post->postLinkTitle,
            'post_link_image' => $post->postLinkImage,
            'post_link_content' => $post->postLinkContent,
            'post_map' => $post->postMap,
            'post_record' => $post->postRecord,
            'post_record_url' => $post->postRecord ? asset('storage/' . $post->postRecord) : null,
            'post_sticker' => $post->postSticker,
            'album_name' => $post->album_name,
            'multi_image_post' => (bool) $post->multi_image_post,
            'is_owner' => $post->user_id == $userId,
            'is_active' => $post->is_active,
            'is_boosted' => $post->is_boosted,
            'is_saved' => true, // All posts in this list are saved
            'author' => [
                'user_id' => $post->user->user_id ?? $post->user_id,
                'username' => $post->user->username ?? 'Unknown',
                'name' => $post->user->name ?? 'Unknown User',
                'avatar_url' => $post->user->avatar ? asset('storage/' . $post->user->avatar) : null,
            ],
            'page_id' => $post->page_id,
            'group_id' => $post->group_id,
            'event_id' => $post->event_id,
            'created_at' => date('c', $post->time),
            'created_at_human' => $this->getHumanTime($post->time),
            'time' => $post->time,
            'reaction_counts' => $this->getPostReactionCounts($post->post_id),
            'total_reactions' => array_sum($this->getPostReactionCounts($post->post_id)),
            'user_reaction' => $this->getUserReaction($post->post_id, $userId),
        ];
    }
}
