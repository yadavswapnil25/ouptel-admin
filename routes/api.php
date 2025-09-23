<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Include versioned API routes
Route::prefix('v1')->group(function () {
    require base_path('routes/api/v1.php');
});


