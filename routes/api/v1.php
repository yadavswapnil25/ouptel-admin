<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\AlbumController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\WatchController;
use App\Http\Controllers\Api\V1\ReelsController;
use App\Http\Controllers\Api\V1\SavedPostsController;
use App\Http\Controllers\Api\V1\PopularPostsController;
use App\Http\Controllers\Api\V1\MemoriesController;
use App\Http\Controllers\Api\V1\PokeController;
use App\Http\Controllers\Api\V1\GroupsController;
use App\Http\Controllers\Api\V1\PagesController;
use App\Http\Controllers\Api\V1\CountriesController;
use App\Http\Controllers\Api\V1\BlogsController;
use App\Http\Controllers\Api\V1\ProductsController;
use App\Http\Controllers\Api\V1\DirectoryController;
use App\Http\Controllers\Api\V1\EventsController;
use App\Http\Controllers\Api\V1\FeelingsController;
use App\Http\Controllers\Api\V1\GamesController;
use App\Http\Controllers\Api\V1\ForumsController;
use App\Http\Controllers\Api\V1\JobsController;
use App\Http\Controllers\Api\V1\OffersController;
use App\Http\Controllers\Api\V1\FriendsController;
use App\Http\Controllers\Api\V1\CommonThingsController;
use App\Http\Controllers\Api\V1\FundingsController;
use App\Http\Controllers\Api\V1\NewFeedController;
use App\Http\Controllers\Api\V1\PeopleFollowController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\FollowController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PrivacyController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\SocialLinksController;
use App\Http\Controllers\Api\V1\DesignController;
use App\Http\Controllers\Api\V1\BlockedUsersController;
use App\Http\Controllers\Api\V1\NotificationSettingsController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\MyInformationController;
use App\Http\Controllers\Api\V1\DeleteAccountController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\PollController;
use App\Http\Controllers\Api\V1\StoriesController;
use App\Http\Controllers\Api\V1\NotificationsController;
use App\Http\Controllers\Api\V1\ShareController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\SubscriptionsController;
use App\Http\Controllers\Api\V1\AnnouncementsController;
use App\Http\Controllers\Api\V1\AccountVerificationController;
use App\Http\Controllers\Api\V1\ReportController;

Route::get('/ping', [PingController::class, 'index']);
Route::get('/albums', [AlbumController::class, 'index']);
Route::post('/create-album', [AlbumController::class, 'store']);

// Authentication routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/signup', [AuthController::class, 'signup']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
Route::post('/check-username', [AuthController::class, 'checkUsername']);
Route::post('/check-email', [AuthController::class, 'checkEmail']);
Route::get('/debug-users', [AuthController::class, 'debugUsers']);
Route::get('/watch', [WatchController::class, 'index']);
Route::get('/reels', [ReelsController::class, 'index']);
Route::get('/saved-posts', [SavedPostsController::class, 'index']);
Route::get('/popular-posts', [PopularPostsController::class, 'index']);
Route::get('/memories', [MemoriesController::class, 'index']);
Route::get('/pokes', [PokeController::class, 'index']);
Route::post('/pokes', [PokeController::class, 'store']);
Route::delete('/pokes/{pokeId}', [PokeController::class, 'destroy']);
Route::get('/groups', [GroupsController::class, 'index']);
Route::get('/groups/meta', [GroupsController::class, 'meta']); // Must be before /groups/{id} to avoid route conflict
Route::post('/groups/join', [GroupsController::class, 'joinGroup']); // Join/leave group (old API: requests.php?f=join_group)
Route::get('/groups/{id}', [GroupsController::class, 'show']); // Get single group by ID
Route::post('/groups', [GroupsController::class, 'store']);
Route::get('/pages', [PagesController::class, 'index']);
Route::get('/pages/meta', [PagesController::class, 'meta']);
Route::get('/pages/search', [PagesController::class, 'search']); // Search pages
Route::get('/pages/{id}/posts', [PagesController::class, 'getPosts']); // Get posts for a page (must be before /pages/{id})
Route::get('/pages/{id}/analytics', [PagesController::class, 'analytics']); // Get page analytics (old API: ajax_loading.php?link1=page-setting&page={page_name}&link3=analytics) - Must be before /pages/{id}
Route::get('/pages/{id}/admins', [PagesController::class, 'getPageAdmins']); // Get page admins (old API: ajax_loading.php?link1=page-setting&page={page_name}&link3=admins) - Must be before /pages/{id}
Route::get('/pages/{id}', [PagesController::class, 'show']); // Get page by ID
Route::post('/pages', [PagesController::class, 'store']);
Route::put('/pages/{id}', [PagesController::class, 'update']); // Update page (Edit page)
Route::patch('/pages/{id}', [PagesController::class, 'update']); // Update page (Edit page) - alternative method
Route::post('/pages/like', [PagesController::class, 'likePage']); // Like/unlike page (old API: requests.php?f=like_page)
Route::post('/pages/delete', [PagesController::class, 'destroy']); // Delete page (old API: v2/endpoints/delete_page.php)
Route::get('/blogs', [BlogsController::class, 'index']);
Route::get('/blogs/meta', [BlogsController::class, 'meta']);
Route::get('/blogs/categories', [BlogsController::class, 'categories']); // Get blog categories with metadata
Route::get('/blogs/my-articles', [BlogsController::class, 'getMyArticles']); // 5.1 Get My Articles (old API: ajax_loading.php?link1=my-blogs)
Route::get('/blogs/{id}', [BlogsController::class, 'show']); // Get blog/article by ID (must be after specific routes)
Route::post('/blogs', [BlogsController::class, 'createArticle']); // 5.2 Create Article
Route::put('/blogs/{id}', [BlogsController::class, 'updateArticle']); // 5.3 Update My Article
Route::post('/blogs/{id}', [BlogsController::class, 'updateArticle']); // 5.3 Update My Article (POST alternative)
Route::delete('/blogs/{id}', [BlogsController::class, 'deleteArticle']); // 5.4 Delete My Article
Route::post('/blogs/{id}/delete', [BlogsController::class, 'deleteArticle']); // 5.4 Delete My Article (POST alternative)
Route::get('/products', [ProductsController::class, 'index']);
Route::get('/products/meta', [ProductsController::class, 'meta']);
Route::get('/my-products', [ProductsController::class, 'my']);
Route::get('/purchased-products', [ProductsController::class, 'purchased']);
Route::post('/market/buy', [ProductsController::class, 'buy']); // Buy products from cart (old API: market.php?type=buy)
Route::post('/products', [ProductsController::class, 'store']);
Route::get('/directory', [DirectoryController::class, 'index']);
Route::get('/events', [EventsController::class, 'index']);
Route::post('/events', [EventsController::class, 'store']);
Route::get('/events/going', [EventsController::class, 'going']);
Route::get('/events/invited', [EventsController::class, 'invited']);
Route::get('/events/interested', [EventsController::class, 'interested']);
Route::get('/my-events', [EventsController::class, 'mine']);
Route::post('/events/go', [EventsController::class, 'goEvent']); // Go/not going to event (old API: requests.php?f=go_event)
Route::get('/games', [GamesController::class, 'index']); // Legacy endpoint
Route::post('/games', [GamesController::class, 'store']); // Legacy endpoint
Route::post('/games/handle', [GamesController::class, 'handle']); // Unified endpoint (old API: games.php with type parameter)
Route::post('/games/get', [GamesController::class, 'getAll']); // Get all games (old API: games.php?type=get)
Route::post('/games/get-my', [GamesController::class, 'getMy']); // Get my games (old API: games.php?type=get_my)
Route::post('/games/add-to-my', [GamesController::class, 'addToMy']); // Add game to my games (old API: games.php?type=add_to_my)
Route::post('/games/search', [GamesController::class, 'search']); // Search games (old API: games.php?type=search)
Route::post('/games/popular', [GamesController::class, 'popular']); // Get popular games (old API: games.php?type=popular)

// Forum routes
Route::get('/forums', [ForumsController::class, 'index']);
Route::post('/forums', [ForumsController::class, 'store']);
Route::get('/forums/meta', [ForumsController::class, 'meta']);
Route::get('/forums/{id}', [ForumsController::class, 'show']);
Route::get('/forums/{id}/topics', [ForumsController::class, 'topics']);
Route::post('/forums/{id}/topics', [ForumsController::class, 'createTopic']);
Route::get('/forums/{forumId}/topics/{topicId}/replies', [ForumsController::class, 'topicReplies']);
Route::post('/forums/{forumId}/topics/{topicId}/replies', [ForumsController::class, 'createReply']);

// Forum menu routes
Route::get('/forums/{id}/members', [ForumsController::class, 'members']);
Route::get('/forums/search', [ForumsController::class, 'search']);
Route::get('/my-threads', [ForumsController::class, 'myThreads']);
Route::get('/my-messages', [ForumsController::class, 'myMessages']);

// Job routes
Route::get('/jobs', [JobsController::class, 'index']);
Route::post('/jobs', [JobsController::class, 'store']);
Route::get('/jobs/meta', [JobsController::class, 'meta']);
Route::get('/jobs/{id}', [JobsController::class, 'show']);
Route::get('/jobs/{id}/applications', [JobsController::class, 'applications']);
Route::post('/jobs/{id}/apply', [JobsController::class, 'apply']);

// Job menu routes
Route::get('/jobs/search', [JobsController::class, 'search']);
Route::get('/my-applications', [JobsController::class, 'myApplications']);

// Offer routes
Route::get('/offers', [OffersController::class, 'index']);
Route::post('/offers', [OffersController::class, 'store']);
Route::get('/offers/meta', [OffersController::class, 'meta']);
Route::get('/offers/{id}', [OffersController::class, 'show']);
Route::get('/offers/{id}/applications', [OffersController::class, 'applications']);
Route::post('/offers/{id}/apply', [OffersController::class, 'apply']);

// Offer menu routes
Route::get('/offers/search', [OffersController::class, 'search']);
Route::get('/my-offer-applications', [OffersController::class, 'myApplications']);

// Friend routes
Route::get('/friends', [FriendsController::class, 'index']);
Route::get('/friends/search', [FriendsController::class, 'search']);
Route::get('/friends/requests', [FriendsController::class, 'requests']);
Route::post('/friends/send-request', [FriendsController::class, 'sendRequest']);
Route::post('/friends/requests/{id}/accept', [FriendsController::class, 'acceptRequest']);
Route::post('/friends/requests/{id}/decline', [FriendsController::class, 'declineRequest']);
Route::delete('/friends/{id}', [FriendsController::class, 'removeFriend']);
Route::post('/friends/{id}/block', [FriendsController::class, 'blockUser']);
Route::post('/friends/{id}/unblock', [FriendsController::class, 'unblockUser']);
Route::get('/friends/suggested', [FriendsController::class, 'suggested']);
Route::post('/friends/update-sidebar-users', [FriendsController::class, 'updateSidebarUsers']); // Old API: requests.php?f=update_sidebar_users

// Common Things routes
Route::get('/common-things', [CommonThingsController::class, 'index']);
Route::post('/common-things', [CommonThingsController::class, 'store']);
Route::get('/common-things/{id}', [CommonThingsController::class, 'show']);
Route::put('/common-things/{id}', [CommonThingsController::class, 'update']);
Route::delete('/common-things/{id}', [CommonThingsController::class, 'destroy']);
Route::get('/common-things/search', [CommonThingsController::class, 'search']);
Route::get('/common-things/categories', [CommonThingsController::class, 'categories']);
Route::get('/my-common-things', [CommonThingsController::class, 'myThings']);
Route::get('/common-things/category/{categoryId}', [CommonThingsController::class, 'byCategory']);

// Funding routes
Route::get('/fundings', [FundingsController::class, 'index']);
Route::post('/fundings', [FundingsController::class, 'store']);
Route::get('/fundings/{id}', [FundingsController::class, 'show']);
Route::put('/fundings/{id}', [FundingsController::class, 'update']);
Route::delete('/fundings/{id}', [FundingsController::class, 'destroy']);
Route::get('/fundings/search', [FundingsController::class, 'search']);
Route::get('/fundings/categories', [FundingsController::class, 'categories']);
Route::get('/my-fundings', [FundingsController::class, 'myFundings']);
Route::get('/fundings/category/{categoryId}', [FundingsController::class, 'byCategory']);
Route::post('/fundings/{id}/contribute', [FundingsController::class, 'contribute']);
Route::get('/my-contributions', [FundingsController::class, 'myContributions']);
Route::get('/fundings-debug', [FundingsController::class, 'debug']);

Route::post('/new-feed/update-order', [NewFeedController::class, 'updateOrderBy']);
Route::get('/new-feed', [NewFeedController::class, 'getFeed']);
Route::get('/new-feed/types', [NewFeedController::class, 'getFeedTypes']);

Route::post('/people-follow/update-order', [PeopleFollowController::class, 'updateOrderBy']);
Route::get('/people-follow/feed', [PeopleFollowController::class, 'getPeopleFollowFeed']);
Route::get('/people-follow/following', [PeopleFollowController::class, 'getFollowing']);
Route::get('/people-follow/types', [PeopleFollowController::class, 'getPeopleFollowTypes']);
Route::post('/people-follow/follow', [PeopleFollowController::class, 'followUser']);
Route::post('/people-follow/{userId}/unfollow', [PeopleFollowController::class, 'unfollowUser']);

Route::get('/feelings', [FeelingsController::class, 'index']); // Get available feelings

// Unified post creation endpoint (optimized - handles all post types via 'type' parameter)
// Use type: 'regular', 'gif', 'feeling', or 'colored' to optimize request handling
Route::post('/posts', [PostController::class, 'insertNewPost']);

// Legacy endpoints (deprecated - use POST /posts with 'type' parameter instead)
// These endpoints still work but are redirected to the unified endpoint for optimization
Route::post('/feelings/post', [FeelingsController::class, 'createFeelingPost']); // Create feeling post (use POST /posts?type=feeling)
Route::post('/posts/gif', [PostController::class, 'createGifPost']); // Create GIF post (use POST /posts?type=gif)
Route::get('/posts', [PostController::class, 'getPosts']); // Get posts with optional type filter (playing, travelling, watching, listening, feeling)
Route::get('/posts/colored', [PostController::class, 'getColoredPosts']); // Get available colored posts
Route::get('/posts/{postId}', [PostController::class, 'getPost']);
Route::post('/posts/get-data', [PostController::class, 'getPostData']); // Get post data for new tab (old API: get-post-data.php)

Route::post('/posts/{postId}/reactions', [PostController::class, 'registerReaction']);
Route::get('/posts/{postId}/reactions', [PostController::class, 'getPostReactions']);
Route::delete('/posts/{postId}/reactions', [PostController::class, 'removeReaction']);

Route::post('/posts/{postId}/comments', [CommentController::class, 'registerComment']);
Route::get('/posts/{postId}/comments', [CommentController::class, 'getComments']);
Route::put('/comments/{commentId}', [CommentController::class, 'updateComment']);
Route::delete('/comments/{commentId}', [CommentController::class, 'deleteComment']);
Route::post('/comments/{commentId}/reactions', [CommentController::class, 'registerCommentReaction']);
Route::post('/comments/{commentId}/replies', [CommentController::class, 'replyToComment']); // Reply to a comment
Route::get('/comments/{commentId}/replies', [CommentController::class, 'getReplies']); // Get replies for a comment

Route::post('/posts/{postId}/save', [PostController::class, 'savePost']);
Route::get('/posts/{postId}/saved', [PostController::class, 'checkSavedPost']);
Route::delete('/posts/{postId}/save', [PostController::class, 'unsavePost']);
Route::get('/saved-posts', [PostController::class, 'getSavedPosts']);
Route::delete('/posts/{postId}', [PostController::class, 'deletePost']); // Delete post (old API: requests.php?f=posts&s=delete_post)
Route::post('/posts/{postId}/delete', [PostController::class, 'deletePost']); // Delete post (POST alternative)
Route::post('/posts/disable-comment', [PostController::class, 'disableComment']); // Disable/enable comments (old API: requests.php?f=posts&s=disable_comment)
Route::post('/posts/hide', [PostController::class, 'hidePost']); // Hide post (old API: requests.php?f=posts&s=hide_post)

// Report routes (matching old WoWonder API: post-actions.php?action=report)
Route::post('/posts/{postId}/report', [ReportController::class, 'reportPost']); // Report/unreport post (old API: post-actions.php?action=report)
Route::get('/posts/{postId}/report-status', [ReportController::class, 'getPostReportStatus']); // Check if post is reported
Route::post('/comments/{commentId}/report', [ReportController::class, 'reportComment']); // Report/unreport comment (old API: report_comment.php)
Route::post('/users/{userId}/report', [ReportController::class, 'reportUser']); // Report/unreport user (old API: report_user.php)
Route::get('/reports/reasons', [ReportController::class, 'getReportReasons']); // Get available report reasons

// Poll routes (matching old API structure: requests.php?f=posts&s=insert_new_post with answer array)
Route::post('/polls/create', [PollController::class, 'createPoll']); // Create poll post (like insert_new_post with answer array)
Route::post('/polls/vote', [PollController::class, 'voteUp']); // Vote on poll (like vote_up.php)
Route::get('/polls/{postId}', [PollController::class, 'getPollDetails']); // Get poll details with percentages

// Stories routes (matching old API structure: requests.php?f=view_all_stories)
// IMPORTANT: Specific routes must come before routes with parameters to avoid route conflicts
Route::post('/stories/view-all', [StoriesController::class, 'viewAllStories']); // View all stories (old API: view_all_stories)
Route::post('/stories/user-stories', [StoriesController::class, 'getUserStories']); // Get user stories grouped (old API: get-user-stories.php)
Route::post('/stories/create', [StoriesController::class, 'create']); // Create story (old API: create-story.php)
Route::post('/stories/delete', [StoriesController::class, 'delete']); // Delete story (old API: delete-story.php)
Route::post('/stories/react', [StoriesController::class, 'react']); // React to story (old API: react_story.php)
Route::post('/stories/mute', [StoriesController::class, 'mute']); // Mute/unmute story (old API: mute_story.php)
Route::post('/stories/mark-seen', [StoriesController::class, 'markAsSeen']); // Mark story as seen (old API: mark_story_seen.php)
Route::post('/stories/views', [StoriesController::class, 'getStoryViews']); // Get story views (old API: get_story_views.php)
Route::post('/stories/{id}', [StoriesController::class, 'getStoryById']); // Get story by ID (old API: get_story_by_id.php) - Must be last to avoid matching specific routes

// Share routes (matching old API structure: requests.php?f=share_post_on)
Route::post('/share/post', [ShareController::class, 'sharePostOn']); // Share post on timeline/page/group (old API: share_post_on)

// Wallet routes (matching old API structure: wallet.php)
Route::post('/wallet/send', [WalletController::class, 'send']); // Send money from wallet (old API: wallet.php?type=send)
Route::post('/wallet/top-up', [WalletController::class, 'topUp']); // Top up wallet (old API: wallet.php?type=top_up)
Route::post('/wallet/pay', [WalletController::class, 'pay']); // Pay using wallet (old API: wallet.php?type=pay)
Route::get('/wallet/balance', [WalletController::class, 'getBalance']); // Get wallet balance

// Subscriptions routes
Route::get('/my-subscriptions', [SubscriptionsController::class, 'getMySubscriptions']); // Get my subscriptions (users, pages, groups)

Route::post('/users/{followingId}/follow', [FollowController::class, 'followUser']);
Route::delete('/users/{followingId}/follow', [FollowController::class, 'unfollowUser']);
Route::get('/users/{userId}/followers', [FollowController::class, 'getFollowers']);
Route::get('/users/{userId}/following', [FollowController::class, 'getFollowing']);
Route::get('/users/{userId}/follow-status', [FollowController::class, 'checkFollowStatus']);
Route::get('/follow-requests', [FollowController::class, 'getFollowRequests']);
Route::post('/users/{followerId}/accept-follow', [FollowController::class, 'acceptFollowRequest']);
Route::post('/users/{followerId}/reject-follow', [FollowController::class, 'rejectFollowRequest']);

// Settings routes (mimics old WoWonder API)
Route::get('/settings', [SettingsController::class, 'getSettings']);
Route::post('/settings/update-user-data', [SettingsController::class, 'updateUserData']);

// Profile routes (mimics old WoWonder API)
Route::get('/profile/user-data', [ProfileController::class, 'getUserData']);
Route::post('/profile/user-data', [ProfileController::class, 'getUserData']);
Route::get('/update-data', [ProfileController::class, 'updateData']);
Route::post('/update-data', [ProfileController::class, 'updateData']);
Route::get('/timeline', [ProfileController::class, 'getTimeline']); // Get user timeline (old API: ajax_loading.php?link1=timeline&u=username)
Route::post('/timeline', [ProfileController::class, 'getTimeline']); // POST alternative

// Privacy settings routes (mimics old WoWonder API)
Route::get('/privacy/settings', [PrivacyController::class, 'getPrivacySettings']);
Route::post('/privacy/settings', [PrivacyController::class, 'updatePrivacySettings']);
Route::put('/privacy/settings', [PrivacyController::class, 'updatePrivacySettings']);

// Password routes (mimics old WoWonder API)
Route::post('/password/forgot', [PasswordController::class, 'forgotPassword']); // Request password reset
Route::post('/password/reset', [PasswordController::class, 'resetPassword']); // Reset password with token
Route::post('/password/change', [PasswordController::class, 'changePassword']); // Change password (requires auth)
Route::post('/password/verify', [PasswordController::class, 'verifyCurrentPassword']); // Verify current password

// Session management routes (mimics old WoWonder API)
Route::get('/sessions', [SessionController::class, 'getSessions']);
Route::post('/sessions/delete', [SessionController::class, 'deleteSession']);
Route::post('/sessions/delete-all', [SessionController::class, 'deleteAllOtherSessions']);
Route::delete('/sessions/{id}', [SessionController::class, 'deleteSession']);

// Social links routes (mimics old WoWonder API)
Route::get('/social-links', [SocialLinksController::class, 'getSocialLinks']);
Route::post('/social-links', [SocialLinksController::class, 'updateSocialLinks']);
Route::put('/social-links', [SocialLinksController::class, 'updateSocialLinks']);

// Design settings routes (mimics old WoWonder API)
Route::get('/design/settings', [DesignController::class, 'getDesignSettings']);
Route::post('/design/avatar', [DesignController::class, 'updateAvatar']);
Route::post('/design/cover', [DesignController::class, 'updateCover']);
Route::post('/design/avatar/reset', [DesignController::class, 'resetAvatar']);
Route::post('/design/cover/reset', [DesignController::class, 'resetCover']);
Route::delete('/design/avatar', [DesignController::class, 'resetAvatar']);
Route::delete('/design/cover', [DesignController::class, 'resetCover']);

// Blocked users routes (mimics old WoWonder API)
Route::get('/blocked-users', [BlockedUsersController::class, 'getBlockedUsers']);
Route::post('/block-user', [BlockedUsersController::class, 'blockUser']);
Route::post('/users/{userId}/block', [BlockedUsersController::class, 'blockUser']);
Route::get('/users/{userId}/block-status', [BlockedUsersController::class, 'checkBlockStatus']);

// Notification settings routes (mimics old WoWonder API)
Route::get('/notifications/settings', [NotificationSettingsController::class, 'getNotificationSettings']);
Route::post('/notifications/settings', [NotificationSettingsController::class, 'updateNotificationSettings']);
Route::put('/notifications/settings', [NotificationSettingsController::class, 'updateNotificationSettings']);
Route::post('/notifications/settings/enable-all', [NotificationSettingsController::class, 'enableAllNotifications']);
Route::post('/notifications/settings/disable-all', [NotificationSettingsController::class, 'disableAllNotifications']);

// Notifications routes (matching old API structure: requests.php?f=get_notifications)
Route::post('/notifications/get', [NotificationsController::class, 'getNotifications']); // Get notifications (old API: get_notifications)
Route::post('/notifications/delete', [NotificationsController::class, 'delete']); // Delete notification (old API: notifications.php?type=delete)
Route::post('/notifications/stop-notify', [NotificationsController::class, 'stopNotify']); // Stop notify from user (old API: stop_notify.php)
Route::post('/notifications/mark-all-seen', [NotificationsController::class, 'markAllSeen']); // Mark all notifications as seen

// Address management routes (mimics old WoWonder API)
Route::get('/addresses', [AddressController::class, 'getAddresses']);
Route::get('/addresses/{id}', [AddressController::class, 'getAddressById']);
Route::post('/addresses', [AddressController::class, 'addAddress']);
Route::put('/addresses/{id}', [AddressController::class, 'updateAddress']);
Route::delete('/addresses/{id}', [AddressController::class, 'deleteAddress']);

// My information routes (mimics old WoWonder API)
Route::post('/my-information', [MyInformationController::class, 'getMyInformation']);
Route::post('/my-information/download', [MyInformationController::class, 'downloadMyInformation']);

// Delete account routes (mimics old WoWonder API)
Route::post('/account/delete', [DeleteAccountController::class, 'deleteAccount']);
Route::post('/account/delete-request', [DeleteAccountController::class, 'requestAccountDeletion']);
Route::delete('/account', [DeleteAccountController::class, 'deleteAccount']);

// Search routes (matching old API structure: search.php, search_for_posts.php, recent_search.php)
Route::post('/search', [SearchController::class, 'search']); // Main search (old API: search.php)
Route::post('/search/posts', [SearchController::class, 'searchForPosts']); // Search for posts (old API: search_for_posts.php)
Route::get('/search/recent', [SearchController::class, 'recentSearches']); // Recent searches (old API: recent_search.php)
Route::get('/search/explore', [SearchController::class, 'explore']); // Explore search (alternative endpoint)

// Countries routes (for explore page)
Route::get('/countries', [CountriesController::class, 'index']); // Get countries list
Route::get('/countries/meta', [CountriesController::class, 'meta']); // Get countries meta (for explore page filters)

// Announcements routes (matching old API structure: get-general-data.php with announcement parameter)
Route::get('/announcements/home', [AnnouncementsController::class, 'getHomeAnnouncement']); // Get home announcement (old API: Wo_GetHomeAnnouncements)
Route::post('/announcements/mark-viewed', [AnnouncementsController::class, 'markAsViewed']); // Mark announcement as viewed
Route::get('/announcements', [AnnouncementsController::class, 'getAllActive']); // Get all active announcements

// Account Verification routes (Blue & Golden Badge)
// Settings -> Verify Account feature for end users
Route::get('/verification/options', [AccountVerificationController::class, 'getVerificationOptions']); // Get available ID proof types and badge types
Route::post('/verification/submit', [AccountVerificationController::class, 'submit']); // Submit verification request
Route::get('/verification/status', [AccountVerificationController::class, 'getStatus']); // Get current verification status
Route::get('/verification/history', [AccountVerificationController::class, 'getHistory']); // Get verification history
Route::post('/verification/resubmit', [AccountVerificationController::class, 'resubmit']); // Resubmit after rejection
Route::post('/verification/cancel', [AccountVerificationController::class, 'cancel']); // Cancel pending request
Route::get('/users/{userId}/badge', [AccountVerificationController::class, 'getUserBadge']); // Get user's badge info (public)

