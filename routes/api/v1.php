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
use App\Http\Controllers\Api\V1\BlogsController;
use App\Http\Controllers\Api\V1\ProductsController;
use App\Http\Controllers\Api\V1\DirectoryController;
use App\Http\Controllers\Api\V1\EventsController;
use App\Http\Controllers\Api\V1\GamesController;
use App\Http\Controllers\Api\V1\ForumsController;
use App\Http\Controllers\Api\V1\JobsController;
use App\Http\Controllers\Api\V1\OffersController;
use App\Http\Controllers\Api\V1\FriendsController;
use App\Http\Controllers\Api\V1\CommonThingsController;
use App\Http\Controllers\Api\V1\FundingsController;
use App\Http\Controllers\Api\V1\NewFeedController;
use App\Http\Controllers\Api\V1\PeopleFollowController;

Route::get('/ping', [PingController::class, 'index']);
Route::get('/albums', [AlbumController::class, 'index']);
Route::post('/create-album', [AlbumController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);
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
Route::post('/groups', [GroupsController::class, 'store']);
Route::get('/groups/meta', [GroupsController::class, 'meta']);
Route::get('/pages', [PagesController::class, 'index']);
Route::get('/pages/meta', [PagesController::class, 'meta']);
Route::post('/pages', [PagesController::class, 'store']);
Route::get('/blogs', [BlogsController::class, 'index']);
Route::get('/blogs/meta', [BlogsController::class, 'meta']);
Route::get('/products', [ProductsController::class, 'index']);
Route::get('/products/meta', [ProductsController::class, 'meta']);
Route::get('/my-products', [ProductsController::class, 'my']);
Route::get('/purchased-products', [ProductsController::class, 'purchased']);
Route::post('/products', [ProductsController::class, 'store']);
Route::get('/directory', [DirectoryController::class, 'index']);
Route::get('/events', [EventsController::class, 'index']);
Route::post('/events', [EventsController::class, 'store']);
Route::get('/events/going', [EventsController::class, 'going']);
Route::get('/events/invited', [EventsController::class, 'invited']);
Route::get('/events/interested', [EventsController::class, 'interested']);
Route::get('/my-events', [EventsController::class, 'mine']);
Route::get('/games', [GamesController::class, 'index']);
Route::post('/games', [GamesController::class, 'store']);

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

// New Feed routes (mimics WoWonder requests.php functionality)
Route::post('/new-feed/update-order', [NewFeedController::class, 'updateOrderBy']);
Route::get('/new-feed', [NewFeedController::class, 'getFeed']);
Route::get('/new-feed/types', [NewFeedController::class, 'getFeedTypes']);

// People Follow routes (mimics WoWonder requests.php?f=update_order_by&type=1)
Route::post('/people-follow/update-order', [PeopleFollowController::class, 'updateOrderBy']);
Route::get('/people-follow/feed', [PeopleFollowController::class, 'getPeopleFollowFeed']);
Route::get('/people-follow/following', [PeopleFollowController::class, 'getFollowing']);
Route::get('/people-follow/types', [PeopleFollowController::class, 'getPeopleFollowTypes']);
Route::post('/people-follow/follow', [PeopleFollowController::class, 'followUser']);
Route::post('/people-follow/{userId}/unfollow', [PeopleFollowController::class, 'unfollowUser']);


