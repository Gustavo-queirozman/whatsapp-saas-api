<?php

use App\Domain\Crm\Controllers\DealController;
use App\Domain\Crm\Controllers\PipelineController;
use App\Domain\Crm\Controllers\PipelineStageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'current.company'])->group(function () {
    Route::apiResource('pipelines', PipelineController::class);
    Route::apiResource('pipeline-stages', PipelineStageController::class);
    Route::apiResource('deals', DealController::class);
    Route::post('/deals/{deal}/move-stage', [DealController::class, 'moveStage']);
});
