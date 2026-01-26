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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PostController extends Controller
{
    /**
     * Create a new post (mimics WoWonder requests.php?f=posts&s=insert_new_post)
     * 
     * OPTIMIZED: Unified endpoint for all post types
     * Use 'type' parameter to optimize: 'regular', 'gif', 'feeling', or 'colored'
     * 
     * Examples:
     * - Regular post: POST /posts (or POST /posts?type=regular)
     * - GIF post: POST /posts?type=gif (with postGif parameter)
     * - Feeling post: POST /posts?type=feeling (with feeling parameter)
     * - Colored post: POST /posts?type=colored (with color_id parameter)
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

        // Get post type (gif, feeling, colored, or regular)
        $postCreationType = $request->input('type', 'regular'); // type: regular, gif, feeling, colored
        
        // Validate request parameters based on type
        $validationRules = [
            'postText' => 'nullable|string|max:5000',
            'postPrivacy' => 'required|in:0,1,2,3,4', // 0=Public, 1=Friends, 2=Only Me, 3=Custom, 4=Group
            'postType' => 'nullable|in:text,photo,video,file,link,location,audio,sticker,album,poll,blog,forum,product,job,offer,funding,gif',
            'type' => 'nullable|in:regular,gif,feeling,colored', // Post creation type
            'feeling' => 'nullable|string|max:100', // Feeling parameter for type=feeling
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
            'postVideo' => 'nullable|file|mimes:mp4,avi,mov,wmv,flv,webm,mkv|max:102400', // 100MB max for videos
            'postRecord' => 'nullable|file|mimes:mp3,wav,ogg|max:51200', // 50MB max
            'postSticker' => 'nullable|string|max:500',
            'postGif' => 'nullable|url|max:2000', // GIF URL from Giphy or similar service
        ];

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get post creation type (regular, gif, feeling, colored)
        $postCreationType = $request->input('type', 'regular');

        // Handle type-specific processing
        if ($postCreationType === 'feeling') {
            // Validate and convert 'feeling' parameter to 'postFeeling'
            $feeling = $request->input('feeling');
            if (empty($feeling)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'feeling parameter is required when type=feeling. Use GET /api/v1/feelings to see available feelings.'
                ], 422);
            }
            
            $validFeelings = [
                'happy', 'loved', 'sad', 'so_sad', 'angry', 'confused', 'smirk',
                'broke', 'expressionless', 'cool', 'funny', 'tired', 'lovely',
                'blessed', 'shocked', 'sleepy', 'pretty', 'bored'
            ];
            if (!in_array($feeling, $validFeelings)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid feeling. Use GET /api/v1/feelings to see available feelings.'
                ], 422);
            }
            $request->merge(['postFeeling' => $feeling]);
        } elseif ($postCreationType === 'gif') {
            // Validate GIF URL
            $postGif = $request->input('postGif');
            if (empty($postGif)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'postGif parameter is required when type=gif. Please provide a valid GIF URL from Giphy, Tenor, or similar service.'
                ], 422);
            }
            
            $isGifUrl = (
                strpos($postGif, '.gif') !== false || 
                strpos($postGif, 'giphy.com') !== false || 
                strpos($postGif, 'tenor.com') !== false ||
                strpos($postGif, 'media.giphy.com') !== false ||
                strpos($postGif, 'media.tenor.com') !== false
            );
            if (!$isGifUrl) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid GIF URL. Please provide a valid GIF URL from Giphy, Tenor, or similar service.'
                ], 422);
            }
            $request->merge(['postType' => 'gif']);
        } elseif ($postCreationType === 'colored') {
            // Validate color_id for colored posts
            $colorId = $request->input('color_id', 0);
            if (empty($colorId) || $colorId <= 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'color_id parameter is required when type=colored. Use GET /api/v1/posts/colored to see available colors.'
                ], 422);
            }
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
        $postVideo = $request->file('postVideo');
        $postRecord = $request->file('postRecord');
        $postYoutube = $request->input('postYoutube', '');
        $postVimeo = $request->input('postVimeo', '');
        $postFacebook = $request->input('postFacebook', '');
        $postPlaytube = $request->input('postPlaytube', '');
        $postLink = $request->input('postLink', '');
        $postMap = $request->input('postMap', '');
        $postSticker = $request->input('postSticker', '');
        $postGif = $request->input('postGif', '');

        // At least one content field must be provided
        if (empty($postText) && !$postPhoto && !$postFile && !$postVideo && !$postRecord && 
            empty($postYoutube) && empty($postVimeo) && empty($postFacebook) && empty($postPlaytube) && 
            empty($postLink) && empty($postMap) && empty($postSticker) && empty($postGif)) {
            return response()->json([
                'ok' => false,
                'message' => 'At least one content field must be provided (text, photo, file, video, link, location, sticker, or gif)'
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
            $isVideoFile = false;

            if ($postPhoto) {
                $postPhotoPath = $this->handleFileUpload($postPhoto, 'posts/photos', 'photo');
            }

            // Handle video files - store in postFile (matching old WoWonder behavior)
            if ($postVideo) {
                $postFilePath = $this->handleFileUpload($postVideo, 'posts/videos', 'video');
                $isVideoFile = true;
            } elseif ($postFile) {
                $postFilePath = $this->handleFileUpload($postFile, 'posts/files', 'file');
            }

            if ($postRecord) {
                $postRecordPath = $this->handleFileUpload($postRecord, 'posts/audio', 'audio');
            }

            // Determine post type
            $postType = $this->determinePostType($request, $postPhotoPath, $postFilePath, $postRecordPath, $isVideoFile);

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
                'postFileName' => ($postVideo ? $postVideo : $postFile) ? (($postVideo ? $postVideo : $postFile)->getClientOriginalName()) : '',
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
                'postPhoto' => $postGif ? $postGif : $postPhotoPath, // Store GIF URL or photo path in postPhoto field
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

            // Handle colored post if color_id is provided
            $colorData = null;
            $colorId = $request->input('color_id', 0);
            if ($colorId > 0 && Schema::hasTable('Wo_Colored_Posts')) {
                try {
                    $coloredPost = DB::table('Wo_Colored_Posts')
                        ->where('id', $colorId)
                        ->first();
                    
                    if ($coloredPost) {
                        $colorData = [
                            'color_id' => $coloredPost->id,
                            'color_1' => $coloredPost->color_1 ?? '',
                            'color_2' => $coloredPost->color_2 ?? '',
                            'text_color' => $coloredPost->text_color ?? '',
                            'image' => $coloredPost->image ?? '',
                            'image_url' => !empty($coloredPost->image) ? asset('storage/' . $coloredPost->image) : null,
                        ];
                    }
                } catch (\Exception $e) {
                    // If query fails, continue without color data
                    Log::warning('Failed to fetch colored post data: ' . $e->getMessage());
                }
            }

            // Handle album creation if multiple images
            if ($request->hasFile('album_images') && $request->input('album_name')) {
                $this->handleAlbumCreation($post->id, $request->file('album_images'), $request->input('album_name'));
            }

            // Note: User post count update skipped - posts column may not exist in Wo_Users table

            // Send notifications to followers/friends
            $this->sendPostNotifications($post, $tokenUserId);

            DB::commit();

            $responseData = [
                'post_id' => $post->id,
                'post_id_original' => $post->post_id,
                'post_url' => $post->post_url,
                'post_type' => $post->postType,
                'post_privacy' => $post->postPrivacy,
                'created_at' => date('c', $post->time),
                'created_at_human' => $this->getHumanTime($post->time),
                'user_id' => $post->user_id,
                'creation_type' => $postCreationType, // Type used for creation: regular, gif, feeling, colored
                'author' => [
                    'user_id' => $user->user_id,
                    'username' => $user->username,
                    'name' => $user->name,
                    'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                ]
            ];

            // Add color data if available
            if ($colorData) {
                $responseData['color'] = $colorData;
            }

            // Add feeling data if available
            if (!empty($post->postFeeling)) {
                $feelingIcons = [
                    'happy' => 'smile',
                    'loved' => 'heart-eyes',
                    'sad' => 'disappointed',
                    'so_sad' => 'sob',
                    'angry' => 'angry',
                    'confused' => 'confused',
                    'smirk' => 'smirk',
                    'broke' => 'broken-heart',
                    'expressionless' => 'expressionless',
                    'cool' => 'sunglasses',
                    'funny' => 'joy',
                    'tired' => 'tired-face',
                    'lovely' => 'heart',
                    'blessed' => 'innocent',
                    'shocked' => 'scream',
                    'sleepy' => 'sleeping',
                    'pretty' => 'relaxed',
                    'bored' => 'unamused'
                ];
                
                $feelingLabels = [
                    'happy' => 'Happy',
                    'loved' => 'Loved',
                    'sad' => 'Sad',
                    'so_sad' => 'So Sad',
                    'angry' => 'Angry',
                    'confused' => 'Confused',
                    'smirk' => 'Smirk',
                    'broke' => 'Broke',
                    'expressionless' => 'Expressionless',
                    'cool' => 'Cool',
                    'funny' => 'Funny',
                    'tired' => 'Tired',
                    'lovely' => 'Lovely',
                    'blessed' => 'Blessed',
                    'shocked' => 'Shocked',
                    'sleepy' => 'Sleepy',
                    'pretty' => 'Pretty',
                    'bored' => 'Bored'
                ];

                $feelingKey = $post->postFeeling;
                $responseData['feeling'] = [
                    'key' => $feelingKey,
                    'label' => $feelingLabels[$feelingKey] ?? ucfirst($feelingKey),
                    'icon' => $feelingIcons[$feelingKey] ?? 'smile',
                ];
            }

            // Add GIF data if available
            if ($postType === 'gif' && !empty($post->postPhoto) && filter_var($post->postPhoto, FILTER_VALIDATE_URL)) {
                $responseData['gif'] = [
                    'url' => $post->postPhoto,
                    'type' => 'gif'
                ];
            }

            return response()->json([
                'ok' => true,
                'message' => 'Post created successfully',
                'data' => $responseData
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Clean up uploaded files if post creation failed
            if ($postPhotoPath) Storage::delete($postPhotoPath);
            if ($postFilePath) Storage::delete($postFilePath);
            // Video files are stored in postFile, so they're handled by postFilePath cleanup above
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
     * @param bool $isVideoFile
     * @return string
     */
    private function determinePostType(Request $request, ?string $postPhotoPath, ?string $postFilePath, ?string $postRecordPath, bool $isVideoFile = false): string
    {
        // Check for GIF first (GIF URLs are stored in postPhotoPath)
        if ($request->input('postGif')) return 'gif';
        if ($postPhotoPath && filter_var($postPhotoPath, FILTER_VALIDATE_URL) && (strpos($postPhotoPath, '.gif') !== false || strpos($postPhotoPath, 'giphy.com') !== false || strpos($postPhotoPath, 'tenor.com') !== false)) {
            return 'gif';
        }
        if ($postPhotoPath) return 'photo';
        // Check if postFile is a video (matching old WoWonder behavior)
        if ($isVideoFile && $postFilePath) return 'video';
        if ($postRecordPath) return 'audio';
        if ($postFilePath) return 'file';
        if ($request->input('postYoutube') || $request->input('postVimeo') || $request->input('postFacebook') || $request->input('postPlaytube')) return 'video';
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
     * Get posts with optional filtering by activity type
     * 
     * Endpoint: GET /api/v1/posts?type=playing|travelling|watching|listening|feeling
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getPosts(Request $request): JsonResponse
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

        // Get filter parameters
        $type = $request->input('type'); // playing, travelling, watching, listening, feeling
        $perPage = (int) ($request->input('per_page', 20));
        $perPage = max(1, min($perPage, 100));
        $page = (int) ($request->input('page', 1));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        // Build query
        $query = Post::where('active', 1)
            ->with('user')
            ->orderBy('time', 'desc');

        // Filter by activity type
        if ($type) {
            $validTypes = ['playing', 'travelling', 'watching', 'listening', 'feeling'];
            if (!in_array($type, $validTypes)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid type. Valid types are: ' . implode(', ', $validTypes)
                ], 400);
            }

            // Map type to database column
            $columnMap = [
                'playing' => 'postPlaying',
                'travelling' => 'postTraveling',
                'watching' => 'postWatching',
                'listening' => 'postListening',
                'feeling' => 'postFeeling',
            ];

            $column = $columnMap[$type];
            $query->whereNotNull($column)
                  ->where($column, '!=', '');
        }

        // Get total count for pagination
        $total = $query->count();

        // Get paginated posts
        $posts = $query->offset($offset)
                      ->limit($perPage)
                      ->get();

        // Format posts
        $formattedPosts = [];
        foreach ($posts as $post) {
            $formattedPosts[] = $this->formatPostData($post, $tokenUserId);
        }

        $lastPage = (int) ceil($total / $perPage);

        return response()->json([
            'ok' => true,
            'data' => $formattedPosts,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'has_more' => $page < $lastPage,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? min($offset + $perPage, $total) : 0,
            ],
            'filter' => $type ? ['type' => $type] : null,
        ]);
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
        // Get activity data
        $activityData = $this->getActivityData($post);

        return [
            'id' => $post->id,
            'post_id' => $post->post_id,
            'user_id' => $post->user_id,
            'post_text' => $post->postText ?? '',
            'post_type' => $post->postType,
            'post_privacy' => $post->postPrivacy,
            'post_privacy_text' => $post->post_privacy_text,
            'post_photo' => $post->postPhoto,
            'post_photo_url' => $this->getPostPhotoUrl($post),
            'post_file' => $post->postFile,
            'post_file_url' => $post->postFile ? asset('storage/' . $post->postFile) : null,
            'post_video' => ($post->postType === 'video' && !empty($post->postFile)) ? $post->postFile : null,
            'post_video_url' => ($post->postType === 'video' && !empty($post->postFile)) ? asset('storage/' . $post->postFile) : null,
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
            // Activity fields
            'post_playing' => $post->postPlaying ?? null,
            'post_travelling' => $post->postTraveling ?? null,
            'post_watching' => $post->postWatching ?? null,
            'post_listening' => $post->postListening ?? null,
            'post_feeling' => $post->postFeeling ?? null,
            'activity' => $activityData,
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
     * Get activity data for a post
     * 
     * @param Post $post
     * @return array|null
     */
    private function getActivityData(Post $post): ?array
    {
        $activity = null;

        if (!empty($post->postPlaying)) {
            $activity = [
                'type' => 'playing',
                'label' => 'Playing',
                'value' => $post->postPlaying,
                'text' => "is playing {$post->postPlaying}",
            ];
        } elseif (!empty($post->postTraveling)) {
            $activity = [
                'type' => 'travelling',
                'label' => 'Travelling',
                'value' => $post->postTraveling,
                'text' => "is travelling to {$post->postTraveling}",
            ];
        } elseif (!empty($post->postWatching)) {
            $activity = [
                'type' => 'watching',
                'label' => 'Watching',
                'value' => $post->postWatching,
                'text' => "is watching {$post->postWatching}",
            ];
        } elseif (!empty($post->postListening)) {
            $activity = [
                'type' => 'listening',
                'label' => 'Listening',
                'value' => $post->postListening,
                'text' => "is listening to {$post->postListening}",
            ];
        } elseif (!empty($post->postFeeling)) {
            $feelingData = $this->getFeelingData($post->postFeeling);
            $activity = [
                'type' => 'feeling',
                'label' => 'Feeling',
                'value' => $post->postFeeling,
                'text' => "is feeling {$feelingData['name']}",
                'icon' => $feelingData['icon'] ?? null,
            ];
        }

        return $activity;
    }

    /**
     * Get feeling data (icon and name)
     * 
     * @param string $feeling
     * @return array
     */
    private function getFeelingData(string $feeling): array
    {
        $feelings = [
            'happy' => ['name' => 'Happy', 'icon' => '😊'],
            'loved' => ['name' => 'Loved', 'icon' => '❤️'],
            'sad' => ['name' => 'Sad', 'icon' => '😢'],
            'so_sad' => ['name' => 'Very Sad', 'icon' => '😭'],
            'angry' => ['name' => 'Angry', 'icon' => '😠'],
            'confused' => ['name' => 'Confused', 'icon' => '😕'],
            'smirk' => ['name' => 'Smirk', 'icon' => '😏'],
            'broke' => ['name' => 'Broke', 'icon' => '💔'],
            'expressionless' => ['name' => 'Expressionless', 'icon' => '😑'],
            'cool' => ['name' => 'Cool', 'icon' => '😎'],
            'funny' => ['name' => 'Funny', 'icon' => '😄'],
            'tired' => ['name' => 'Tired', 'icon' => '😴'],
            'lovely' => ['name' => 'Lovely', 'icon' => '🥰'],
            'blessed' => ['name' => 'Blessed', 'icon' => '🙏'],
            'shocked' => ['name' => 'Shocked', 'icon' => '😱'],
            'sleepy' => ['name' => 'Sleepy', 'icon' => '😪'],
            'pretty' => ['name' => 'Pretty', 'icon' => '😍'],
            'bored' => ['name' => 'Bored', 'icon' => '😐'],
        ];

        return $feelings[$feeling] ?? ['name' => ucfirst(str_replace('_', ' ', $feeling)), 'icon' => null];
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
            'post_photo_url' => $this->getPostPhotoUrl($post),
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

    /**
     * Get post data for opening in new tab (mimics old API: get-post-data.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getPostData(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        // Validate request
        if (empty($request->input('post_id'))) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 3,
                    'error_text' => 'post_id (POST) is missing'
                ]
            ], 400);
        }

        if (empty($request->input('fetch'))) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 3,
                    'error_text' => 'fetch (POST) is missing'
                ]
            ], 400);
        }

        $postId = (int) $request->input('post_id');
        $fetch = $request->input('fetch');
        $addView = (int) ($request->input('add_view', 0));

        // Get post
        $post = DB::table('Wo_Posts')->where('id', $postId)->first();
        if (!$post) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Post not found'
                ]
            ], 404);
        }

        // Add view if requested
        if ($addView == 1) {
            // Increment video views if it's a video post
            if (in_array($post->postType ?? '', ['video', 'postYoutube', 'postVimeo', 'postPlaytube'])) {
                DB::table('Wo_Posts')
                    ->where('id', $postId)
                    ->increment('videoViews', 1);
            }
        }

        // Parse fetch parameter (comma-separated list)
        $fetchArray = explode(',', $fetch);
        $fetchData = [];
        foreach ($fetchArray as $value) {
            $fetchData[trim($value)] = trim($value);
        }

        $responseData = ['api_status' => 200];

        // Get post data
        if (!empty($fetchData['post_data'])) {
            $postData = $this->getFormattedPostData($postId, $tokenUserId);
            if ($postData) {
                $responseData['post_data'] = $postData;
            }
        }

        // Get post comments
        if (!empty($fetchData['post_comments'])) {
            $comments = $this->getPostComments($postId, $tokenUserId);
            $responseData['post_comments'] = $comments;
        }

        // Get post liked users
        if (!empty($fetchData['post_liked_users'])) {
            $likedUsers = $this->getPostLikedUsers($postId);
            $responseData['post_liked_users'] = $likedUsers;
        }

        // Get post wondered users
        if (!empty($fetchData['post_wondered_users'])) {
            $wonderedUsers = $this->getPostWonderedUsers($postId);
            $responseData['post_wondered_users'] = $wonderedUsers;
        }

        return response()->json($responseData);
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
     * Get formatted post data
     * 
     * @param int $postId
     * @param string $tokenUserId
     * @return array|null
     */
    private function getFormattedPostData(int $postId, string $tokenUserId): ?array
    {
        $post = DB::table('Wo_Posts')->where('id', $postId)->first();
        if (!$post) {
            return null;
        }

        // Get publisher/user data
        $publisher = null;
        if ($post->user_id) {
            $user = DB::table('Wo_Users')->where('user_id', $post->user_id)->first();
            if ($user) {
                $publisher = [
                    'user_id' => $user->user_id,
                    'username' => $user->username ?? 'Unknown',
                    'name' => $user->name ?? $user->username ?? 'Unknown User',
                    'email' => $user->email ?? '',
                    'avatar' => $user->avatar ?? '',
                    'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'cover' => $user->cover ?? '',
                    'cover_url' => $user->cover ? asset('storage/' . $user->cover) : null,
                    'verified' => (bool) ($user->verified ?? false),
                    'is_following' => $this->isFollowing($tokenUserId, $user->user_id),
                ];
            }
        }

        // Get page data if exists
        $page = null;
        if ($post->page_id) {
            $pageData = DB::table('Wo_Pages')->where('page_id', $post->page_id)->first();
            if ($pageData) {
                $page = [
                    'page_id' => $pageData->page_id,
                    'page_name' => $pageData->page_name ?? '',
                    'page_title' => $pageData->page_title ?? '',
                    'avatar' => $pageData->avatar ?? '',
                    'avatar_url' => $pageData->avatar ? asset('storage/' . $pageData->avatar) : null,
                    'verified' => (bool) ($pageData->verified ?? false),
                ];
            }
        }

        // Get group data if exists
        $group = null;
        if ($post->group_id) {
            $groupData = DB::table('Wo_Groups')->where('id', $post->group_id)->first();
            if ($groupData) {
                $group = [
                    'id' => $groupData->id,
                    'group_name' => $groupData->group_name ?? '',
                    'avatar' => $groupData->avatar ?? '',
                    'avatar_url' => $groupData->avatar ? asset('storage/' . $groupData->avatar) : null,
                ];
            }
        }

        // Get shared post info if exists
        $sharedFrom = null;
        if ($post->parent_id) {
            $sharedPost = DB::table('Wo_Posts')->where('id', $post->parent_id)->first();
            if ($sharedPost) {
                $sharedUser = DB::table('Wo_Users')->where('user_id', $sharedPost->user_id)->first();
                $sharedFrom = [
                    'id' => $sharedPost->id,
                    'postText' => $sharedPost->postText ?? '',
                    'postType' => $sharedPost->postType ?? 'post',
                    'publisher' => $sharedUser ? [
                        'user_id' => $sharedUser->user_id,
                        'username' => $sharedUser->username ?? 'Unknown',
                        'name' => $sharedUser->name ?? $sharedUser->username ?? 'Unknown User',
                        'avatar_url' => $sharedUser->avatar ? asset('storage/' . $sharedUser->avatar) : null,
                    ] : null,
                ];
            }
        }

        // Get blog data if exists
        $blog = null;
        if ($post->blog_id) {
            $blogData = DB::table('Wo_Blogs')->where('id', $post->blog_id)->first();
            if ($blogData) {
                $blogAuthor = DB::table('Wo_Users')->where('user_id', $blogData->user_id)->first();
                $blog = [
                    'id' => $blogData->id,
                    'title' => $blogData->title ?? '',
                    'content' => $blogData->content ?? '',
                    'thumbnail' => $blogData->thumbnail ?? '',
                    'author' => $blogAuthor ? [
                        'user_id' => $blogAuthor->user_id,
                        'username' => $blogAuthor->username ?? 'Unknown',
                        'name' => $blogAuthor->name ?? $blogAuthor->username ?? 'Unknown User',
                    ] : null,
                ];
            }
        }

        // Get event data if exists
        $event = null;
        if ($post->event_id) {
            $eventData = DB::table('Wo_Events')->where('id', $post->event_id)->first();
            if ($eventData) {
                $eventUser = DB::table('Wo_Users')->where('user_id', $eventData->user_id)->first();
                $event = [
                    'id' => $eventData->id,
                    'name' => $eventData->name ?? '',
                    'location' => $eventData->location ?? '',
                    'start_date' => $eventData->start_date ?? '',
                    'user_data' => $eventUser ? [
                        'user_id' => $eventUser->user_id,
                        'username' => $eventUser->username ?? 'Unknown',
                        'name' => $eventUser->name ?? $eventUser->username ?? 'Unknown User',
                    ] : null,
                ];
            }
        }

        // Get post ID for reactions (use post_id field if available, fallback to id)
        $postIdForReactions = $post->post_id ?? $post->id;
        
        // Get reaction counts (detailed breakdown by reaction type)
        $reactionCounts = $this->getPostReactionCounts($postIdForReactions);
        $totalReactions = array_sum($reactionCounts);
        
        // Get user's reaction
        $userReaction = $this->getUserReaction($postIdForReactions, $tokenUserId);
        
        // Check if user liked the post (has any reaction)
        $isLiked = $userReaction !== null;
        
        // Get comments count
        $postIdForComments = $post->post_id ?? $post->id;
        $commentsCount = $this->getPostCommentsCount($postIdForComments, $post);
        
        // Get shares count
        $sharesCount = (int) ($post->postShare ?? 0);
        
        // Get views count (for videos)
        $viewsCount = (int) ($post->videoViews ?? 0);

        return [
            'id' => $post->id,
            'post_id' => $post->post_id ?? $post->id,
            'user_id' => $post->user_id,
            'post_text' => $post->postText ?? '',
            'post_type' => $post->postType ?? 'post',
            'post_privacy' => $post->postPrivacy ?? '0',
            'post_privacy_text' => $this->getPostPrivacyText($post->postPrivacy ?? '0'),
            
            // Media content
            'post_photo' => $post->postPhoto ?? '',
            'post_photo_url' => $this->getPostPhotoUrl($post),
            'post_file' => $post->postFile ?? '',
            'post_file_url' => $post->postFile ? asset('storage/' . $post->postFile) : null,
            'post_file_thumb' => $post->postFileThumb ? asset('storage/' . $post->postFileThumb) : null,
            'post_video' => ($post->postType === 'video' && !empty($post->postFile)) ? $post->postFile : '',
            'post_video_url' => ($post->postType === 'video' && !empty($post->postFile)) ? asset('storage/' . $post->postFile) : null,
            'post_record' => $post->postRecord ?? '',
            'post_record_url' => $post->postRecord ? asset('storage/' . $post->postRecord) : null,
            'post_youtube' => $post->postYoutube ?? '',
            'post_vimeo' => $post->postVimeo ?? '',
            'post_playtube' => $post->postPlaytube ?? '',
            'post_dailymotion' => $post->postDailymotion ?? '',
            'post_facebook' => $post->postFacebook ?? '',
            'post_vine' => $post->postVine ?? '',
            'post_soundcloud' => $post->postSoundCloud ?? '',
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
            
            // Engagement metrics (matching new-feed format)
            'reactions_count' => $totalReactions,
            'comments_count' => $commentsCount,
            'shares_count' => $sharesCount,
            'views_count' => $viewsCount,
            
            // Reaction details (matching new-feed format)
            'reaction_counts' => $reactionCounts,
            'total_reactions' => $totalReactions,
            'user_reaction' => $userReaction,
            'is_liked' => $isLiked ? 1 : 0,
            
            // User interaction
            'is_owner' => $post->user_id == $tokenUserId,
            'is_boosted' => (bool) ($post->boosted ?? false),
            'comments_disabled' => (bool) ($post->comments_status ?? false),
            
            // Author information
            'author' => $publisher ? [
                'user_id' => $publisher['user_id'],
                'username' => $publisher['username'],
                'name' => $publisher['name'],
                'avatar_url' => $publisher['avatar_url'],
                'verified' => $publisher['verified'] ?? false,
                'is_following' => $publisher['is_following'] ?? false,
            ] : null,
            
            // Context
            'page' => $page,
            'group' => $group,
            'blog' => $blog,
            'event' => $event,
            'shared_from' => $sharedFrom,
            'page_id' => $post->page_id ?? 0,
            'group_id' => $post->group_id ?? 0,
            'event_id' => $post->event_id ?? 0,
            'parent_id' => $post->parent_id ?? null,
            
            // Timestamps
            'created_at' => $post->time ? date('c', $post->time) : null,
            'created_at_human' => $post->time ? $this->getHumanTime($post->time) : null,
            'time' => $post->time ?? time(),
            
            // Legacy fields for backward compatibility
            'postText' => $post->postText ?? '',
            'postType' => $post->postType ?? 'post',
            'postPrivacy' => $post->postPrivacy ?? '0',
            'postFile' => $post->postFile ? asset('storage/' . $post->postFile) : null,
            'postFileThumb' => $post->postFileThumb ? asset('storage/' . $post->postFileThumb) : null,
            'postLink' => $post->postLink ?? '',
            'postLinkTitle' => $post->postLinkTitle ?? '',
            'postLinkImage' => $post->postLinkImage ?? '',
            'postLinkContent' => $post->postLinkContent ?? '',
            'postYoutube' => $post->postYoutube ?? '',
            'postPlaytube' => $post->postPlaytube ?? '',
            'postPhoto' => $this->getPostPhotoUrl($post),
            'publisher' => $publisher,
            'comments_status' => $post->comments_status ?? 1,
            'videoViews' => $viewsCount,
        ];
    }

    /**
     * Get post comments
     * 
     * @param int $postId
     * @param string $tokenUserId
     * @return array
     */
    private function getPostComments(int $postId, string $tokenUserId): array
    {
        $comments = DB::table('Wo_Comments')
            ->where('post_id', $postId)
            ->orderBy('id', 'asc')
            ->get();

        $formatted = [];
        foreach ($comments as $comment) {
            $commentUser = DB::table('Wo_Users')->where('user_id', $comment->user_id)->first();
            
            $formatted[] = [
                'id' => $comment->id,
                'user_id' => $comment->user_id,
                'post_id' => $comment->post_id,
                'text' => $comment->text ?? '',
                'time' => $comment->time ?? time(),
                'publisher' => $commentUser ? [
                    'user_id' => $commentUser->user_id,
                    'username' => $commentUser->username ?? 'Unknown',
                    'name' => $commentUser->name ?? $commentUser->username ?? 'Unknown User',
                    'avatar' => $commentUser->avatar ?? '',
                    'avatar_url' => $commentUser->avatar ? asset('storage/' . $commentUser->avatar) : null,
                    'verified' => (bool) ($commentUser->verified ?? false),
                ] : null,
            ];
        }

        return $formatted;
    }

    /**
     * Get post liked users
     * 
     * @param int $postId
     * @return array
     */
    private function getPostLikedUsers(int $postId): array
    {
        $likes = DB::table('Wo_Reactions')
            ->where('post_id', $postId)
            ->where('reaction', 1) // 1 = Like
            ->where('comment_id', 0)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $users = [];
        foreach ($likes as $like) {
            $user = DB::table('Wo_Users')->where('user_id', $like->user_id)->first();
            if ($user) {
                $users[] = [
                    'user_id' => $user->user_id,
                    'username' => $user->username ?? 'Unknown',
                    'name' => $user->name ?? $user->username ?? 'Unknown User',
                    'avatar' => $user->avatar ?? '',
                    'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'verified' => (bool) ($user->verified ?? false),
                ];
            }
        }

        return $users;
    }

    /**
     * Get post wondered users
     * 
     * @param int $postId
     * @return array
     */
    private function getPostWonderedUsers(int $postId): array
    {
        $wonders = DB::table('Wo_Reactions')
            ->where('post_id', $postId)
            ->where('reaction', 2) // 2 = Wonder/Dislike
            ->where('comment_id', 0)
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $users = [];
        foreach ($wonders as $wonder) {
            $user = DB::table('Wo_Users')->where('user_id', $wonder->user_id)->first();
            if ($user) {
                $users[] = [
                    'user_id' => $user->user_id,
                    'username' => $user->username ?? 'Unknown',
                    'name' => $user->name ?? $user->username ?? 'Unknown User',
                    'avatar' => $user->avatar ?? '',
                    'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'verified' => (bool) ($user->verified ?? false),
                ];
            }
        }

        return $users;
    }

    /**
     * Check if user is following another user
     * 
     * @param string $followerId
     * @param string $followingId
     * @return bool
     */
    private function isFollowing(string $followerId, string $followingId): bool
    {
        return DB::table('Wo_Followers')
            ->where('follower_id', $followerId)
            ->where('following_id', $followingId)
            ->exists();
    }

    /**
     * Disable or enable comments on a post (mimics old API: requests.php?f=posts&s=disable_comment)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function disableComment(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'post_id' => 'required|integer|min:1',
            'type' => 'nullable|integer|in:0,1', // 0 = disable, 1 = enable (optional - if not provided, toggles)
        ]);

        if ($validator->fails()) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'post_id must be numeric and greater than 0'
                ]
            ], 400);
        }

        $postId = (int) $request->input('post_id');
        $type = $request->has('type') ? (int) $request->input('type') : null;

        // Get post
        $post = DB::table('Wo_Posts')->where('id', $postId)->first();
        if (!$post) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Post not found'
                ]
            ], 404);
        }

        // Check if user is post owner
        $isOwner = false;
        
        // Check if user is the post owner
        if ($post->user_id == $tokenUserId) {
            $isOwner = true;
        }
        
        // Check if post belongs to a page and user is page owner/admin
        if (!$isOwner && !empty($post->page_id)) {
            $page = DB::table('Wo_Pages')->where('page_id', $post->page_id)->first();
            if ($page) {
                if ($page->user_id == $tokenUserId) {
                    $isOwner = true;
                } else {
                    $isAdmin = DB::table('Wo_PageAdmins')
                        ->where('page_id', $post->page_id)
                        ->where('user_id', $tokenUserId)
                        ->exists();
                    if ($isAdmin) {
                        $isOwner = true;
                    }
                }
            }
        }
        
        // Check if post belongs to a group and user is group creator/admin
        if (!$isOwner && !empty($post->group_id)) {
            $group = DB::table('Wo_Groups')->where('id', $post->group_id)->first();
            if ($group) {
                if ($group->user_id == $tokenUserId) {
                    $isOwner = true;
                } else {
                    $isAdmin = DB::table('Wo_GroupAdmins')
                        ->where('group_id', $post->group_id)
                        ->where('user_id', $tokenUserId)
                        ->exists();
                    if ($isAdmin) {
                        $isOwner = true;
                    }
                }
            }
        }

        if (!$isOwner) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 7,
                    'error_text' => 'You are not the post owner'
                ]
            ], 403);
        }

        // Get current comments status (default to 1 if not set)
        $currentStatus = $post->comments_status ?? 1;
        
        // Determine new status
        if ($type !== null) {
            // Explicitly set status based on type parameter
            $newStatus = $type;
            $action = $type == 1 ? 'post comments enabled' : 'post comments disabled';
        } else {
            // Toggle status (matching old API behavior)
            if ($currentStatus == 1) {
                $newStatus = 0;
                $action = 'post comments disabled';
            } else {
                $newStatus = 1;
                $action = 'post comments enabled';
            }
        }

        // Update post
        DB::table('Wo_Posts')
            ->where('id', $postId)
            ->update(['comments_status' => $newStatus]);

        return response()->json([
            'api_status' => 200,
            'action' => $action,
            'code' => $newStatus
        ]);
    }

    /**
     * Hide post (mimics old API: requests.php?f=posts&s=hide_post / hide_post.php)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function hidePost(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 1,
                    'error_text' => 'Unauthorized - No Bearer token provided'
                ]
            ], 401);
        }
        
        $token = substr($authHeader, 7);
        $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$tokenUserId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        // Validate request - accept both 'post_id' and 'post' parameters (matching old API)
        $postId = (int) ($request->input('post_id', $request->input('post', 0)));

        if (empty($postId) || $postId <= 0) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'post_id can not be empty'
                ]
            ], 400);
        }

        // Check if post exists
        $post = DB::table('Wo_Posts')->where('id', $postId)->first();
        if (!$post) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Post not found'
                ]
            ], 404);
        }

        try {
            // Check if post is already hidden
            $isHidden = DB::table('Wo_HiddenPosts')
                ->where('user_id', $tokenUserId)
                ->where('post_id', $postId)
                ->exists();

            if ($isHidden) {
                // Post is already hidden, return success
                return response()->json([
                    'api_status' => 200,
                    'message' => 'post hidden'
                ]);
            }

            // Hide the post
            // Check if table exists and what columns it has
            $insertData = [
                'user_id' => $tokenUserId,
                'post_id' => $postId,
            ];
            
            // Only add time column if it exists in the table
            if (Schema::hasColumn('Wo_HiddenPosts', 'time')) {
                $insertData['time'] = time();
            } elseif (Schema::hasColumn('Wo_HiddenPosts', 'created_at')) {
                $insertData['created_at'] = now();
            }
            
            DB::table('Wo_HiddenPosts')->insert($insertData);

            return response()->json([
                'api_status' => 200,
                'message' => 'post hidden'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'something went wrong'
                ]
            ], 500);
        }
    }

    /**
     * Get available colored posts (mimics WoWonder colored posts system)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getColoredPosts(Request $request): JsonResponse
    {
        // Auth is optional for this endpoint
        $tokenUserId = null;
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        }

        // Check if colored posts table exists
        if (!Schema::hasTable('Wo_Colored_Posts')) {
            return response()->json([
                'ok' => true,
                'data' => [
                    'colored_posts' => [],
                    'message' => 'Colored posts feature is not available'
                ]
            ]);
        }

        try {
            // Get all available colored posts
            $coloredPosts = DB::table('Wo_Colored_Posts')
                ->orderBy('id')
                ->get()
                ->map(function ($coloredPost) {
                    return [
                        'id' => $coloredPost->id,
                        'color_id' => $coloredPost->id, // Alias for clarity - use this in POST /api/v1/posts?type=colored
                        'color_1' => $coloredPost->color_1 ?? '',
                        'color_2' => $coloredPost->color_2 ?? '',
                        'text_color' => $coloredPost->text_color ?? '',
                        'image' => $coloredPost->image ?? '',
                        'image_url' => !empty($coloredPost->image) ? asset('storage/' . $coloredPost->image) : null,
                        'time' => $coloredPost->time ?? '',
                    ];
                });

            return response()->json([
                'ok' => true,
                'data' => [
                    'colored_posts' => $coloredPosts,
                    'total' => $coloredPosts->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch colored posts: ' . $e->getMessage());
            return response()->json([
                'ok' => false,
                'message' => 'Failed to fetch colored posts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a GIF post (mimics WoWonder GIF post creation)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createGifPost(Request $request): JsonResponse
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

        // Validate request
        $validated = $request->validate([
            'postText' => 'nullable|string|max:5000',
            'postPrivacy' => 'required|in:0,1,2,3,4', // 0=Public, 1=Friends, 2=Only Me, 3=Custom, 4=Group
            'postGif' => 'required|url|max:2000', // GIF URL from Giphy, Tenor, or similar service
            'page_id' => 'nullable|integer',
            'group_id' => 'nullable|integer',
            'event_id' => 'nullable|integer',
        ]);

        // Validate that the URL is a GIF URL (from Giphy, Tenor, etc.)
        $gifUrl = $validated['postGif'];
        $isGifUrl = (
            strpos($gifUrl, '.gif') !== false || 
            strpos($gifUrl, 'giphy.com') !== false || 
            strpos($gifUrl, 'tenor.com') !== false ||
            strpos($gifUrl, 'media.giphy.com') !== false ||
            strpos($gifUrl, 'media.tenor.com') !== false
        );

        if (!$isGifUrl) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid GIF URL. Please provide a valid GIF URL from Giphy, Tenor, or similar service.'
            ], 422);
        }

        // Merge GIF into request and forward to insertNewPost
        $request->merge([
            'postGif' => $gifUrl,
            'postType' => 'gif'
        ]);
        
        return $this->insertNewPost($request);
    }

    /**
     * Get post photo URL - handles both storage paths and external URLs (GIFs)
     * 
     * @param object|Post $post
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
