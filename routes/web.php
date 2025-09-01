<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Temporary route to create admin user
Route::get('/create-admin', function () {
    try {
        $user = \App\Models\User::updateOrCreate(
            ['email' => 'admin@ouptel.com'],
            [
                'name' => 'Ouptel Admin',
                'email' => 'admin@ouptel.com',
                'password' => bcrypt('admin123'),
                'email_verified_at' => now(),
            ]
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Admin user created successfully!',
            'credentials' => [
                'email' => 'admin@ouptel.com',
                'password' => 'admin123'
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error creating admin user: ' . $e->getMessage()
        ]);
    }
});