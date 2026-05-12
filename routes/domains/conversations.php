<?php

use App\Domain\Conversations\Controllers\ConversationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'current.company'])->group(function () {
    Route::get('/attendants', [ConversationController::class, 'attendants']);
    Route::get('/queue', [ConversationController::class, 'queue']);
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
    Route::post('/conversations/{conversation}/assign-me', [ConversationController::class, 'assignMe']);
    Route::post('/conversations/{conversation}/assign-user', [ConversationController::class, 'assignUser']);
    Route::post('/conversations/{conversation}/transfer-sector', [ConversationController::class, 'transferSector']);
    Route::post('/conversations/{conversation}/send-message', [ConversationController::class, 'sendMessage']);
    Route::post('/conversations/{conversation}/close', [ConversationController::class, 'close']);
    Route::post('/conversations/{conversation}/reopen', [ConversationController::class, 'reopen']);
});
