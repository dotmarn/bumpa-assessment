<?php

use App\Http\Controllers\Api\PaystackWebhookController;
use App\Http\Controllers\Api\PurchaseController;
use Illuminate\Support\Facades\Route;

Route::post('/users/{user}/purchase', PurchaseController::class)->name('users.purchase');
Route::post('/webhooks/paystack', PaystackWebhookController::class)->name('paystack.webhook');
