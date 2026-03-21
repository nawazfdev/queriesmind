<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PlatformCustomerController;
use App\Http\Controllers\PlatformSettingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WebsiteController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['tenant', 'throttle:tenant-api'])->group(function () {
    Route::post('/chat', [ChatController::class, 'store']);
});

Route::middleware(['auth:api'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware(['auth:api', 'tenant', 'throttle:tenant-api'])->group(function () {
    Route::get('/chatbots', [ChatbotController::class, 'index']);
    Route::post('/chatbots', [ChatbotController::class, 'store']);
    Route::get('/chatbots/{chatbot}', [ChatbotController::class, 'show']);
    Route::get('/chatbots/{chatbot}/playground', [ChatbotController::class, 'playground']);
    Route::get('/chatbots/{chatbot}/training', [ChatbotController::class, 'training']);
    Route::put('/chatbots/{chatbot}/training', [ChatbotController::class, 'updateTraining']);
    Route::get('/chatbots/{chatbot}/settings', [ChatbotController::class, 'settings']);
    Route::put('/chatbots/{chatbot}/settings', [ChatbotController::class, 'updateSettings']);
    Route::get('/chatbots/{chatbot}/appearance', [ChatbotController::class, 'appearance']);
    Route::put('/chatbots/{chatbot}/appearance', [ChatbotController::class, 'updateAppearance']);
    Route::get('/chatbots/{chatbot}/embed', [ChatbotController::class, 'embed']);
    Route::put('/chatbots/{chatbot}/embed', [ChatbotController::class, 'updateEmbed']);

    Route::post('/upload-document', [DocumentController::class, 'upload']);
    Route::post('/train-text', [DocumentController::class, 'storeText']);
    Route::post('/add-website', [WebsiteController::class, 'add']);
    Route::get('/analytics', [AnalyticsController::class, 'index']);
    Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout']);
});

Route::prefix('admin')->middleware(['auth:api', 'role:super_admin'])->group(function () {
    Route::get('/customers', [PlatformCustomerController::class, 'index']);
    Route::get('/customers/{tenant}', [PlatformCustomerController::class, 'show']);
    Route::get('/settings', [PlatformSettingController::class, 'index']);
    Route::put('/settings', [PlatformSettingController::class, 'update']);
});
