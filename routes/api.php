<?php

use App\Http\Controllers\Api\PurchaseController;
use Illuminate\Support\Facades\Route;

Route::post('/users/{user}/purchase', PurchaseController::class)->name('users.purchase');
