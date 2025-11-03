<?php

use App\Http\Controllers\Api\CardGeneratorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Generate card from raw data
    Route::post('/cards/generate', [CardGeneratorController::class, 'generate']);
    
    // Get card details
    Route::get('/cards/{orderNumber}', [CardGeneratorController::class, 'show']);
    
    // Upload background image
    Route::post('/cards/upload-background', [CardGeneratorController::class, 'uploadBackground']);
    
    // Upload user photos
    Route::post('/cards/upload-photo', [CardGeneratorController::class, 'uploadPhoto']);
});