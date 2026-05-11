<?php

use App\Domain\Companies\Controllers\CompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'current.company'])->group(function () {
    Route::get('/companies', [CompanyController::class, 'index']);
});
