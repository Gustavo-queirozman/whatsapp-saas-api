<?php

use App\Domain\Tags\Controllers\TagController;
use App\Domain\Tags\Controllers\TagRelationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'current.company'])->group(function () {
    Route::apiResource('tags', TagController::class);

    Route::post('/contacts/{contact}/tags', [TagRelationController::class, 'attachToContact']);
    Route::delete('/contacts/{contact}/tags/{tag}', [TagRelationController::class, 'detachFromContact']);

    Route::post('/conversations/{conversation}/tags', [TagRelationController::class, 'attachToConversation']);
    Route::delete('/conversations/{conversation}/tags/{tag}', [TagRelationController::class, 'detachFromConversation']);
});
