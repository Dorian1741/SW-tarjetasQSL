<?php

use App\Http\Controllers\AdminCallsignController;
use App\Http\Controllers\CallsignController;
use App\Http\Controllers\CallsignLookupController;
use App\Http\Controllers\CallsignReviewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperatorProfileController;
use App\Http\Controllers\OperatorSocialController;
use App\Http\Controllers\OwnershipRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QslCardController;
use App\Http\Controllers\QsoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// La API SPA comparte sesiones y CSRF del grupo web; Sanctum autentica las cookies.
Route::prefix('api/v1')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::middleware('operational')->group(function () {
        Route::post('admin/callsigns/{callsign}/review', [CallsignReviewController::class, 'store']);
        Route::get('callsigns', [CallsignController::class, 'index']);
        Route::post('callsigns', [CallsignController::class, 'store']);
    });
});

Route::get('api/v1/public/qsl-cards/{card:public_id}', [QslCardController::class, 'verify'])->middleware('throttle:60,1')->name('qsl.verify');
Route::prefix('api/v1')->middleware(['auth:sanctum', 'operational', 'throttle:120,1'])->group(function () {
    Route::patch('callsigns/{callsign}/primary', [CallsignController::class, 'update']);
    Route::get('admin/callsigns', [AdminCallsignController::class, 'index']);
    Route::delete('admin/callsigns/{callsign}/reservation', [AdminCallsignController::class, 'destroy']);
    Route::apiResource('qsos', QsoController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::get('matches', [MatchController::class, 'index']);
    Route::patch('matches/{match}', [MatchController::class, 'update']);
    Route::post('matches/{match}/qsl-cards', [QslCardController::class, 'store']);
    Route::get('qsl-cards', [QslCardController::class, 'index']);
    Route::get('qsl-cards/{card:public_id}', [QslCardController::class, 'show']);
    Route::get('qsl-cards/{card:public_id}/download', [QslCardController::class, 'download']);
    Route::get('dashboard', DashboardController::class);
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/{notification}', [NotificationController::class, 'update']);
});

// Directorio: resultados externos privados; publicación social explícita y revisada.
Route::post('api/v1/callsigns/lookup', CallsignLookupController::class)->middleware(['auth:sanctum', 'verified', 'throttle:callbook']);
Route::prefix('api/v1')->middleware(['auth:sanctum', 'operational', 'throttle:60,1'])->group(function () {
    Route::get('operator-profile', [OperatorProfileController::class, 'mine']);
    Route::put('operator-profile', [OperatorProfileController::class, 'update']);
    Route::post('operator-profile/photo', [OperatorProfileController::class, 'upload']);
    Route::post('operator-posts', [OperatorSocialController::class, 'store']);
    Route::delete('operator-posts/{post}', [OperatorSocialController::class, 'destroy']);
    Route::put('operators/follow/{callsign}', [OperatorSocialController::class, 'follow'])->where('callsign', '[A-Za-z0-9/]+');
    Route::delete('operators/follow/{callsign}', [OperatorSocialController::class, 'unfollow'])->where('callsign', '[A-Za-z0-9/]+');
    Route::post('callsigns/{callsign}/ownership-requests', [OwnershipRequestController::class, 'store']);
    Route::get('callsigns/{callsign}/ownership-requests', [OwnershipRequestController::class, 'index']);
});
Route::prefix('api/v1/public')->middleware('throttle:60,1')->group(function () {
    Route::get('operator-photos/{profile}', [OperatorProfileController::class, 'photo']);
    Route::get('operator-history/{callsign}', [OperatorProfileController::class, 'history'])->where('callsign', '[A-Za-z0-9/]+');
    Route::get('operator-posts/{callsign}', [OperatorSocialController::class, 'posts'])->where('callsign', '[A-Za-z0-9/]+');
    Route::get('operators/{callsign}', [OperatorProfileController::class, 'show'])->where('callsign', '[A-Za-z0-9/]+');
});
