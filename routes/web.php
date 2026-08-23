<?php

use App\Http\Controllers\UserAchievementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/users/{user}/achievements', UserAchievementController::class)
    ->name('users.achievements');
