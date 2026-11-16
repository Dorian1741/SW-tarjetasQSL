<?php

use App\Http\Controllers\IntegrationQsoController;
use Illuminate\Support\Facades\Route;

Route::post('qsos', [IntegrationQsoController::class, 'store']);
Route::get('qsos', [IntegrationQsoController::class, 'index']);
Route::get('qsos/{externalId}', [IntegrationQsoController::class, 'show'])->where('externalId', '[A-Za-z0-9][A-Za-z0-9._:-]{0,99}');
