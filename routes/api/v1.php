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

Route::get('/ping', [PingController::class, 'index']);
Route::get('/albums', [AlbumController::class, 'index']);
Route::post('/create-album', [AlbumController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);
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


