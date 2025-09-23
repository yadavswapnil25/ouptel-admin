<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\AlbumController;
use App\Http\Controllers\Api\V1\AuthController;

Route::get('/ping', [PingController::class, 'index']);
Route::get('/albums', [AlbumController::class, 'index']);
Route::post('/create-album', [AlbumController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);


