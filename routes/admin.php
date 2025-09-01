<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SettingsController;

// Admin routes - DISABLED (using Filament instead)
// All admin functionality is now handled by Filament
// Route::prefix('admin')->group(function () {
//     // All routes disabled to prevent conflicts with Filament
// });
