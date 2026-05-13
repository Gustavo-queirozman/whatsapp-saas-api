<?php

use App\Domain\Ai\Controllers\ConversationAiController;
use App\Domain\Ai\Controllers\MessageAiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'current.company'])->group(function () {
    Route::post('/conversations/{conversation}/ai/summary', [ConversationAiController::class, 'summary']);
    Route::post('/conversations/{conversation}/ai/suggest-reply', [ConversationAiController::class, 'suggestReply']);
    Route::post('/messages/{message}/ai/classify-intent', [MessageAiController::class, 'classifyIntent']);
});
