<?php

use App\Domain\Conversations\Controllers\ConversationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'current.company'])->group(function () {
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
    Route::post('/conversations/{conversation}/send-message', [ConversationController::class, 'sendMessage']);
    Route::post('/conversations/{conversation}/close', [ConversationController::class, 'close']);
    Route::post('/conversations/{conversation}/reopen', [ConversationController::class, 'reopen']);
});
