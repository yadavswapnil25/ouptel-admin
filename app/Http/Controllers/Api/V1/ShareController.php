<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ChatService;
use App\Services\FriendActivityNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ShareController extends Controller
{
    /**
     * Share post on timeline/page/group (mimics old API: requests.php?f=share_post_on)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function sharePostOn(Request $request): JsonResponse
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

        // Get parameters (matching old API structure)
        $s = $request->input('s', $request->query('s', 'timeline')); // timeline, page, group
        $typeId = (int) ($request->input('type_id', $request->query('type_id', 0))); // user_id, page_id, or group_id (0 = current user)
        $postId = (int) ($request->input('post_id', $request->query('post_id', 0)));
        $text = trim((string) ($request->input('text', $request->query('text', ''))));

        // Validate post_id
        if (empty($postId) || $postId <= 0) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'post_id can not be empty'
                ]
            ], 400);
        }

        // Resolve post: clients send either DB `id` or public `post_id` (same as PostController)
        $originalPost = DB::table('Wo_Posts')
            ->where('id', $postId)
            ->orWhere('post_id', $postId)
            ->first();
        if (!$originalPost) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Post not found'
                ]
            ], 404);
        }

        $internalPostId = (int) $originalPost->id;
        $notificationPostId = (int) ($originalPost->post_id ?? $internalPostId);

        try {
            DB::beginTransaction();

            $newPostId = null;
            $recipientUserId = null;

            if ($s === 'send') {
                DB::rollBack();

                return $this->sharePostWithUser(
                    (int) $tokenUserId,
                    $typeId,
                    $originalPost,
                    $internalPostId,
                    $notificationPostId,
                    $text
                );
            }

            if ($s === 'timeline' || $s === 'user') {
                // Sharing to another person's profile sends it to them (friend or not).
                // Never create a post as another user.
                if ($typeId > 0 && $typeId !== (int) $tokenUserId) {
                    DB::rollBack();

                    return $this->sharePostWithUser(
                        (int) $tokenUserId,
                        $typeId,
                        $originalPost,
                        $internalPostId,
                        $notificationPostId,
                        $text
                    );
                }

                // Share on own timeline
                $userId = $tokenUserId;
                
                // Check if user exists
                $user = DB::table('Wo_Users')->where('user_id', $userId)->first();
                if (!$user) {
                    DB::rollBack();
                    return response()->json([
                        'api_status' => 400,
                        'errors' => [
                            'error_id' => 5,
                            'error_text' => 'User not found'
                        ]
                    ], 404);
                }

                // Get original post owner
                $originalPostOwner = $originalPost->user_id;
                if (empty($originalPostOwner) && !empty($originalPost->page_id)) {
                    $page = DB::table('Wo_Pages')->where('page_id', $originalPost->page_id)->first();
                    $originalPostOwner = $page->user_id ?? null;
                }
                $recipientUserId = $originalPostOwner;

                // Create shared post (internal DB id; sharePost loads Wo_Posts by id)
                $newPostId = $this->sharePost($internalPostId, $userId, 'user', $text);

            } elseif ($s === 'page') {
                // Share on page
                if (empty($typeId) || $typeId <= 0) {
                    return response()->json([
                        'api_status' => 400,
                        'errors' => [
                            'error_id' => 6,
                            'error_text' => 'page_id can not be empty'
                        ]
                    ], 400);
                }

                // Check if page exists and user is page owner/admin
                $page = DB::table('Wo_Pages')->where('page_id', $typeId)->first();
                if (!$page) {
                    return response()->json([
                        'api_status' => 400,
                        'errors' => [
                            'error_id' => 6,
                            'error_text' => 'Page not found'
                        ]
                    ], 404);
                }

                // Check if user is page owner
                if ($page->user_id != $tokenUserId) {
                    // Check if user is page admin
                    $isAdmin = DB::table('Wo_PageAdmins')
                        ->where('page_id', $typeId)
                        ->where('user_id', $tokenUserId)
                        ->exists();
                    
                    if (!$isAdmin) {
                        return response()->json([
                            'api_status' => 400,
                            'errors' => [
                                'error_id' => 7,
                                'error_text' => 'You do not have permission to share on this page'
                            ]
                        ], 403);
                    }
                }

                // Get original post owner
                $originalPostOwner = $originalPost->user_id;
                if (empty($originalPostOwner)) {
                    $originalPostOwner = $page->user_id;
                }
                $recipientUserId = $originalPostOwner;

                // Create shared post
                $newPostId = $this->sharePost($internalPostId, $typeId, 'page', $text);

            } elseif ($s === 'group') {
                // Share on group
                if (empty($typeId) || $typeId <= 0) {
                    return response()->json([
                        'api_status' => 400,
                        'errors' => [
                            'error_id' => 8,
                            'error_text' => 'group_id can not be empty'
                        ]
                    ], 400);
                }

                // Check if group exists and user is group admin
                $group = DB::table('Wo_Groups')->where('id', $typeId)->first();
                if (!$group) {
                    return response()->json([
                        'api_status' => 400,
                        'errors' => [
                            'error_id' => 8,
                            'error_text' => 'Group not found'
                        ]
                    ], 404);
                }

                // Check if user is group creator or admin
                if ($group->user_id != $tokenUserId) {
                    // Check if user is group admin
                    $isAdmin = DB::table('Wo_GroupAdmins')
                        ->where('group_id', $typeId)
                        ->where('user_id', $tokenUserId)
                        ->exists();
                    
                    if (!$isAdmin) {
                        return response()->json([
                            'api_status' => 400,
                            'errors' => [
                                'error_id' => 9,
                                'error_text' => 'You do not have permission to share on this group'
                            ]
                        ], 403);
                    }
                }

                // Get original post owner
                $originalPostOwner = $originalPost->user_id;
                if (empty($originalPostOwner)) {
                    $originalPostOwner = $group->user_id;
                }
                $recipientUserId = $originalPostOwner;

                // Create shared post
                $newPostId = $this->sharePost($internalPostId, $typeId, 'group', $text);

            } elseif ($s === 'story' || $s === 'vibe') {
                // Facebook-style: share friend's post into your vibe/story
                $storyId = $this->sharePostToStory($originalPost, (int) $tokenUserId, $text);
                if (!$storyId) {
                    throw new \Exception('Failed to share post to story');
                }

                DB::commit();

                FriendActivityNotificationService::notifyFriendsOfNewStory((int) $storyId, (string) $tokenUserId);

                $originalOwnerId = (int) ($originalPost->user_id ?? 0);
                if ($originalOwnerId > 0 && $originalOwnerId !== (int) $tokenUserId && Schema::hasTable('Wo_Notifications')) {
                    try {
                        $notif = [
                            'recipient_id' => $originalOwnerId,
                            'notifier_id' => (int) $tokenUserId,
                            'post_id' => $notificationPostId,
                            'type' => 'shared_your_post',
                            'type2' => 'story',
                            'text' => 'shared your post to their vibe',
                            'url' => '/profile/' . $tokenUserId,
                            'time' => time(),
                            'seen' => 0,
                        ];
                        foreach (['page_id', 'group_id', 'event_id'] as $col) {
                            if (Schema::hasColumn('Wo_Notifications', $col)) {
                                $notif[$col] = 0;
                            }
                        }
                        DB::table('Wo_Notifications')->insert($notif);
                    } catch (\Throwable $e) {
                        Log::warning('Share-to-story owner notification failed: ' . $e->getMessage());
                    }
                }

                return response()->json([
                    'api_status' => 200,
                    'ok' => true,
                    'message' => 'Shared to your vibe.',
                    'story_id' => $storyId,
                ]);

            } else {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 10,
                        'error_text' => 'Invalid share type. Use: timeline, send, page, group, or story'
                    ]
                ], 400);
            }

            if (!$newPostId) {
                throw new \Exception('Failed to create shared post');
            }

            // Create notifications
            if ($recipientUserId && $recipientUserId != $tokenUserId) {
                // Notify original post owner
                DB::table('Wo_Notifications')->insert([
                    'recipient_id' => $recipientUserId,
                    'notifier_id' => $tokenUserId,
                    'post_id' => $notificationPostId,
                    'type' => 'shared_your_post',
                    'url' => 'index.php?link1=post&id=' . $newPostId,
                    'time' => time(),
                    'seen' => 0,
                ]);

                // If sharing on timeline, also notify timeline owner
                if ($s === 'timeline' || $s === 'user') {
                    $timelineUserId = $typeId > 0 ? $typeId : $tokenUserId;
                    if ($timelineUserId != $tokenUserId && $timelineUserId != $recipientUserId) {
                        DB::table('Wo_Notifications')->insert([
                            'recipient_id' => $timelineUserId,
                            'notifier_id' => $tokenUserId,
                            'post_id' => $notificationPostId,
                            'type' => 'shared_a_post_in_timeline',
                            'url' => 'index.php?link1=post&id=' . $newPostId,
                            'time' => time(),
                            'seen' => 0,
                        ]);
                    }
                }
            }

            DB::commit();

            // Get the new post data
            $newPost = $this->getPostData($newPostId, $tokenUserId);

            return response()->json([
                'api_status' => 200,
                'data' => $newPost
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 11,
                    'error_text' => 'Failed to share post: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Send a post to any user (friend or not). Respects blocks only.
     */
    private function sharePostWithUser(
        int $senderId,
        int $recipientId,
        object $originalPost,
        int $internalPostId,
        int $notificationPostId,
        string $text
    ): JsonResponse {
        if ($recipientId <= 0) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'Please choose a user to share with',
                ],
            ], 400);
        }

        if ($recipientId === $senderId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'You cannot share a post with yourself',
                ],
            ], 400);
        }

        $recipient = DB::table('Wo_Users')->where('user_id', $recipientId)->first();
        if (!$recipient || in_array((string) ($recipient->active ?? '1'), ['0', '2'], true)) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 5,
                    'error_text' => 'User not found',
                ],
            ], 404);
        }

        if (Schema::hasTable('Wo_Blocks')) {
            $blocked = DB::table('Wo_Blocks')
                ->where(function ($query) use ($senderId, $recipientId) {
                    $query->where('blocker', $senderId)->where('blocked', $recipientId);
                })
                ->orWhere(function ($query) use ($senderId, $recipientId) {
                    $query->where('blocker', $recipientId)->where('blocked', $senderId);
                })
                ->exists();
            if ($blocked) {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 7,
                        'error_text' => 'You cannot share a post with this user',
                    ],
                ], 403);
            }
        }

        $frontendBase = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
        $postUrl = $frontendBase . '/post/' . $notificationPostId;
        $recipientName = trim(implode(' ', array_filter([
            $recipient->first_name ?? null,
            $recipient->last_name ?? null,
        ])));
        if ($recipientName === '') {
            $recipientName = trim((string) ($recipient->name ?? $recipient->username ?? 'user'));
        }
        $sentViaChat = false;
        $conversationId = null;

        try {
            $chat = app(ChatService::class);
            $conversation = $chat->findOrCreateDirectConversation($senderId, $recipientId);
            $caption = trim($text);
            $messageText = ($caption !== '' ? $caption . "\n" : "Shared a post with you\n") . $postUrl;
            $chat->sendTextMessage($conversation, $senderId, $recipientId, $messageText);
            $sentViaChat = true;
            $conversationId = (int) $conversation->id;
        } catch (\Throwable $e) {
            Log::warning('Share-to-user chat failed: ' . $e->getMessage(), [
                'sender_id' => $senderId,
                'recipient_id' => $recipientId,
                'post_id' => $internalPostId,
            ]);
        }

        $this->insertShareNotification([
            'recipient_id' => $recipientId,
            'notifier_id' => $senderId,
            'post_id' => $notificationPostId,
            'type' => 'shared_post_with_you',
            'text' => $text,
            'url' => '/post/' . $notificationPostId,
        ]);

        $originalOwnerId = (int) ($originalPost->user_id ?? 0);
        if ($originalOwnerId > 0 && $originalOwnerId !== $senderId && $originalOwnerId !== $recipientId) {
            $this->insertShareNotification([
                'recipient_id' => $originalOwnerId,
                'notifier_id' => $senderId,
                'post_id' => $notificationPostId,
                'type' => 'shared_your_post',
                'text' => '',
                'url' => '/post/' . $notificationPostId,
            ]);
        }

        if (Schema::hasColumn('Wo_Posts', 'postShare')) {
            DB::table('Wo_Posts')->where('id', $internalPostId)->increment('postShare');
        }

        return response()->json([
            'api_status' => 200,
            'ok' => true,
            'message' => 'Shared with ' . $recipientName,
            'sent_via_chat' => $sentViaChat,
            'conversation_id' => $conversationId,
            'user_id' => $recipientId,
        ]);
    }

    private function insertShareNotification(array $payload): void
    {
        if (!Schema::hasTable('Wo_Notifications')) {
            return;
        }

        try {
            $row = [
                'recipient_id' => (int) $payload['recipient_id'],
                'notifier_id' => (int) $payload['notifier_id'],
                'type' => $payload['type'],
                'url' => $payload['url'] ?? '',
                'time' => time(),
                'seen' => 0,
            ];
            foreach (['post_id', 'type2', 'text', 'page_id', 'group_id', 'event_id'] as $col) {
                if (!array_key_exists($col, $payload) || !Schema::hasColumn('Wo_Notifications', $col)) {
                    continue;
                }
                $row[$col] = $payload[$col];
            }
            foreach (['post_id', 'page_id', 'group_id', 'event_id'] as $requiredNumeric) {
                if (Schema::hasColumn('Wo_Notifications', $requiredNumeric) && !array_key_exists($requiredNumeric, $row)) {
                    $row[$requiredNumeric] = 0;
                }
            }
            DB::table('Wo_Notifications')->insert($row);
        } catch (\Throwable $e) {
            Log::warning('Share notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Share a post
     * 
     * @param int $postId
     * @param int $targetId
     * @param string $targetType
     * @param string $text
     * @return int|null
     */
    private function sharePost(int $postId, int $targetId, string $targetType, string $text = ''): ?int
    {
        // Get original post
        $originalPost = DB::table('Wo_Posts')->where('id', $postId)->first();
        if (!$originalPost) {
            return null;
        }

        // Determine user_id based on target type
        $userId = null;
        $pageId = 0;
        $groupId = 0;

        if ($targetType === 'user') {
            $userId = $targetId;
        } elseif ($targetType === 'page') {
            $page = DB::table('Wo_Pages')->where('page_id', $targetId)->first();
            $userId = $page->user_id ?? null;
            $pageId = $targetId;
        } elseif ($targetType === 'group') {
            $group = DB::table('Wo_Groups')->where('id', $targetId)->first();
            $userId = $group->user_id ?? null;
            $groupId = $targetId;
        }

        if (!$userId) {
            return null;
        }

        // Create new post as a share — never keep profile media post types on shares
        // (those labels belong only to the original cover/avatar update posts).
        $originalType = strtolower(trim((string) ($originalPost->postType ?? '')));
        $sharePostType = in_array($originalType, ['profile_cover_picture', 'profile_picture', 'answer'], true)
            ? 'post'
            : (($originalPost->postType ?? '') !== '' ? $originalPost->postType : 'post');

        $newPostId = DB::table('Wo_Posts')->insertGetId([
            'user_id' => $userId,
            'postText' => !empty($text) ? $text : $originalPost->postText,
            'postPrivacy' => $originalPost->postPrivacy ?? '0',
            'postType' => $sharePostType,
            'parent_id' => $postId, // Reference to original post
            'page_id' => $pageId,
            'group_id' => $groupId,
            'event_id' => $originalPost->event_id ?? 0,
            'postLink' => $originalPost->postLink ?? '',
            'postLinkTitle' => $originalPost->postLinkTitle ?? '',
            'postLinkImage' => $originalPost->postLinkImage ?? '',
            'postLinkContent' => $originalPost->postLinkContent ?? '',
            'postYoutube' => $originalPost->postYoutube ?? '',
            'postVimeo' => $originalPost->postVimeo ?? '',
            'postDailymotion' => $originalPost->postDailymotion ?? '',
            'postFacebook' => $originalPost->postFacebook ?? '',
            'postVine' => $originalPost->postVine ?? '',
            'postSoundCloud' => $originalPost->postSoundCloud ?? '',
            'postPlaytube' => $originalPost->postPlaytube ?? '',
            'postDeepsound' => $originalPost->postDeepsound ?? '',
            'postMap' => $originalPost->postMap ?? '',
            'postFeeling' => $originalPost->postFeeling ?? '',
            'postListening' => $originalPost->postListening ?? '',
            'postTraveling' => $originalPost->postTraveling ?? '',
            'postWatching' => $originalPost->postWatching ?? '',
            'postPlaying' => $originalPost->postPlaying ?? '',
            'postFile' => $originalPost->postFile ?? '',
            'postFileThumb' => $originalPost->postFileThumb ?? '',
            'postRecord' => $originalPost->postRecord ?? '',
            'postSticker' => $originalPost->postSticker ?? '',
            'postPhoto' => $originalPost->postPhoto ?? '',
            'time' => time(),
            'active' => 1,
        ]);

        // Align post_id with PK so reactions/comments attach to this share, not post_id=0
        if ($newPostId && Schema::hasColumn('Wo_Posts', 'post_id')) {
            DB::table('Wo_Posts')->where('id', $newPostId)->update(['post_id' => $newPostId]);
        }

        // Ensure shared_from is set when the column exists (some DBs omit it from insert defaults)
        if ($newPostId && Schema::hasColumn('Wo_Posts', 'shared_from')) {
            DB::table('Wo_Posts')->where('id', $newPostId)->update(['shared_from' => $postId]);
        }

        // Bump share count on the original post
        if (Schema::hasColumn('Wo_Posts', 'postShare')) {
            DB::table('Wo_Posts')->where('id', $postId)->increment('postShare');
        }

        return $newPostId;
    }

    /**
     * Share a post into the current user's vibe/story (Facebook-style).
     */
    private function sharePostToStory(object $originalPost, int $userId, string $text = ''): ?int
    {
        if (!Schema::hasTable('Wo_UserStory')) {
            throw new \Exception('Stories are not available');
        }

        $media = $this->resolvePostShareMedia($originalPost);
        if (!$media) {
            $media = $this->createShareStoryCardImage($originalPost, $text);
        }
        if (!$media || empty($media['filename'])) {
            return null;
        }

        $overlayText = trim($text);
        $storyDescription = '';
        $textX = null;
        $textY = null;
        if ($overlayText !== '') {
            $textX = 50.0;
            $textY = 78.0;
            $storyDescription = json_encode([
                '__ouptel_overlay' => 1,
                't' => $overlayText,
                'x' => $textX,
                'y' => $textY,
            ], JSON_UNESCAPED_UNICODE);
        } elseif (!empty($originalPost->postText)) {
            // Keep a short caption for text shares without overlay positioning
            $storyDescription = mb_substr(trim((string) $originalPost->postText), 0, 280);
        }

        $storyInsert = [
            'user_id' => $userId,
            'posted' => time(),
            'expire' => time() + (60 * 60 * 24),
            'title' => '',
            'description' => $storyDescription,
        ];
        if (Schema::hasColumn('Wo_UserStory', 'text_x')) {
            $storyInsert['text_x'] = $textX;
        }
        if (Schema::hasColumn('Wo_UserStory', 'text_y')) {
            $storyInsert['text_y'] = $textY;
        }

        $storyId = (int) DB::table('Wo_UserStory')->insertGetId($storyInsert);
        if ($storyId <= 0) {
            return null;
        }

        $mediaTable = 'Wo_UserStoryMedia';
        if (!Schema::hasTable($mediaTable)) {
            if (Schema::hasTable('Wo_StoryMedia')) {
                $mediaTable = 'Wo_StoryMedia';
            } else {
                throw new \Exception('Story media table does not exist');
            }
        }

        $mediaInsert = [
            'story_id' => $storyId,
            'type' => $media['type'],
            'filename' => $media['filename'],
        ];
        if (Schema::hasColumn($mediaTable, 'expire')) {
            $mediaInsert['expire'] = time() + (60 * 60 * 24);
        }
        DB::table($mediaTable)->insert($mediaInsert);

        if (Schema::hasColumn('Wo_UserStory', 'thumbnail')) {
            DB::table('Wo_UserStory')->where('id', $storyId)->update([
                'thumbnail' => $media['thumbnail'] ?? $media['filename'],
            ]);
        }

        if (Schema::hasColumn('Wo_Posts', 'postShare')) {
            DB::table('Wo_Posts')->where('id', (int) $originalPost->id)->increment('postShare');
        }

        return $storyId;
    }

    /**
     * Resolve image/video path from a post for story sharing.
     *
     * @return array{type:string,filename:string,thumbnail:?string}|null
     */
    private function resolvePostShareMedia(object $post): ?array
    {
        $candidates = [];

        $postPhoto = trim((string) ($post->postPhoto ?? ''));
        if ($postPhoto !== '') {
            $candidates[] = ['path' => $postPhoto, 'prefer' => 'image'];
        }

        $postFile = trim((string) ($post->postFile ?? ''));
        if ($postFile !== '') {
            $candidates[] = ['path' => $postFile, 'prefer' => 'video'];
        }

        $postFileThumb = trim((string) ($post->postFileThumb ?? ''));
        if ($postFileThumb !== '') {
            $candidates[] = ['path' => $postFileThumb, 'prefer' => 'image'];
        }

        if (Schema::hasTable('Wo_Albums_Media')) {
            $albumImage = DB::table('Wo_Albums_Media')
                ->where('post_id', (int) $post->id)
                ->orderBy('id')
                ->value('image');
            if (!empty($albumImage)) {
                $candidates[] = ['path' => (string) $albumImage, 'prefer' => 'image'];
            }
        }

        foreach ($candidates as $candidate) {
            $copied = $this->copyMediaToStoriesPath($candidate['path'], $candidate['prefer']);
            if ($copied) {
                return $copied;
            }
        }

        return null;
    }

    /**
     * Copy local storage media (or download remote image URL) into stories/.
     *
     * @return array{type:string,filename:string,thumbnail:?string}|null
     */
    private function copyMediaToStoriesPath(string $sourcePath, string $prefer = 'image'): ?array
    {
        $sourcePath = trim($sourcePath);
        if ($sourcePath === '') {
            return null;
        }

        $binary = null;
        $extension = 'jpg';

        if (filter_var($sourcePath, FILTER_VALIDATE_URL)) {
            try {
                $binary = @file_get_contents($sourcePath);
            } catch (\Throwable $e) {
                $binary = null;
            }
            $pathInfo = pathinfo(parse_url($sourcePath, PHP_URL_PATH) ?? '');
            $extension = strtolower((string) ($pathInfo['extension'] ?? 'jpg'));
        } else {
            $relative = ltrim(preg_replace('#^storage/#', '', $sourcePath), '/');
            if (Storage::disk('public')->exists($relative)) {
                $binary = Storage::disk('public')->get($relative);
                $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION) ?: 'jpg');
            } elseif (is_file($sourcePath)) {
                $binary = @file_get_contents($sourcePath);
                $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg');
            } elseif (is_file(public_path($relative))) {
                $binary = @file_get_contents(public_path($relative));
                $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION) ?: 'jpg');
            } elseif (is_file(storage_path('app/public/' . $relative))) {
                $binary = @file_get_contents(storage_path('app/public/' . $relative));
                $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION) ?: 'jpg');
            }
        }

        if ($binary === null || $binary === false || $binary === '') {
            return null;
        }

        $videoExts = ['mp4', 'm4v', 'mov', 'webm', 'avi', 'mpg', 'mpeg'];
        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        if (!in_array($extension, array_merge($videoExts, $imageExts), true)) {
            $extension = $prefer === 'video' ? 'mp4' : 'jpg';
        }

        $type = in_array($extension, $videoExts, true) ? 'video' : 'image';
        $filename = 'stories/' . date('Y/m') . '/' . uniqid('share_', true) . '_' . time() . '.' . $extension;
        Storage::disk('public')->put($filename, $binary);

        return [
            'type' => $type,
            'filename' => $filename,
            'thumbnail' => $type === 'image' ? $filename : null,
        ];
    }

    /**
     * Generate a simple story card for text-only posts.
     *
     * @return array{type:string,filename:string,thumbnail:?string}|null
     */
    private function createShareStoryCardImage(object $post, string $userText = ''): ?array
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $width = 720;
        $height = 1280;
        $img = imagecreatetruecolor($width, $height);
        if (!$img) {
            return null;
        }

        $bgTop = imagecolorallocate($img, 30, 58, 138);
        $bgBottom = imagecolorallocate($img, 78, 110, 242);
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / max(1, $height - 1);
            $r = (int) (30 + (78 - 30) * $ratio);
            $g = (int) (58 + (110 - 58) * $ratio);
            $b = (int) (138 + (242 - 138) * $ratio);
            $color = imagecolorallocate($img, $r, $g, $b);
            imageline($img, 0, $y, $width, $y, $color);
        }
        unset($bgTop, $bgBottom);

        $white = imagecolorallocate($img, 255, 255, 255);
        $muted = imagecolorallocate($img, 220, 230, 255);

        $author = DB::table('Wo_Users')->where('user_id', (int) ($post->user_id ?? 0))->first();
        $authorName = trim((string) ($author->name ?? $author->username ?? 'Ouptel'));
        $body = trim($userText !== '' ? $userText : (string) ($post->postText ?? ''));
        if ($body === '') {
            $body = 'Shared a post';
        }
        $body = mb_substr($body, 0, 220);

        $lines = $this->wrapGdText($body, 34);
        $y = 420;
        imagestring($img, 5, 40, 120, $this->gdSafeText('Shared from ' . $authorName, 40), $muted);
        foreach (array_slice($lines, 0, 12) as $line) {
            imagestring($img, 5, 40, $y, $this->gdSafeText($line, 42), $white);
            $y += 36;
        }
        imagestring($img, 3, 40, $height - 80, 'ouptel', $muted);

        ob_start();
        imagejpeg($img, null, 88);
        $binary = ob_get_clean();
        imagedestroy($img);

        if ($binary === false || $binary === '') {
            return null;
        }

        $filename = 'stories/' . date('Y/m') . '/' . uniqid('share_card_', true) . '_' . time() . '.jpg';
        Storage::disk('public')->put($filename, $binary);

        return [
            'type' => 'image',
            'filename' => $filename,
            'thumbnail' => $filename,
        ];
    }

    private function wrapGdText(string $text, int $maxChars): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $trial = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($trial) > $maxChars) {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = $word;
            } else {
                $current = $trial;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        return $lines ?: [''];
    }

    private function gdSafeText(string $text, int $maxLen = 60): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii === false || $ascii === null) {
            $ascii = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
        }
        $ascii = trim((string) $ascii);
        if ($ascii === '') {
            $ascii = 'Shared post';
        }
        return mb_substr($ascii, 0, $maxLen);
    }

    /**
     * Get post data formatted for API response
     * 
     * @param int $postId
     * @param string $tokenUserId
     * @return array
     */
    private function getPostData(int $postId, string $tokenUserId): array
    {
        $post = DB::table('Wo_Posts')->where('id', $postId)->first();
        if (!$post) {
            return [];
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
                    'avatar' => $user->avatar ?? '',
                    'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                    'verified' => (bool) ($user->verified ?? false),
                ];
            }
        }

        // Get shared post info if exists
        $sharedInfo = null;
        if ($post->parent_id) {
            $sharedPost = DB::table('Wo_Posts')->where('id', $post->parent_id)->first();
            if ($sharedPost) {
                $sharedUser = DB::table('Wo_Users')->where('user_id', $sharedPost->user_id)->first();
                $sharedInfo = [
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

        return [
            'id' => $post->id,
            'post_id' => $post->id,
            'user_id' => $post->user_id,
            'postText' => $post->postText ?? '',
            'postType' => $post->postType ?? 'post',
            'postPrivacy' => $post->postPrivacy ?? '0',
            'parent_id' => $post->parent_id ?? null,
            'page_id' => $post->page_id ?? 0,
            'group_id' => $post->group_id ?? 0,
            'event_id' => $post->event_id ?? 0,
            'time' => $post->time ?? time(),
            'publisher' => $publisher,
            'user_data' => $publisher,
            'shared_info' => $sharedInfo,
            'postFile' => $post->postFile ? asset('storage/' . $post->postFile) : null,
            'postFileThumb' => $post->postFileThumb ? asset('storage/' . $post->postFileThumb) : null,
            'postLink' => $post->postLink ?? '',
            'postYoutube' => $post->postYoutube ?? '',
            'postPlaytube' => $post->postPlaytube ?? '',
        ];
    }
}

