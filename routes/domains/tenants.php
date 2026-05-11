<?php

use App\Domain\Tenants\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/tenants', [TenantController::class, 'index']);
});
