<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Page;
use App\Models\PageCategory;
use App\Models\PageSubCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

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

        $data = $paginator->getCollection()->map(function (Page $page) {
            return [
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
                $updateData['about'] = $request->input('about');
                // Also update page_description if about is provided and page_description doesn't exist
                if (!isset($updateData['page_description'])) {
                    $updateData['page_description'] = $request->input('about');
                }
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
                    'about' => $page->about ?? $page->page_description ?? '',
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
                    'page_description' => $page->page_description ?? $page->about ?? '',
                    'about' => $page->about ?? $page->page_description ?? '',
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
     * Get user name from user object (handles different column structures)
     * 
     * @param object $user
     * @return string
     */
    private function getUserName($user): string
    {
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


