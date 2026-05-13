<?php

use App\Domain\Dashboard\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'current.company'])->group(function () {
    Route::get('/dashboard/overview', [DashboardController::class, 'overview']);
});
