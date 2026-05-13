<?php

use App\Domain\Campaigns\Controllers\CampaignController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'current.company'])->group(function () {
    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::post('/campaigns', [CampaignController::class, 'store']);
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show']);
    Route::put('/campaigns/{campaign}', [CampaignController::class, 'update']);
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy']);
    Route::post('/campaigns/{campaign}/contacts', [CampaignController::class, 'importContacts']);
    Route::post('/campaigns/{campaign}/schedule', [CampaignController::class, 'schedule']);
    Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause']);
    Route::post('/campaigns/{campaign}/resume', [CampaignController::class, 'resume']);
});
