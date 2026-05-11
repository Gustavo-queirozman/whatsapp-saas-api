<?php

use App\Domain\WhatsApp\Controllers\WhatsappInstanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'current.company'])->group(function () {
    Route::get('/whatsapp-instances', [WhatsappInstanceController::class, 'index']);
    Route::post('/whatsapp-instances', [WhatsappInstanceController::class, 'store']);
    Route::get('/whatsapp-instances/{whatsappInstance}', [WhatsappInstanceController::class, 'show']);
    Route::get('/whatsapp-instances/{whatsappInstance}/qrcode', [WhatsappInstanceController::class, 'qrcode']);
    Route::post('/whatsapp-instances/{whatsappInstance}/disconnect', [WhatsappInstanceController::class, 'disconnect']);
    Route::delete('/whatsapp-instances/{whatsappInstance}', [WhatsappInstanceController::class, 'destroy']);
});
