<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TTSController;
use App\Http\Controllers\AdVideoController;
use App\Http\Controllers\CreditPurchaseController;

// Auth required
Route::middleware('auth:web')->group(function () {
    // Voiceover Studio
    Route::post('/generate-audio', [TTSController::class, 'generate'])->middleware('throttle:tts');
    
    // Ad Video Studio
    Route::get('/ad-video/library', [AdVideoController::class, 'library']);
    Route::post('/ad-video/upload', [AdVideoController::class, 'uploadMedia']);
    Route::post('/ad-video/{job}/script', [AdVideoController::class, 'generateScript']);
    Route::post('/ad-video/{job}/generate', [AdVideoController::class, 'generateVideo']);
    Route::get('/ad-video/{job}/status', [AdVideoController::class, 'checkStatus']);

    // Credit Purchases
    Route::post('/credit-purchase', [CreditPurchaseController::class, 'store']);
    Route::get('/credit-purchase/history', [CreditPurchaseController::class, 'history']);
    Route::get('/credit-purchase/qr', [CreditPurchaseController::class, 'qrCode']);
});
