<?php

use App\Http\Controllers\BranchSwitchController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\JoinController;
use App\Http\Controllers\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Public signup page for each gym.
Route::get('/join/{gym:slug}', [JoinController::class, 'show'])->name('join.show');
Route::post('/join/{gym:slug}', [JoinController::class, 'store'])->middleware('throttle:10,1')->name('join.store');
Route::get('/join/{gym:slug}/welcome', [JoinController::class, 'welcome'])->name('join.welcome');

Route::post('/webhooks/razorpay/{gym:slug}', RazorpayWebhookController::class)->name('webhooks.razorpay');

Route::middleware('auth')->group(function () {
    Route::get('/invoices/{subscription}', [DocumentController::class, 'invoice'])->name('invoices.show');
    Route::get('/receipts/{payment}', [DocumentController::class, 'receipt'])->name('receipts.show');
    Route::post('/branch-switch', BranchSwitchController::class)->name('branch.select');
});
