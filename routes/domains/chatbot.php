<?php

use App\Domain\Chatbot\Controllers\BotFlowController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'current.company'])->group(function () {
    Route::get('/bot-flows', [BotFlowController::class, 'index']);
    Route::post('/bot-flows', [BotFlowController::class, 'store']);
    Route::get('/bot-flows/{botFlow}', [BotFlowController::class, 'show']);
    Route::put('/bot-flows/{botFlow}', [BotFlowController::class, 'update']);
    Route::delete('/bot-flows/{botFlow}', [BotFlowController::class, 'destroy']);
});
