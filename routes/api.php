<?php

use App\Domain\WhatsApp\Controllers\EvolutionWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/evolution', EvolutionWebhookController::class)
    ->middleware('evolution.webhook');

Route::prefix('v1')->group(function () {
    require base_path('routes/domains/system.php');
    require base_path('routes/domains/auth.php');
    require base_path('routes/domains/companies.php');
    require base_path('routes/domains/whatsapp.php');
});
