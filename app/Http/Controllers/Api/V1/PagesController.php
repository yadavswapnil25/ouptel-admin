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
use Illuminate\Support\Facades\Storage;

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
            // Get page category name if available
            $categoryName = '';
            if ($page->page_category && DB::getSchemaBuilder()->hasTable('Wo_Pages_Categories')) {
                $category = PageCategory::find($page->page_category);
                if ($category) {
                    $categoryName = $category->name;
                }
            }

            // Get page sub category value and name if available
            // Access sub_category directly from attributes since it's not in fillable
            $subCategoryValue = '';
            if (Schema::hasColumn('Wo_Pages', 'sub_category')) {
                $subCategoryValue = $page->getAttributes()['sub_category'] ?? '';
            }
            
            $subCategoryName = '';
            if (!empty($subCategoryValue) && Schema::hasTable('Wo_Sub_Categories')) {
                $subCategory = PageSubCategory::find($subCategoryValue);
                if ($subCategory) {
                    $subCategoryName = $subCategory->name;
                }
            }

            $pageData = [
                'page_id' => $page->page_id,
                'page_name' => $page->page_name,
                'page_title' => $page->page_title,
                'description' => $page->page_description,
                'category' => $page->page_category ?? 0,
                'category_name' => $categoryName,
                'sub_category' => $subCategoryValue,
                'sub_category_name' => $subCategoryName,
                'verified' => $page->verified,
                'avatar_url' => $this->getFileUrl($page->getAttributes()['avatar'] ?? ''),
                'cover_url' => $this->getFileUrl($page->getAttributes()['cover'] ?? ''),
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

        // Call to action options (matching WoWonder call_action array)
        // These are the text labels that map to integer IDs stored in call_action_type column
        $callToActions = [
            ['id' => 0, 'label' => ''],
            ['id' => 1, 'label' => 'Read more'],
            ['id' => 2, 'label' => 'Shop now'],
            ['id' => 3, 'label' => 'View now'],
            ['id' => 4, 'label' => 'Visit now'],
            ['id' => 5, 'label' => 'Book now'],
            ['id' => 6, 'label' => 'Learn more'],
            ['id' => 7, 'label' => 'Play now'],
            ['id' => 8, 'label' => 'Bet now'],
            ['id' => 9, 'label' => 'Donate'],
            ['id' => 10, 'label' => 'Apply here'],
            ['id' => 11, 'label' => 'Quote here'],
            ['id' => 12, 'label' => 'Order now'],
            ['id' => 13, 'label' => 'Book tickets'],
            ['id' => 14, 'label' => 'Enroll now'],
            ['id' => 15, 'label' => 'Find a card'],
            ['id' => 16, 'label' => 'Get a quote'],
            ['id' => 17, 'label' => 'Get tickets'],
            ['id' => 18, 'label' => 'Locate a dealer'],
            ['id' => 19, 'label' => 'Order online'],
            ['id' => 20, 'label' => 'Preorder now'],
            ['id' => 21, 'label' => 'Schedule now'],
            ['id' => 22, 'label' => 'Sign up now'],
            ['id' => 23, 'label' => 'Subscribe'],
            ['id' => 24, 'label' => 'Register now'],
        ];

        return response()->json([
            'ok' => true,
            'data' => [
                'categories' => $categories,
                'sub_categories' => $subCategories,
                'call_to_actions' => $callToActions,
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
        $subCategory = $request->input('sub_category', '');
        $pageSubCategory = $request->input('page_sub_category', ''); // Alternative parameter name

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

        // Start database transaction
        DB::beginTransaction();

        try {
            // Validate and set sub_category if provided
            $subCategoryValue = '';
            if (!empty($subCategory) || !empty($pageSubCategory)) {
                $subCategoryInput = !empty($subCategory) ? $subCategory : $pageSubCategory;
                
                // Validate sub_category belongs to the selected category
                if (Schema::hasTable('Wo_Sub_Categories')) {
                    $subCategoryExists = DB::table('Wo_Sub_Categories')
                        ->where('id', $subCategoryInput)
                        ->where('category_id', $pageCategory)
                        ->where('type', 'page')
                        ->exists();
                    
                    if ($subCategoryExists) {
                        $subCategoryValue = $subCategoryInput;
                    }
                    // If sub_category doesn't exist or doesn't belong to category, leave it empty
                }
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
            
            // Set sub_category directly on attributes since it's not in fillable
            if (Schema::hasColumn('Wo_Pages', 'sub_category')) {
                $page->setAttribute('sub_category', $subCategoryValue);
            }
            
            $page->save();

            // Get sub category name if available
            $subCategoryName = '';
            if (!empty($subCategoryValue) && Schema::hasTable('Wo_Sub_Categories')) {
                $subCategoryModel = PageSubCategory::find($subCategoryValue);
                if ($subCategoryModel) {
                    $subCategoryName = $subCategoryModel->name;
                }
            }

            // Commit transaction if everything is successful
            DB::commit();

            // Return response matching old API format
            return response()->json([
                'api_status' => 200,
                'api_text' => 'success',
                'api_version' => '1.0',
                'page_name' => $page->page_name,
                'page_id' => $page->page_id,
                'sub_category' => $subCategoryValue,
                'sub_category_name' => $subCategoryName,
            ], 200);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            // Return error response
            return response()->json([
                'api_status' => 500,
                'api_text' => 'failed',
                'api_version' => '1.0',
                'errors' => [
                    'error_id' => 500,
                    'error_text' => 'Failed to create page: ' . $e->getMessage()
                ]
            ], 500);
        }
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
            // Ensure empty strings are preserved (not converted to null) since DB columns don't allow null
            if ($request->has('website')) {
                $website = $request->input('website', '');
                $updateData['website'] = $website !== null ? trim((string) $website) : '';
            }
            if ($request->has('phone')) {
                $phone = $request->input('phone', '');
                $updateData['phone'] = $phone !== null ? trim((string) $phone) : '';
            }
            if ($request->has('address')) {
                $address = $request->input('address', '');
                $updateData['address'] = $address !== null ? trim((string) $address) : '';
            }

            // Update social media links
            // Check if columns exist before updating to avoid errors
            $socialFields = ['facebook', 'instgram', 'linkedin', 'twitter', 'youtube', 'vk', 'vkontakte'];
            foreach ($socialFields as $field) {
                if ($request->has($field)) {
                    // Handle vkontakte -> vk mapping
                    $dbField = ($field === 'vkontakte') ? 'vk' : $field;
                    
                    // Check if column exists in database
                    if (Schema::hasColumn('Wo_Pages', $dbField)) {
                        $value = $request->input($field, '');
                        // Validate URL if not empty
                        if (!empty($value)) {
                            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                                $errors[] = ucfirst($field) . ' URL is invalid';
                            } else {
                                $updateData[$dbField] = trim((string) $value);
                            }
                        } else {
                            // Allow empty string to clear the link
                            $updateData[$dbField] = '';
                        }
                    }
                }
            }

            // Update page call-to-action settings using WoWonder's original columns
            // DB columns: call_action_type (int), call_action_type_url (string), users_post (int 0/1)
            // Accept both old parameter names and the new ones you are using
            if ($request->has('call_action_type') || $request->has('call_to_action')) {
                $callAction = $request->input('call_action_type', $request->input('call_to_action'));
                $updateData['call_action_type'] = is_numeric($callAction) ? (int) $callAction : 0;
            }

            if ($request->has('call_action_type_url') || $request->has('call_to_target_url')) {
                $callActionUrl = $request->input('call_action_type_url', $request->input('call_to_target_url'));
                // Validate URL if provided (not empty)
                if (!empty($callActionUrl)) {
                    if (!filter_var($callActionUrl, FILTER_VALIDATE_URL)) {
                        $errors[] = 'Call to action URL is invalid';
                    } else {
                        $updateData['call_action_type_url'] = $callActionUrl;
                    }
                } else {
                    // Allow empty string to clear the URL
                    $updateData['call_action_type_url'] = '';
                }
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

            // Handle verification status/request
            // Accepts: 'verified' (1), 'notVerified' (0), 'pending', 'request'
            $verificationRequestSubmitted = false;
            if ($request->has('verified') || $request->has('verification_request')) {
                $verifiedValue = $request->input('verified', $request->input('verification_request'));
                
                if (is_string($verifiedValue)) {
                    $normalized = strtolower($verifiedValue);
                    // Handle verification request statuses
                    if (in_array($normalized, ['verified', '1', 'true', 'yes'], true)) {
                        $updateData['verified'] = '1';
                    } elseif (in_array($normalized, ['notverified', 'notverified', '0', 'false', 'no'], true)) {
                        $updateData['verified'] = '0';
                    } elseif (in_array($normalized, ['pending', 'request', 'pending_request'])) {
                        // For pending/request status, check if verification request table exists
                        // and create a verification request record if it doesn't exist
                        if (DB::getSchemaBuilder()->hasTable('Wo_Verification_Requests')) {
                            $existingRequest = DB::table('Wo_Verification_Requests')
                                ->where('page_id', $id)
                                ->where('seen', 0) // Not yet reviewed
                                ->first();
                            
                            if (!$existingRequest) {
                                // Create a new verification request
                                DB::table('Wo_Verification_Requests')->insert([
                                    'page_id' => $id,
                                    'user_id' => $userId,
                                    'type' => 'Page',
                                    'seen' => 0,
                                    'message' => $request->input('verification_message', ''),
                                    'user_name' => '',
                                    'passport' => '',
                                    'photo' => '',
                                ]);
                                $verificationRequestSubmitted = true;
                            } else {
                                $verificationRequestSubmitted = true; // Request already exists
                            }
                        }
                        // Don't update verified field for pending/request - leave it as is
                    }
                } elseif (is_numeric($verifiedValue)) {
                    $updateData['verified'] = (int) $verifiedValue ? '1' : '0';
                }
            }

            // Handle avatar upload if provided
            if ($request->hasFile('avatar')) {
                $avatar = $request->file('avatar');
                if ($avatar && $avatar->isValid()) {
                    // Validate image
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($avatar->getMimeType(), $allowedMimes)) {
                        $errors[] = 'Avatar must be a valid image (JPEG, PNG, GIF, or WebP)';
                    } elseif ($avatar->getSize() > 5 * 1024 * 1024) { // 5MB max
                        $errors[] = 'Avatar file size must be less than 5MB';
                    } else {
                        // Delete old avatar if it exists and is not a default image
                        $oldAvatar = $page->avatar ?? '';
                        if (!empty($oldAvatar) && 
                            !str_contains($oldAvatar, 'd-page.jpg') && 
                            !str_contains($oldAvatar, 'd-avatar.jpg') &&
                            Storage::disk('public')->exists($oldAvatar)) {
                            Storage::disk('public')->delete($oldAvatar);
                        }
                        
                        // Store avatar using WoWonder path format (upload/photos/)
                        $extension = $avatar->getClientOriginalExtension();
                        $filename = 'page_avatar_' . $id . '_' . time() . '.' . $extension;
                        $avatarPath = $avatar->storeAs('upload/photos/' . date('Y/m'), $filename, 'public');
                        if ($avatarPath) {
                            $updateData['avatar'] = $avatarPath;
                        } else {
                            $errors[] = 'Failed to upload avatar';
                        }
                    }
                } else {
                    $errors[] = 'Invalid avatar file';
                }
            }

            // Handle cover upload if provided
            if ($request->hasFile('cover')) {
                $cover = $request->file('cover');
                if ($cover && $cover->isValid()) {
                    // Validate image
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($cover->getMimeType(), $allowedMimes)) {
                        $errors[] = 'Cover must be a valid image (JPEG, PNG, GIF, or WebP)';
                    } elseif ($cover->getSize() > 10 * 1024 * 1024) { // 10MB max
                        $errors[] = 'Cover file size must be less than 10MB';
                    } else {
                        // Delete old cover if it exists and is not a default image
                        $oldCover = $page->cover ?? '';
                        if (!empty($oldCover) && 
                            !str_contains($oldCover, 'd-cover.jpg') && 
                            !str_contains($oldCover, 'cover.jpg') &&
                            Storage::disk('public')->exists($oldCover)) {
                            Storage::disk('public')->delete($oldCover);
                        }
                        
                        // Store cover using WoWonder path format (upload/photos/)
                        $extension = $cover->getClientOriginalExtension();
                        $filename = 'page_cover_' . $id . '_' . time() . '.' . $extension;
                        $coverPath = $cover->storeAs('upload/photos/' . date('Y/m'), $filename, 'public');
                        if ($coverPath) {
                            $updateData['cover'] = $coverPath;
                        } else {
                            $errors[] = 'Failed to upload cover';
                        }
                    }
                } else {
                    $errors[] = 'Invalid cover file';
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
                
                // Update using DB directly to ensure changes are saved
                $updated = DB::table('Wo_Pages')
                    ->where('page_id', $id)
                    ->update($updateData);
                
                // Reload the page from database to get updated values
                // Clear the model cache and reload
                $page = Page::where('page_id', $id)->first();
                if ($page) {
                    $page->refresh();
                }
            }

            // Check verification request status
            $verificationRequestStatus = null;
            $verificationStatus = 'not_verified'; // Default status
            
            if (DB::getSchemaBuilder()->hasTable('Wo_Verification_Requests')) {
                $pendingRequest = DB::table('Wo_Verification_Requests')
                    ->where('page_id', $id)
                    ->where('seen', 0)
                    ->first();
                
                if ($pendingRequest) {
                    $verificationRequestStatus = 'pending';
                    $verificationStatus = 'pending';
                } elseif ($verificationRequestSubmitted) {
                    $verificationRequestStatus = 'pending';
                    $verificationStatus = 'pending';
                }
            }
            
            // If page is actually verified, set status to verified
            if ($page->verified == '1') {
                $verificationStatus = 'verified';
            }

            // Return success response
            $responseMessage = 'Page updated successfully';
            if ($verificationRequestSubmitted) {
                $responseMessage = 'Page updated successfully. Verification request submitted.';
            }

            // Get fresh avatar, cover, and social links from database to ensure we have the latest values
            $pageData = DB::table('Wo_Pages')
                ->where('page_id', $id)
                ->first();
            
            $avatarPath = $pageData->avatar ?? $page->avatar ?? '';
            $coverPath = $pageData->cover ?? $page->cover ?? '';
            
            // Get social media links if columns exist
            $socialLinks = [];
            if (Schema::hasColumn('Wo_Pages', 'facebook')) {
                $socialLinks['facebook'] = $pageData->facebook ?? '';
            }
            if (Schema::hasColumn('Wo_Pages', 'instgram')) {
                $socialLinks['instgram'] = $pageData->instgram ?? '';
            }
            if (Schema::hasColumn('Wo_Pages', 'linkedin')) {
                $socialLinks['linkedin'] = $pageData->linkedin ?? '';
            }
            if (Schema::hasColumn('Wo_Pages', 'twitter')) {
                $socialLinks['twitter'] = $pageData->twitter ?? '';
            }
            if (Schema::hasColumn('Wo_Pages', 'youtube')) {
                $socialLinks['youtube'] = $pageData->youtube ?? '';
            }
            if (Schema::hasColumn('Wo_Pages', 'vk')) {
                $socialLinks['vkontakte'] = $pageData->vk ?? '';
            }

            return response()->json([
                'api_status' => 200,
                'api_text' => 'success',
                'api_version' => '1.0',
                'message' => $responseMessage,
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
                    // Social media links
                    'facebook' => $socialLinks['facebook'] ?? '',
                    'instgram' => $socialLinks['instgram'] ?? '',
                    'linkedin' => $socialLinks['linkedin'] ?? '',
                    'twitter' => $socialLinks['twitter'] ?? '',
                    'youtube' => $socialLinks['youtube'] ?? '',
                    'vkontakte' => $socialLinks['vkontakte'] ?? '',
                    'call_to_action' => $page->call_action_type ?? 0,
                    'call_to_target_url' => $page->call_action_type_url ?? '',
                    'can_post' => isset($page->users_post) ? (int) $page->users_post : 0,
                    'verified' => (bool) ($page->verified ?? false),
                    'verification_status' => $verificationStatus, // 'not_verified', 'pending', or 'verified'
                    'verification_request_status' => $verificationRequestStatus,
                    'avatar' => $avatarPath,
                    'avatar_url' => $this->getFileUrl($avatarPath),
                    'cover' => $coverPath,
                    'cover_url' => $this->getFileUrl($coverPath),
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
            if ($page->page_category && DB::getSchemaBuilder()->hasTable('Wo_Pages_Categories')) {
                $category = PageCategory::find($page->page_category);
                if ($category) {
                    $categoryName = $category->name;
                }
            }

            // Get page sub category name if available
            // Access sub_category directly from attributes since it's not in fillable
            $subCategoryValue = '';
            if (Schema::hasColumn('Wo_Pages', 'sub_category')) {
                $subCategoryValue = $page->getAttributes()['sub_category'] ?? '';
            }
            
            $subCategoryName = '';
            if (!empty($subCategoryValue) && Schema::hasTable('Wo_Sub_Categories')) {
                $subCategory = PageSubCategory::find($subCategoryValue);
                if ($subCategory) {
                    $subCategoryName = $subCategory->name;
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

            // Get social media links directly from database to ensure we get all columns
            $pageData = DB::table('Wo_Pages')
                ->where('page_id', $id)
                ->first();
            
            $socialLinks = [];
            if ($pageData) {
                if (Schema::hasColumn('Wo_Pages', 'facebook')) {
                    $socialLinks['facebook'] = $pageData->facebook ?? '';
                }
                if (Schema::hasColumn('Wo_Pages', 'instgram')) {
                    $socialLinks['instgram'] = $pageData->instgram ?? '';
                }
                if (Schema::hasColumn('Wo_Pages', 'linkedin')) {
                    $socialLinks['linkedin'] = $pageData->linkedin ?? '';
                }
                if (Schema::hasColumn('Wo_Pages', 'twitter')) {
                    $socialLinks['twitter'] = $pageData->twitter ?? '';
                }
                if (Schema::hasColumn('Wo_Pages', 'youtube')) {
                    $socialLinks['youtube'] = $pageData->youtube ?? '';
                }
                if (Schema::hasColumn('Wo_Pages', 'vk')) {
                    $socialLinks['vkontakte'] = $pageData->vk ?? '';
                }
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
                    'avatar_url' => $this->getFileUrl($page->avatar ?? ''),
                    'cover' => $page->cover ?? '',
                    'cover_url' => $this->getFileUrl($page->cover ?? ''),
                    'website' => $page->website ?? '',
                    'phone' => $page->phone ?? '',
                    'address' => $page->address ?? '',
                    // Social media links (retrieved directly from database)
                    'facebook' => $socialLinks['facebook'] ?? '',
                    'instgram' => $socialLinks['instgram'] ?? '',
                    'linkedin' => $socialLinks['linkedin'] ?? '',
                    'twitter' => $socialLinks['twitter'] ?? '',
                    'youtube' => $socialLinks['youtube'] ?? '',
                    'vkontakte' => $socialLinks['vkontakte'] ?? '',
                    'sub_category' => $subCategoryValue,
                    'sub_category_name' => $subCategoryName,
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
                if ($pageItem->page_category && DB::getSchemaBuilder()->hasTable('Wo_Pages_Categories')) {
                    $category = PageCategory::find($pageItem->page_category);
                    if ($category) {
                        $categoryName = $category->name;
                    }
                }

                // Get sub category name if available
                // Access sub_category directly from attributes since it's not in fillable
                $subCategoryValue = '';
                if (Schema::hasColumn('Wo_Pages', 'sub_category')) {
                    $subCategoryValue = $pageItem->getAttributes()['sub_category'] ?? '';
                }
                
                $subCategoryName = '';
                if (!empty($subCategoryValue) && Schema::hasTable('Wo_Sub_Categories')) {
                    $subCategory = PageSubCategory::find($subCategoryValue);
                    if ($subCategory) {
                        $subCategoryName = $subCategory->name;
                    }
                }

                $pages[] = [
                    'page_id' => $pageItem->page_id,
                    'page_name' => $pageItem->page_name ?? '',
                    'page_title' => $pageItem->page_title ?? $pageItem->page_name ?? '',
                    'page_description' => $pageItem->page_description ?? '',
                    'category' => $pageItem->page_category ?? 0,
                    'category_name' => $categoryName,
                    'sub_category' => $subCategoryValue,
                    'sub_category_name' => $subCategoryName,
                    'verified' => (bool) ($pageItem->verified ?? false),
                    'avatar' => $pageItem->getAttributes()['avatar'] ?? '',
                    'avatar_url' => $this->getFileUrl($pageItem->getAttributes()['avatar'] ?? ''),
                    'cover' => $pageItem->getAttributes()['cover'] ?? '',
                    'cover_url' => $this->getFileUrl($pageItem->getAttributes()['cover'] ?? ''),
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

    /**
     * Get page analytics (mimics old API: ajax_loading.php?link1=page-setting&page={page_name}&link3=analytics)
     * 
     * @param Request $request
     * @param int|string $id Page ID or page name
     * @return JsonResponse
     */
    public function analytics(Request $request, $id): JsonResponse
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
            // Find the page by ID or page_name
            $page = null;
            if (is_numeric($id)) {
                $page = Page::where('page_id', $id)->first();
            } else {
                $page = Page::where('page_name', $id)->first();
            }

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

            $pageId = $page->page_id;
            $currentTime = time();
            
            // Calculate time ranges
            $todayStart = strtotime('today');
            $weekStart = strtotime('monday this week');
            $monthStart = strtotime('first day of this month midnight');
            $yearStart = strtotime('january 1 this year');

            // Get likes statistics
            $likesStats = [
                'total' => 0,
                'today' => 0,
                'this_week' => 0,
                'this_month' => 0,
                'this_year' => 0,
            ];

            if (DB::getSchemaBuilder()->hasTable('Wo_Pages_Likes')) {
                $likesQuery = DB::table('Wo_Pages_Likes')
                    ->where('page_id', $pageId);

                $likesStats['total'] = $likesQuery->count();

                // Check if time column exists
                if (Schema::hasColumn('Wo_Pages_Likes', 'time')) {
                    $likesStats['today'] = (clone $likesQuery)
                        ->where('time', '>=', $todayStart)
                        ->count();

                    $likesStats['this_week'] = (clone $likesQuery)
                        ->where('time', '>=', $weekStart)
                        ->count();

                    $likesStats['this_month'] = (clone $likesQuery)
                        ->where('time', '>=', $monthStart)
                        ->count();

                    $likesStats['this_year'] = (clone $likesQuery)
                        ->where('time', '>=', $yearStart)
                        ->count();
                }
            }

            // Get posts statistics
            $postsStats = [
                'total' => 0,
                'today' => 0,
                'this_week' => 0,
                'this_month' => 0,
                'this_year' => 0,
            ];

            if (DB::getSchemaBuilder()->hasTable('Wo_Posts')) {
                $postsQuery = DB::table('Wo_Posts')
                    ->where('page_id', $pageId)
                    ->where('active', '1');

                $postsStats['total'] = $postsQuery->count();

                // Posts use 'time' column (Unix timestamp)
                if (Schema::hasColumn('Wo_Posts', 'time')) {
                    $postsStats['today'] = (clone $postsQuery)
                        ->where('time', '>=', $todayStart)
                        ->count();

                    $postsStats['this_week'] = (clone $postsQuery)
                        ->where('time', '>=', $weekStart)
                        ->count();

                    $postsStats['this_month'] = (clone $postsQuery)
                        ->where('time', '>=', $monthStart)
                        ->count();

                    $postsStats['this_year'] = (clone $postsQuery)
                        ->where('time', '>=', $yearStart)
                        ->count();
                }
            }

            // Get recent likes (last 10)
            $recentLikes = [];
            if (DB::getSchemaBuilder()->hasTable('Wo_Pages_Likes')) {
                $recentLikesQuery = DB::table('Wo_Pages_Likes')
                    ->where('page_id', $pageId)
                    ->orderByDesc('time')
                    ->limit(10);

                if (Schema::hasColumn('Wo_Pages_Likes', 'time')) {
                    $recentLikesData = $recentLikesQuery->get();
                    
                    foreach ($recentLikesData as $like) {
                        $user = DB::table('Wo_Users')
                            ->where('user_id', $like->user_id)
                            ->first();
                        
                        $recentLikes[] = [
                            'user_id' => $like->user_id,
                            'username' => $user->username ?? 'Unknown',
                            'name' => $this->getUserName($user),
                            'avatar_url' => $user && $user->avatar ? asset('storage/' . $user->avatar) : null,
                            'liked_at' => $like->time ? date('c', $like->time) : null,
                            'liked_at_human' => $like->time ? $this->getHumanTime($like->time) : null,
                        ];
                    }
                }
            }

            // Get recent posts (last 5)
            $recentPosts = [];
            if (DB::getSchemaBuilder()->hasTable('Wo_Posts')) {
                $recentPostsData = DB::table('Wo_Posts')
                    ->where('page_id', $pageId)
                    ->where('active', '1')
                    ->orderByDesc('time')
                    ->limit(5)
                    ->get();

                foreach ($recentPostsData as $post) {
                    $recentPosts[] = [
                        'post_id' => $post->id ?? $post->post_id ?? null,
                        'post_text' => substr($post->postText ?? '', 0, 100) . (strlen($post->postText ?? '') > 100 ? '...' : ''),
                        'created_at' => $post->time ? date('c', $post->time) : null,
                        'created_at_human' => $post->time ? $this->getHumanTime($post->time) : null,
                    ];
                }
            }

            // Calculate growth percentages (comparing this period to previous period)
            $likesGrowth = [
                'week' => $this->calculateGrowth($likesStats['this_week'], $this->getPreviousPeriodCount('Wo_Pages_Likes', $pageId, $weekStart, 'week')),
                'month' => $this->calculateGrowth($likesStats['this_month'], $this->getPreviousPeriodCount('Wo_Pages_Likes', $pageId, $monthStart, 'month')),
                'year' => $this->calculateGrowth($likesStats['this_year'], $this->getPreviousPeriodCount('Wo_Pages_Likes', $pageId, $yearStart, 'year')),
            ];

            $postsGrowth = [
                'week' => $this->calculateGrowth($postsStats['this_week'], $this->getPreviousPeriodCount('Wo_Posts', $pageId, $weekStart, 'week', true)),
                'month' => $this->calculateGrowth($postsStats['this_month'], $this->getPreviousPeriodCount('Wo_Posts', $pageId, $monthStart, 'month', true)),
                'year' => $this->calculateGrowth($postsStats['this_year'], $this->getPreviousPeriodCount('Wo_Posts', $pageId, $yearStart, 'year', true)),
            ];

            return response()->json([
                'api_status' => 200,
                'api_text' => 'success',
                'api_version' => '1.0',
                'data' => [
                    'page_id' => $page->page_id,
                    'page_name' => $page->page_name,
                    'page_title' => $page->page_title,
                    'likes' => $likesStats,
                    'posts' => $postsStats,
                    'likes_growth' => $likesGrowth,
                    'posts_growth' => $postsGrowth,
                    'recent_likes' => $recentLikes,
                    'recent_posts' => $recentPosts,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 500,
                'errors' => [
                    'error_id' => 500,
                    'error_text' => 'Failed to fetch page analytics: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Calculate growth percentage
     * 
     * @param int $current
     * @param int $previous
     * @return float|null
     */
    private function calculateGrowth($current, $previous): ?float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : null;
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Get count for previous period
     * 
     * @param string $table
     * @param int $pageId
     * @param int $currentPeriodStart
     * @param string $periodType
     * @param bool $isPosts
     * @return int
     */
    private function getPreviousPeriodCount($table, $pageId, $currentPeriodStart, $periodType, $isPosts = false): int
    {
        if (!DB::getSchemaBuilder()->hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        
        if ($isPosts) {
            $query->where('page_id', $pageId)->where('active', '1');
        } else {
            $query->where('page_id', $pageId);
        }

        if (!Schema::hasColumn($table, 'time')) {
            return 0;
        }

        // Calculate previous period start and end
        $previousPeriodEnd = $currentPeriodStart - 1;
        $previousPeriodStart = 0;

        switch ($periodType) {
            case 'week':
                $previousPeriodStart = $currentPeriodStart - (7 * 24 * 60 * 60); // 7 days ago
                break;
            case 'month':
                $previousPeriodStart = strtotime('-1 month', $currentPeriodStart);
                break;
            case 'year':
                $previousPeriodStart = strtotime('-1 year', $currentPeriodStart);
                break;
        }

        return $query->where('time', '>=', $previousPeriodStart)
            ->where('time', '<', $currentPeriodStart)
            ->count();
    }

    /**
     * Get page admins (mimics old API: ajax_loading.php?link1=page-setting&page={page_name}&link3=admins)
     * 
     * @param Request $request
     * @param int|string $id Page ID or page name
     * @return JsonResponse
     */
    public function getPageAdmins(Request $request, $id): JsonResponse
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

        try {
            // Find page by ID or page name
            $page = null;
            if (is_numeric($id)) {
                $page = Page::where('page_id', $id)->first();
            } else {
                $page = Page::where('page_name', $id)->first();
            }

            if (!$page) {
                return response()->json([
                    'api_status' => 400,
                    'errors' => [
                        'error_id' => 4,
                        'error_text' => 'Page not found'
                    ]
                ], 404);
            }

            // Check if user is page owner or admin
            $isOwner = ((string) $page->user_id === (string) $tokenUserId);
            $isAdmin = false;

            // Check if user is an admin (try both table names)
            if (Schema::hasTable('Wo_PageAdmins')) {
                $isAdmin = DB::table('Wo_PageAdmins')
                    ->where('page_id', $page->page_id)
                    ->where('user_id', $tokenUserId)
                    ->exists();
            } elseif (Schema::hasTable('Wo_Pages_Admin')) {
                $isAdmin = DB::table('Wo_Pages_Admin')
                    ->where('page_id', $page->page_id)
                    ->where('user_id', $tokenUserId)
                    ->exists();
            }

            if (!$isOwner && !$isAdmin) {
                return response()->json([
                    'api_status' => 403,
                    'errors' => [
                        'error_id' => 5,
                        'error_text' => 'Access denied - You must be the page owner or an admin to view admins'
                    ]
                ], 403);
            }

            // Get all admins for this page
            $admins = [];
            
            // Try Wo_PageAdmins table first
            if (Schema::hasTable('Wo_PageAdmins')) {
                $adminRecords = DB::table('Wo_PageAdmins')
                    ->where('page_id', $page->page_id)
                    ->get();
                
                foreach ($adminRecords as $adminRecord) {
                    $user = DB::table('Wo_Users')
                        ->where('user_id', $adminRecord->user_id)
                        ->first();
                    
                    if ($user) {
                        $userName = $user->name ?? '';
                        if (empty($userName)) {
                            $firstName = $user->first_name ?? '';
                            $lastName = $user->last_name ?? '';
                            $userName = trim($firstName . ' ' . $lastName);
                        }
                        if (empty($userName)) {
                            $userName = $user->username ?? 'Unknown User';
                        }

                        $admins[] = [
                            'user_id' => $user->user_id,
                            'username' => $user->username ?? 'Unknown',
                            'name' => $userName,
                            'email' => $user->email ?? '',
                            'avatar' => $user->avatar ?? '',
                            'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                            'verified' => (bool) ($user->verified ?? false),
                            'is_owner' => ((string) $user->user_id === (string) $page->user_id),
                            'added_at' => isset($adminRecord->time) ? date('c', $adminRecord->time) : null,
                        ];
                    }
                }
            } 
            // Fallback to Wo_Pages_Admin table
            elseif (Schema::hasTable('Wo_Pages_Admin')) {
                $adminRecords = DB::table('Wo_Pages_Admin')
                    ->where('page_id', $page->page_id)
                    ->get();
                
                foreach ($adminRecords as $adminRecord) {
                    $user = DB::table('Wo_Users')
                        ->where('user_id', $adminRecord->user_id)
                        ->first();
                    
                    if ($user) {
                        $userName = $user->name ?? '';
                        if (empty($userName)) {
                            $firstName = $user->first_name ?? '';
                            $lastName = $user->last_name ?? '';
                            $userName = trim($firstName . ' ' . $lastName);
                        }
                        if (empty($userName)) {
                            $userName = $user->username ?? 'Unknown User';
                        }

                        $admins[] = [
                            'user_id' => $user->user_id,
                            'username' => $user->username ?? 'Unknown',
                            'name' => $userName,
                            'email' => $user->email ?? '',
                            'avatar' => $user->avatar ?? '',
                            'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
                            'verified' => (bool) ($user->verified ?? false),
                            'is_owner' => ((string) $user->user_id === (string) $page->user_id),
                            'added_at' => isset($adminRecord->time) ? date('c', $adminRecord->time) : null,
                        ];
                    }
                }
            }

            // Always include the page owner in the admins list
            $owner = DB::table('Wo_Users')
                ->where('user_id', $page->user_id)
                ->first();
            
            if ($owner) {
                $ownerName = $owner->name ?? '';
                if (empty($ownerName)) {
                    $firstName = $owner->first_name ?? '';
                    $lastName = $owner->last_name ?? '';
                    $ownerName = trim($firstName . ' ' . $lastName);
                }
                if (empty($ownerName)) {
                    $ownerName = $owner->username ?? 'Unknown User';
                }

                // Check if owner is already in the list
                $ownerInList = false;
                foreach ($admins as $admin) {
                    if ((string) $admin['user_id'] === (string) $page->user_id) {
                        $ownerInList = true;
                        break;
                    }
                }

                if (!$ownerInList) {
                    array_unshift($admins, [
                        'user_id' => $owner->user_id,
                        'username' => $owner->username ?? 'Unknown',
                        'name' => $ownerName,
                        'email' => $owner->email ?? '',
                        'avatar' => $owner->avatar ?? '',
                        'avatar_url' => $owner->avatar ? asset('storage/' . $owner->avatar) : null,
                        'verified' => (bool) ($owner->verified ?? false),
                        'is_owner' => true,
                        'added_at' => null,
                    ]);
                }
            }

            return response()->json([
                'api_status' => 200,
                'page_id' => $page->page_id,
                'page_name' => $page->page_name,
                'admins' => $admins,
                'total_admins' => count($admins),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'api_status' => 500,
                'errors' => [
                    'error_id' => 500,
                    'error_text' => 'Failed to get page admins: ' . $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Get file URL for avatar/cover images
     * Returns URL even if file doesn't exist (for newly uploaded files)
     * 
     * @param string|null $filePath
     * @return string|null
     */
    private function getFileUrl(?string $filePath): ?string
    {
        if (empty($filePath) || trim($filePath) === '') {
            return null;
        }

        $trimmedPath = trim($filePath);

        // If path is already a full URL (starts with http:// or https://), return it as-is
        if (str_starts_with($trimmedPath, 'http://') || str_starts_with($trimmedPath, 'https://')) {
            return $trimmedPath;
        }

        // Normalize path: remove leading slashes to prevent double slashes in URL
        $normalizedPath = ltrim($trimmedPath, '/');

        // If path is empty after normalization, return null
        if (empty($normalizedPath)) {
            return null;
        }

        // For uploaded files (page_avatar_*, page_cover_*), always return URL
        // These are newly uploaded files that should exist
        if (str_contains($normalizedPath, 'page_avatar_') || str_contains($normalizedPath, 'page_cover_')) {
            return asset('storage/' . $normalizedPath);
        }

        // Check if file exists in storage
        if (Storage::disk('public')->exists($normalizedPath)) {
            return asset('storage/' . $normalizedPath);
        }

        // For default images (d-page.jpg, d-cover.jpg, etc.), return null if they don't exist
        // These are expected to exist, but if they don't, return null to avoid 404
        $defaultImages = ['d-page.jpg', 'd-cover.jpg', 'cover.jpg', 'd-avatar.jpg', 'f-avatar.jpg'];
        $filename = basename($normalizedPath);
        
        if (in_array($filename, $defaultImages)) {
            // For default images, only return URL if file exists
            return null;
        }

        // For paths starting with 'images/', return as public asset (not in storage)
        if (str_starts_with($normalizedPath, 'images/')) {
            return asset($normalizedPath);
        }

        // For any other path that starts with 'upload/', return URL anyway
        // This handles any uploaded files that might not match the exact pattern
        if (str_starts_with($normalizedPath, 'upload/')) {
            return asset('storage/' . $normalizedPath);
        }

        // For any other path, return URL anyway (might be a valid file)
        return asset('storage/' . $normalizedPath);
    }
}


