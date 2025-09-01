<?php

use Illuminate\Support\Facades\Route;

// Temporary Filament routes until proper installation
Route::get('/admin/login', function () {
    return view('filament.pages.auth.login');
})->name('filament.admin.auth.login');

Route::post('/admin/login', function () {
    // Handle login logic here
    return redirect('/admin');
})->name('filament.admin.auth.login');


