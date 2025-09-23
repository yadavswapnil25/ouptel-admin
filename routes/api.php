<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Simple test endpoint to verify API and CORS (versioned)
Route::prefix('v1')->group(function () {
    Route::get('/ping', function () {
        return response()->json([
            'ok' => true,
            'message' => 'pong',
            'time' => now()->toIso8601String(),
        ]);
    });
});


