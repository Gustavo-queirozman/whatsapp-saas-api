<?php

use App\Domain\Queues\Controllers\SectorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'current.company'])->group(function () {
    Route::apiResource('sectors', SectorController::class);
    Route::post('/sectors/{sector}/users', [SectorController::class, 'attachUser']);
    Route::delete('/sectors/{sector}/users/{userId}', [SectorController::class, 'detachUser']);
    Route::get('/sectors/{sector}/queue', [SectorController::class, 'queue']);
});
