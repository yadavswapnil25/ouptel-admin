<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Get original post
        $originalPost = DB::table('Wo_Posts')->where('id', $postId)->first();
        if (!$originalPost) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 4,
                    'error_text' => 'Post not found'
                ]
            ], 404);
        }

        try {
            DB::beginTransaction();

            $newPostId = null;
            $recipientUserId = null;

            if ($s === 'timeline' || $s === 'user') {
                // Share on user timeline
                $userId = $typeId > 0 ? $typeId : $tokenUserId;
                
                // Check if user exists
                $user = DB::table('Wo_Users')->where('user_id', $userId)->first();
                if (!$user) {
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

                // Create shared post
                $newPostId = $this->sharePost($postId, $userId, 'user', $text);

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
                $newPostId = $this->sharePost($postId, $typeId, 'page', $text);

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
                $newPostId = $this->sharePost($postId, $typeId, 'group', $text);

            } else {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 10,
                        'error_text' => 'Invalid share type. Use: timeline, page, or group'
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
                    'post_id' => $postId,
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
                            'post_id' => $postId,
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

        // Create new post as a share
        $newPostId = DB::table('Wo_Posts')->insertGetId([
            'user_id' => $userId,
            'postText' => !empty($text) ? $text : $originalPost->postText,
            'postPrivacy' => $originalPost->postPrivacy ?? '0',
            'postType' => $originalPost->postType ?? 'post',
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

        return $newPostId;
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

