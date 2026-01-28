<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Page;
use App\Models\PageCategory;
use App\Models\PageSubCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PagesController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));

        $type = $request->query('type', 'my_pages'); // my_pages, suggested, liked

        // Resolve user via token when needed
        $authHeader = $request->header('Authorization');
        $tokenUserId = null;
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        }
        
        $query = Page::query()->where('active', '1');
        if ($type === 'my_pages') {
            if (!$tokenUserId) {
                return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
            }
            $query->where('user_id', (string) $tokenUserId);
        } elseif ($type === 'liked') {
            if (!$tokenUserId) {
                return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
            }
            $likedPageIds = DB::table('Wo_Pages_Likes')
                ->where('user_id', (string) $tokenUserId)
                ->pluck('page_id');
            $query->whereIn('page_id', $likedPageIds);
        } elseif ($type === 'suggested') {
            // Suggested pages: not owned by user and not liked by user
            if ($tokenUserId) {
                $likedPageIds = DB::table('Wo_Pages_Likes')
                    ->where('user_id', (string) $tokenUserId)
                    ->pluck('page_id')
                    ->toArray();
                $query->where('user_id', '!=', (string) $tokenUserId)
                      ->whereNotIn('page_id', $likedPageIds);
            }
        } else {
            // Default behavior: all active pages (optionally category filtered)
        }

        if ($request->filled('category')) {
            $query->where('page_category', $request->query('category'));
        }

        $paginator = $query->paginate($perPage);

        // Check if user wants to include posts
        $includePosts = $request->query('include_posts', false);
        $postsLimit = (int) ($request->query('posts_limit', 3)); // Default 3 recent posts
        $postsLimit = max(0, min($postsLimit, 10)); // Limit between 0-10

        $data = $paginator->getCollection()->map(function (Page $page) use ($tokenUserId, $includePosts, $postsLimit) {
            $pageData = [
                'page_id' => $page->page_id,
                'page_name' => $page->page_name,
                'page_title' => $page->page_title,
                'description' => $page->page_description,
                'category' => $page->page_category,
                'category_name' => $page->category_name,
                'verified' => $page->verified,
                'avatar_url' => $page->avatar,
                'cover_url' => \App\Helpers\ImageHelper::getCoverUrl($page->cover ?? ''),
                'website' => $page->website,
                'phone' => $page->phone,
                'address' => $page->address,
                'url' => $page->url,
                'owner' => [
                    'user_id' => optional($page->owner)->user_id,
                    'username' => optional($page->owner)->username,
                    'avatar_url' => optional($page->owner)->avatar_url,
                ],
            ];

            // Get posts count
            $postsCount = DB::table('Wo_Posts')
                ->where('page_id', $page->page_id)
                ->where('active', '1')
                ->count();
            
            $pageData['posts_count'] = $postsCount;

            // Include recent posts if requested
            if ($includePosts && $postsLimit > 0) {
                $recentPosts = DB::table('Wo_Posts')
                    ->where('page_id', $page->page_id)
                    ->where('active', '1')
                    ->orderByDesc('time')
                    ->limit($postsLimit)
                    ->get();

                $pageData['posts'] = $recentPosts->map(function ($post) use ($tokenUserId) {
                    return $this->formatPostForPage($post, $tokenUserId);
                })->toArray();
            }

            return $pageData;
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function meta(): JsonResponse
    {
        $categories = PageCategory::query()
            ->orderBy('id')
            ->get()
            ->map(fn (PageCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]);

        $categoryId = request()->query('category_id');
        $subsQuery = PageSubCategory::query();
        if (!empty($categoryId)) {
            $subsQuery->where('category_id', (int) $categoryId);
        }
        $subCategories = $subsQuery
            ->orderBy('id')
            ->get()
            ->map(fn (PageSubCategory $s) => [
                'id' => $s->id,
                'category_id' => $s->category_id,
                'name' => $s->name,
            ]);

        return response()->json([
            'ok' => true,
            'data' => [
                'categories' => $categories,
                'sub_categories' => $subCategories,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        // Validate required fields
        $errors = [];
        $pageName = $request->input('page_name');
        $pageTitle = $request->input('page_title');
        $pageCategory = $request->input('page_category', 1); // Default to 1 if empty
        $pageDescription = $request->input('page_description', '');

        // Validate page_name and page_title are not empty
        if (empty($pageName) || empty($pageTitle)) {
            $errors[] = 'Please check details';
        }

        if (empty($errors)) {
            // Check if page_name already exists (in pages, users, groups)
            $pageExists = Page::where('page_name', $pageName)->exists();
            $userExists = DB::table('Wo_Users')->where('username', $pageName)->exists();
            $groupExists = DB::table('Wo_Groups')->where('group_name', $pageName)->exists();
            
            if ($pageExists || $userExists || $groupExists) {
                $errors[] = 'Page name already exists';
            }

            // Check reserved site pages (common reserved routes)
            $reservedPages = ['home', 'index', 'login', 'register', 'logout', 'settings', 'profile', 'admin', 'api', 'search', 'explore', 'messages', 'notifications', 'friends', 'pages', 'groups', 'events', 'jobs', 'market', 'blog', 'forum'];
            if (in_array(strtolower($pageName), $reservedPages)) {
                $errors[] = 'Page name contains invalid characters';
            }

            // Validate page_name length (5-32 characters)
            if (strlen($pageName) < 5 || strlen($pageName) > 32) {
                $errors[] = 'Page name must be between 5 and 32 characters';
            }

            // Validate page_name format (only word characters: letters, numbers, underscore)
            if (!preg_match('/^[\w]+$/', $pageName)) {
                $errors[] = 'Page name contains invalid characters';
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'api_status' => 400,
                'errors' => $errors,
            ], 400);
        }

        // Create the page
        $page = new Page();
        $page->page_name = $pageName;
        $page->page_title = $pageTitle;
        $page->page_description = $pageDescription;
        $page->page_category = $pageCategory;
        $page->user_id = (string) $userId;
        $page->verified = false;
        $page->active = '1';
        $page->website = $request->input('website', '');
        $page->phone = $request->input('phone', '');
        $page->address = $request->input('address', '');
        $page->save();

        // Return response matching old API format
        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'api_version' => '1.0',
            'page_name' => $page->page_name,
            'page_id' => $page->page_id,
        ], 200);
    }

    /**
     * Update page (Edit page)
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
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
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 2,
                    'error_text' => 'Invalid token - Session not found'
                ]
            ], 401);
        }

        try {
            // Find the page
            $page = Page::where('page_id', $id)->first();

            if (!$page) {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 4,
                        'error_text' => 'Page not found'
                    ]
                ], 404);
            }

            // Check if user is the page owner
            if ($page->user_id != $userId) {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 7,
                        'error_text' => 'You are not the page owner'
                    ]
                ], 403);
            }

            // Validate and collect errors
            $errors = [];
            $updateData = [];

            // Update page_title if provided
            if ($request->has('page_title')) {
                $pageTitle = trim($request->input('page_title'));
                if (empty($pageTitle)) {
                    $errors[] = 'Page title cannot be empty';
                } else {
                    $updateData['page_title'] = $pageTitle;
                }
            }

            // Update page_description/about if provided
            if ($request->has('page_description')) {
                $updateData['page_description'] = $request->input('page_description');
            }
            if ($request->has('about')) {
                // Map 'about' to 'page_description' since Wo_Pages table doesn't have 'about' column
                $updateData['page_description'] = $request->input('about');
                // Don't try to update 'about' column as it doesn't exist in Wo_Pages table
            }

            // Update page_category if provided
            if ($request->has('page_category')) {
                $pageCategory = $request->input('page_category');
                if (is_numeric($pageCategory)) {
                    $updateData['page_category'] = (int) $pageCategory;
                }
            }

            // Update page_name if provided (with validation)
            if ($request->has('page_name')) {
                $pageName = trim($request->input('page_name'));
                
                if (empty($pageName)) {
                    $errors[] = 'Page name cannot be empty';
                } else {
                    // Only validate if page name is changing
                    if ($pageName !== $page->page_name) {
                        // Check if page_name already exists (in pages, users, groups)
                        $pageExists = Page::where('page_name', $pageName)
                            ->where('page_id', '!=', $id)
                            ->exists();
                        $userExists = DB::table('Wo_Users')->where('username', $pageName)->exists();
                        $groupExists = DB::table('Wo_Groups')->where('group_name', $pageName)->exists();
                        
                        if ($pageExists || $userExists || $groupExists) {
                            $errors[] = 'Page name already exists';
                        }

                        // Check reserved site pages
                        $reservedPages = ['home', 'index', 'login', 'register', 'logout', 'settings', 'profile', 'admin', 'api', 'search', 'explore', 'messages', 'notifications', 'friends', 'pages', 'groups', 'events', 'jobs', 'market', 'blog', 'forum'];
                        if (in_array(strtolower($pageName), $reservedPages)) {
                            $errors[] = 'Page name contains invalid characters';
                        }

                        // Validate page_name length (5-32 characters)
                        if (strlen($pageName) < 5 || strlen($pageName) > 32) {
                            $errors[] = 'Page name must be between 5 and 32 characters';
                        }

                        // Validate page_name format (only word characters: letters, numbers, underscore)
                        if (!preg_match('/^[\w]+$/', $pageName)) {
                            $errors[] = 'Page name contains invalid characters';
                        }

                        if (empty($errors)) {
                            $updateData['page_name'] = $pageName;
                        }
                    }
                }
            }

            // Update contact information
            if ($request->has('website')) {
                $updateData['website'] = $request->input('website');
            }
            if ($request->has('phone')) {
                $updateData['phone'] = $request->input('phone');
            }
            if ($request->has('address')) {
                $updateData['address'] = $request->input('address');
            }

            // Update page call-to-action settings using WoWonder's original columns
            // DB columns: call_action_type (int), call_action_type_url (string), users_post (int 0/1)
            // Accept both old parameter names and the new ones you are using
            if ($request->has('call_action_type') || $request->has('call_to_action')) {
                $callAction = $request->input('call_action_type', $request->input('call_to_action'));
                $updateData['call_action_type'] = is_numeric($callAction) ? (int) $callAction : 0;
            }

            if ($request->has('call_action_type_url') || $request->has('call_to_target_url')) {
                $updateData['call_action_type_url'] = $request->input('call_action_type_url', $request->input('call_to_target_url'));
            }

            if ($request->has('users_post') || $request->has('can_post')) {
                $canPost = $request->input('users_post', $request->input('can_post'));

                // Map string values like 'enable' / 'disable' to 1 / 0, otherwise cast to int
                if (is_string($canPost)) {
                    $normalized = strtolower($canPost);
                    if (in_array($normalized, ['enable', 'enabled', 'yes', 'true', '1'], true)) {
                        $updateData['users_post'] = 1;
                    } elseif (in_array($normalized, ['disable', 'disabled', 'no', 'false', '0'], true)) {
                        $updateData['users_post'] = 0;
                    }
                } elseif (is_numeric($canPost)) {
                    $updateData['users_post'] = (int) $canPost ? 1 : 0;
                }
            }

            // Handle avatar upload if provided
            if ($request->hasFile('avatar')) {
                $avatar = $request->file('avatar');
                if ($avatar->isValid()) {
                    // Validate image
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($avatar->getMimeType(), $allowedMimes)) {
                        $errors[] = 'Avatar must be a valid image (JPEG, PNG, GIF, or WebP)';
                    } elseif ($avatar->getSize() > 5 * 1024 * 1024) { // 5MB max
                        $errors[] = 'Avatar file size must be less than 5MB';
                    } else {
                        // Store avatar (you may want to use a storage service)
                        $avatarPath = $avatar->store('pages/avatars', 'public');
                        $updateData['avatar'] = $avatarPath;
                    }
                }
            }

            // Handle cover upload if provided
            if ($request->hasFile('cover')) {
                $cover = $request->file('cover');
                if ($cover->isValid()) {
                    // Validate image
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($cover->getMimeType(), $allowedMimes)) {
                        $errors[] = 'Cover must be a valid image (JPEG, PNG, GIF, or WebP)';
                    } elseif ($cover->getSize() > 10 * 1024 * 1024) { // 10MB max
                        $errors[] = 'Cover file size must be less than 10MB';
                    } else {
                        // Store cover (you may want to use a storage service)
                        $coverPath = $cover->store('pages/covers', 'public');
                        $updateData['cover'] = $coverPath;
                    }
                }
            }

            // Return errors if any
            if (!empty($errors)) {
                return response()->json([
                    'api_status' => 400,
                    'errors' => $errors
                ], 400);
            }

            // Update the page if there are changes
            if (!empty($updateData)) {
                // Remove 'about' from updateData if it exists (column doesn't exist in Wo_Pages table)
                // 'about' should be mapped to 'page_description' instead
                if (isset($updateData['about'])) {
                    unset($updateData['about']);
                }
                
                foreach ($updateData as $key => $value) {
                    $page->$key = $value;
                }
                $page->save();
            }

            // Return success response
            return response()->json([
                'api_status' => 200,
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => 'Page updated successfully',
                'data' => [
                    'page_id' => $page->page_id,
                    'page_name' => $page->page_name,
                    'page_title' => $page->page_title,
                    'page_description' => $page->page_description ?? '',
                    'about' => $page->page_description ?? '', // 'about' is mapped to page_description since column doesn't exist
                    'category' => $page->page_category ?? 0,
                    'website' => $page->website ?? '',
                    'phone' => $page->phone ?? '',
                    'address' => $page->address ?? '',
                    'avatar_url' => $page->avatar ? asset('storage/' . $page->avatar) : null,
                    'cover_url' => $page->cover ? asset('storage/' . $page->cover) : null,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 500,
                'errors' => [
                    'error_id' => 500,
                    'error_text' => 'Failed to update page: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    public function likePage(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        // Validate page_id
        $validated = $request->validate([
            'page_id' => ['required', 'integer'],
        ]);

        $pageId = $validated['page_id'];

        // Check if page exists
        $page = Page::find($pageId);
        if (!$page) {
            return response()->json([
                'ok' => false,
                'message' => 'Page not found',
            ], 404);
        }

        // Check if page is active
        if ($page->active != '1') {
            return response()->json([
                'ok' => false,
                'message' => 'Page is not active',
            ], 400);
        }

        // Check if user already liked the page
        $isLiked = DB::table('Wo_Pages_Likes')
            ->where('page_id', $pageId)
            ->where('user_id', $userId)
            ->exists();

        $likeStatus = 'invalid';

        if ($isLiked) {
            // Unlike the page - delete the like
            DB::table('Wo_Pages_Likes')
                ->where('page_id', $pageId)
                ->where('user_id', $userId)
                ->delete();
            $likeStatus = 'unliked';
        } else {
            // Like the page - register the like
            DB::table('Wo_Pages_Likes')->insert([
                'page_id' => $pageId,
                'user_id' => $userId,
                'time' => time(),
            ]);
            $likeStatus = 'liked';
        }

        return response()->json([
            'ok' => true,
            'like_status' => $likeStatus,
            'data' => [
                'like' => $likeStatus === 'unliked' ? 'unliked' : 'liked',
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        // Auth via Wo_AppsSessions
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $token = substr($authHeader, 7);
        $userId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        if (!$userId) {
            return response()->json(['ok' => false, 'message' => 'Invalid token'], 401);
        }

        // Validate required fields
        $validated = $request->validate([
            'page_id' => ['required', 'integer'],
            'password' => ['required', 'string'],
        ]);

        $pageId = $validated['page_id'];
        $password = $validated['password'];

        // Check if page exists
        $page = Page::find($pageId);
        if (!$page) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'Page not found',
                ],
            ], 400);
        }

        // Check if user is the page owner
        if ($page->user_id != $userId) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 7,
                    'error_text' => 'You are not the page owner',
                ],
            ], 400);
        }

        // Get user data to verify password
        $user = DB::table('Wo_Users')->where('user_id', $userId)->first();
        if (!$user) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 6,
                    'error_text' => 'User not found',
                ],
            ], 400);
        }

        // Verify password
        $passwordVerified = false;
        if (password_verify($password, $user->password)) {
            $passwordVerified = true;
        } else {
            // Check if password matches using old hash method (if applicable)
            // Some old systems might use different hashing
            if (hash_equals($user->password, md5($password)) || hash_equals($user->password, sha1($password))) {
                $passwordVerified = true;
            }
        }

        // Check page admin password if exists (for page-specific admin passwords)
        if (!$passwordVerified && DB::getSchemaBuilder()->hasTable('Wo_Pages_Admin')) {
            $pageAdmin = DB::table('Wo_Pages_Admin')
                ->where('page_id', $pageId)
                ->where('user_id', $userId)
                ->first();
            
            if ($pageAdmin && isset($pageAdmin->password)) {
                if (password_verify($password, $pageAdmin->password) || 
                    hash_equals($pageAdmin->password, md5($password)) || 
                    hash_equals($pageAdmin->password, sha1($password))) {
                    $passwordVerified = true;
                }
            }
        }

        if (!$passwordVerified) {
            return response()->json([
                'api_status' => 400,
                'errors' => [
                    'error_id' => 7,
                    'error_text' => 'Current password mismatch',
                ],
            ], 400);
        }

        // Delete related data first
        DB::table('Wo_Pages_Likes')->where('page_id', $pageId)->delete();
        
        // Delete page admins if table exists
        if (DB::getSchemaBuilder()->hasTable('Wo_Pages_Admin')) {
            DB::table('Wo_Pages_Admin')->where('page_id', $pageId)->delete();
        }

        // Delete page posts if table exists
        if (DB::getSchemaBuilder()->hasTable('Wo_Posts')) {
            DB::table('Wo_Posts')->where('page_id', $pageId)->delete();
        }

        // Delete the page
        $page->delete();

        return response()->json([
            'api_status' => 200,
            'message' => 'Page successfully deleted',
        ], 200);
    }

    /**
     * Get page by ID
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, $id): JsonResponse
    {
        // Optional auth - public pages can be viewed without auth
        $authHeader = $request->header('Authorization');
        $tokenUserId = null;
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        }

        try {
            // Find page by ID
            $page = Page::where('page_id', $id)->first();

            if (!$page) {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 4,
                        'error_text' => 'Page not found'
                    ]
                ], 404);
            }

            // Check if page is active (unless user is the owner)
            if ($page->active != '1') {
                // Allow owner to view their own inactive page
                if (!$tokenUserId || $page->user_id != $tokenUserId) {
                    return response()->json([
                        'api_status' => 400,
                        'errors' => [
                            'error_id' => 4,
                            'error_text' => 'Page not found'
                        ]
                    ], 404);
                }
            }

            // Get page owner data
            $owner = null;
            if ($page->user_id) {
                $ownerData = DB::table('Wo_Users')
                    ->where('user_id', $page->user_id)
                    ->first();
                
                if ($ownerData) {
                    $owner = [
                        'user_id' => $ownerData->user_id,
                        'username' => $ownerData->username ?? '',
                        'name' => $this->getUserName($ownerData),
                        'avatar' => $ownerData->avatar ?? '',
                        'avatar_url' => $ownerData->avatar ? asset('storage/' . $ownerData->avatar) : null,
                        'verified' => (bool) ($ownerData->verified ?? false),
                    ];
                }
            }

            // Get page likes count
            $likesCount = 0;
            $isLiked = false;
            if (DB::getSchemaBuilder()->hasTable('Wo_Pages_Likes')) {
                $likesCount = DB::table('Wo_Pages_Likes')
                    ->where('page_id', $id)
                    ->count();
                
                if ($tokenUserId) {
                    $isLiked = DB::table('Wo_Pages_Likes')
                        ->where('page_id', $id)
                        ->where('user_id', $tokenUserId)
                        ->exists();
                }
            }

            // Get page category name if available
            $categoryName = '';
            if ($page->page_category && DB::getSchemaBuilder()->hasTable('Wo_Page_Categories')) {
                $category = DB::table('Wo_Page_Categories')
                    ->where('id', $page->page_category)
                    ->first();
                if ($category) {
                    $categoryName = $category->name ?? '';
                }
            }

            // Get page posts count
            $postsCount = 0;
            if (DB::getSchemaBuilder()->hasTable('Wo_Posts')) {
                $postsCount = DB::table('Wo_Posts')
                    ->where('page_id', $id)
                    ->where('active', '1')
                    ->count();
            }

            // Format response
            $responseData = [
                'api_status' => 200,
                'data' => [
                    'page_id' => $page->page_id,
                    'page_name' => $page->page_name ?? '',
                    'page_title' => $page->page_title ?? $page->page_name ?? '',
                    'page_description' => $page->page_description ?? '',
                    'about' => $page->page_description ?? '', // 'about' is mapped to page_description since column doesn't exist
                    'category' => $page->page_category ?? 0,
                    'category_name' => $categoryName,
                    'verified' => (bool) ($page->verified ?? false),
                    'active' => $page->active ?? '1',
                    'avatar' => $page->avatar ?? '',
                    'avatar_url' => $page->avatar ? asset('storage/' . $page->avatar) : null,
                    'cover' => $page->cover ?? '',
                    'cover_url' => $page->cover ? asset('storage/' . $page->cover) : null,
                    'website' => $page->website ?? '',
                    'phone' => $page->phone ?? '',
                    'address' => $page->address ?? '',
                    // Expose legacy WoWonder columns under clearer API field names
                    'call_to_action' => $page->call_action_type ?? 0,
                    'call_to_target_url' => $page->call_action_type_url ?? '',
                    'can_post' => isset($page->users_post) ? (int) $page->users_post : 0,
                    'url' => $page->url ?? url('/page/' . ($page->page_name ?? '')),
                    'likes_count' => $likesCount,
                    'posts_count' => $postsCount,
                    'is_liked' => $isLiked,
                    'is_owner' => $tokenUserId && $page->user_id == $tokenUserId,
                    'owner' => $owner,
                    'created_at' => isset($page->created_at) ? date('c', strtotime($page->created_at)) : null,
                ]
            ];

            return response()->json($responseData);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 500,
                'errors' => [
                    'error_id' => 500,
                    'error_text' => 'Failed to fetch page: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Search pages by name, title, or description
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        // Optional auth - search can be public but auth provides additional info
        $authHeader = $request->header('Authorization');
        $tokenUserId = null;
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        }

        $term = $request->query('term', '');
        if (empty($term)) {
            return response()->json([
                'data' => [
                    'pages' => [],
                    'total' => 0,
                ],
            ]);
        }

        $perPage = (int) ($request->query('per_page', 12));
        $perPage = max(1, min($perPage, 50));
        $page = (int) ($request->query('page', 1));
        $page = max(1, $page);

        try {
            // Search pages by page_name, page_title, and page_description
            $query = Page::query()
                ->where('active', '1');

            // Search in page_name, page_title, and page_description
            $searchTerm = '%' . $term . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('page_name', 'LIKE', $searchTerm)
                  ->orWhere('page_title', 'LIKE', $searchTerm)
                  ->orWhere('page_description', 'LIKE', $searchTerm);
            });

            // Filter by category if provided
            if ($request->filled('category')) {
                $query->where('page_category', $request->query('category'));
            }

            // Get total count
            $total = $query->count();

            // Apply pagination
            $offset = ($page - 1) * $perPage;
            $pagesData = $query->orderByDesc('page_id')
                ->offset($offset)
                ->limit($perPage)
                ->get();

            // Format pages
            $pages = [];
            foreach ($pagesData as $pageItem) {
                // Check if user liked the page
                $isLiked = false;
                $likesCount = 0;
                if (DB::getSchemaBuilder()->hasTable('Wo_Pages_Likes')) {
                    $likesCount = DB::table('Wo_Pages_Likes')
                        ->where('page_id', $pageItem->page_id)
                        ->count();
                    
                    if ($tokenUserId) {
                        $isLiked = DB::table('Wo_Pages_Likes')
                            ->where('page_id', $pageItem->page_id)
                            ->where('user_id', $tokenUserId)
                            ->exists();
                    }
                }

                // Get page owner data
                $owner = null;
                if ($pageItem->user_id) {
                    $ownerData = DB::table('Wo_Users')
                        ->where('user_id', $pageItem->user_id)
                        ->first();
                    
                    if ($ownerData) {
                        $owner = [
                            'user_id' => $ownerData->user_id,
                            'username' => $ownerData->username ?? '',
                            'name' => $this->getUserName($ownerData),
                            'avatar_url' => $ownerData->avatar ? asset('storage/' . $ownerData->avatar) : null,
                        ];
                    }
                }

                // Get category name if available
                $categoryName = '';
                if ($pageItem->page_category && DB::getSchemaBuilder()->hasTable('Wo_Page_Categories')) {
                    $category = DB::table('Wo_Page_Categories')
                        ->where('id', $pageItem->page_category)
                        ->first();
                    if ($category) {
                        $categoryName = $category->name ?? '';
                    }
                }

                $pages[] = [
                    'page_id' => $pageItem->page_id,
                    'page_name' => $pageItem->page_name ?? '',
                    'page_title' => $pageItem->page_title ?? $pageItem->page_name ?? '',
                    'page_description' => $pageItem->page_description ?? '',
                    'category' => $pageItem->page_category ?? 0,
                    'category_name' => $categoryName,
                    'verified' => (bool) ($pageItem->verified ?? false),
                    'avatar' => $pageItem->avatar ?? '',
                    'avatar_url' => $pageItem->avatar ? asset('storage/' . $pageItem->avatar) : null,
                    'cover' => $pageItem->cover ?? '',
                    'cover_url' => $pageItem->cover ? asset('storage/' . $pageItem->cover) : null,
                    'website' => $pageItem->website ?? '',
                    'phone' => $pageItem->phone ?? '',
                    'address' => $pageItem->address ?? '',
                    'url' => $pageItem->url ?? url('/page/' . ($pageItem->page_name ?? '')),
                    'likes_count' => $likesCount,
                    'is_liked' => $isLiked,
                    'is_owner' => $tokenUserId && $pageItem->user_id == $tokenUserId,
                    'owner' => $owner,
                ];
            }

            // Calculate pagination metadata
            $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

            return response()->json([
                'data' => [
                    'pages' => $pages,
                    'total' => $total,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'last_page' => $lastPage,
                        'from' => $total > 0 ? $offset + 1 : 0,
                        'to' => min($offset + $perPage, $total),
                        'has_more' => $page < $lastPage,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to search pages: ' . $e->getMessage(),
                'data' => [
                    'pages' => [],
                    'total' => 0,
                ],
            ], 500);
        }
    }

    /**
     * Get posts for a specific page
     * 
     * @param Request $request
     * @param int $id Page ID
     * @return JsonResponse
     */
    public function getPosts(Request $request, $id): JsonResponse
    {
        // Optional auth - public pages can be viewed without auth
        $authHeader = $request->header('Authorization');
        $tokenUserId = null;
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $tokenUserId = DB::table('Wo_AppsSessions')->where('session_id', $token)->value('user_id');
        }

        try {
            // Check if page exists
            $page = Page::where('page_id', $id)->first();
            if (!$page) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Page not found'
                ], 404);
            }

            // Check if page is active (unless user is the owner)
            if ($page->active != '1') {
                if (!$tokenUserId || $page->user_id != $tokenUserId) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Page not found'
                    ], 404);
                }
            }

            $perPage = (int) ($request->query('per_page', 12));
            $perPage = max(1, min($perPage, 50));
            $pageNum = (int) ($request->query('page', 1));
            $pageNum = max(1, $pageNum);

            // Get posts for this page
            $query = DB::table('Wo_Posts')
                ->where('page_id', $id)
                ->where('active', '1')
                ->orderByDesc('time');

            // Get total count
            $total = $query->count();

            // Apply pagination
            $offset = ($pageNum - 1) * $perPage;
            $posts = $query->offset($offset)
                ->limit($perPage)
                ->get();

            // Format posts
            $formattedPosts = $posts->map(function ($post) use ($tokenUserId) {
                return $this->formatPostForPage($post, $tokenUserId);
            })->toArray();

            // Calculate pagination metadata
            $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

            return response()->json([
                'ok' => true,
                'data' => [
                    'page_id' => $id,
                    'page_name' => $page->page_name,
                    'page_title' => $page->page_title,
                    'posts' => $formattedPosts,
                    'pagination' => [
                        'current_page' => $pageNum,
                        'per_page' => $perPage,
                        'total' => $total,
                        'last_page' => $lastPage,
                        'from' => $total > 0 ? $offset + 1 : 0,
                        'to' => min($offset + $perPage, $total),
                        'has_more' => $pageNum < $lastPage,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Failed to fetch page posts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format post data for page posts response
     * 
     * @param object $post
     * @param string|null $userId
     * @return array
     */
    private function formatPostForPage($post, $userId = null): array
    {
        // Get user information
        $user = DB::table('Wo_Users')->where('user_id', $post->user_id)->first();
        
        // Get post reactions count
        $postIdForReactions = $post->post_id ?? $post->id;
        $reactionsCount = $this->getPostReactionsCount($postIdForReactions);
        
        // Get post comments count
        $postIdForComments = $post->post_id ?? $post->id;
        $commentsCount = $this->getPostCommentsCount($postIdForComments);
        
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
        
        // Get color data if it's a colored post
        $colorData = null;
        $colorId = $post->color_id ?? 0;
        if ($colorId > 0) {
            $colorData = $this->getColorData($colorId);
        }

        // Determine post type
        $postType = $this->getPostType($post);

        return [
            'id' => $post->id,
            'post_id' => $post->post_id ?? $post->id,
            'user_id' => $post->user_id,
            'post_text' => $post->postText ?? '',
            'post_type' => $postType,
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
            
            // Color data (for colored posts)
            'color_id' => $post->color_id ?? null,
            'color' => $colorData,
            
            // Engagement metrics
            'reactions_count' => $reactionsCount,
            'comments_count' => $commentsCount,
            'shares_count' => $post->postShare ?? 0,
            'views_count' => $post->videoViews ?? 0,
            
            // User interaction
            'is_liked' => $userId ? $this->isPostLiked($post->id, $userId) : false,
            'is_owner' => $userId && $post->user_id == $userId,
            'is_boosted' => (bool) ($post->boosted ?? false),
            'comments_disabled' => (bool) ($post->comments_status ?? false),
            
            // Author information
            'author' => [
                'user_id' => $post->user_id,
                'username' => $user?->username ?? 'Unknown',
                'name' => $this->getUserName($user),
                'avatar_url' => ($user?->avatar) ? asset('storage/' . $user?->avatar) : null,
                'verified' => (bool) ($user?->verified ?? false),
            ],
            
            // Timestamps
            'created_at' => $post->time ? date('c', $post->time) : null,
            'created_at_human' => $post->time ? $this->getHumanTime($post->time) : null,
            'time' => $post->time,
        ];
    }

    /**
     * Get post reactions count
     * 
     * @param int $postId
     * @return int
     */
    private function getPostReactionsCount(int $postId): int
    {
        if (Schema::hasTable('Wo_Reactions')) {
            try {
                return DB::table('Wo_Reactions')
                    ->where('post_id', $postId)
                    ->where('comment_id', 0)
                    ->count();
            } catch (\Exception $e) {
                return 0;
            }
        }
        return 0;
    }

    /**
     * Get post comments count
     * 
     * @param int $postId
     * @return int
     */
    private function getPostCommentsCount(int $postId): int
    {
        if (Schema::hasTable('Wo_Comments')) {
            try {
                return DB::table('Wo_Comments')
                    ->where('post_id', $postId)
                    ->count();
            } catch (\Exception $e) {
                return 0;
            }
        }
        return 0;
    }

    /**
     * Get album images for a post
     * 
     * @param int $postId
     * @return array
     */
    private function getAlbumImages(int $postId): array
    {
        if (!Schema::hasTable('Wo_Albums_Media')) {
            return [];
        }

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
     * Get poll options with vote counts
     * 
     * @param int $postId
     * @param string|null $userId
     * @return array
     */
    private function getPollOptions(int $postId, $userId = null): array
    {
        if (!Schema::hasTable('Wo_Polls')) {
            return [];
        }

        try {
            $options = DB::table('Wo_Polls')
                ->where('post_id', $postId)
                ->get();

            if ($options->isEmpty()) {
                return [];
            }

            $votesTable = 'Wo_Votes';
            if (!Schema::hasTable($votesTable)) {
                if (Schema::hasTable('Wo_PollVotes')) {
                    $votesTable = 'Wo_PollVotes';
                } else {
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

            $totalVotes = DB::table($votesTable)
                ->where('post_id', $postId)
                ->count();

            $userVote = null;
            if ($userId) {
                $userVote = DB::table($votesTable)
                    ->where('post_id', $postId)
                    ->where('user_id', $userId)
                    ->value('option_id');
            }

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
            return [];
        }
    }

    /**
     * Get color data for a colored post
     * 
     * @param int $colorId
     * @return array|null
     */
    private function getColorData(int $colorId): ?array
    {
        if (!Schema::hasTable('Wo_Colored_Posts')) {
            return null;
        }

        try {
            $coloredPost = DB::table('Wo_Colored_Posts')
                ->where('id', $colorId)
                ->first();
            
            if (!$coloredPost) {
                return null;
            }

            return [
                'color_id' => $coloredPost->id,
                'color_1' => $coloredPost->color_1 ?? '',
                'color_2' => $coloredPost->color_2 ?? '',
                'text_color' => $coloredPost->text_color ?? '',
                'image' => $coloredPost->image ?? '',
                'image_url' => !empty($coloredPost->image) ? asset('storage/' . $coloredPost->image) : null,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get post type based on content
     * 
     * @param object $post
     * @return string
     */
    private function getPostType($post): string
    {
        if (!empty($post->postType)) {
            return $post->postType;
        }
        
        if (!empty($post->job_id) && $post->job_id > 0) return 'job';
        if (!empty($post->blog_id) && $post->blog_id > 0) return 'blog';
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
     * Get post photo URL
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
        
        $isGifPost = ($post->postType ?? '') === 'gif';
        $isUrl = filter_var($postPhoto, FILTER_VALIDATE_URL) !== false;
        
        if ($isGifPost || $isUrl) {
            return preg_replace('#([^:])//+#', '$1/', $postPhoto);
        }
        
        return asset('storage/' . $postPhoto);
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
        if (!Schema::hasTable('Wo_Reactions')) {
            return false;
        }

        try {
            return DB::table('Wo_Reactions')
                ->where('post_id', $postId)
                ->where('user_id', $userId)
                ->where('comment_id', 0)
                ->exists();
        } catch (\Exception $e) {
            return false;
        }
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
     * Get user name from user object (handles different column structures)
     * 
     * @param object $user
     * @return string
     */
    private function getUserName($user): string
    {
        if (!$user) {
            return 'Unknown User';
        }
        
        // Try name column first
        if (isset($user->name) && !empty($user->name)) {
            return $user->name;
        }
        
        // Try first_name + last_name
        $firstName = $user->first_name ?? '';
        $lastName = $user->last_name ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        if (!empty($fullName)) {
            return $fullName;
        }
        
        // Fallback to username
        return $user->username ?? 'Unknown User';
    }
}


