<?php

use App\Domain\WhatsApp\Controllers\EvolutionWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/evolution', EvolutionWebhookController::class)
    ->middleware('evolution.webhook');

Route::prefix('v1')->group(function () {
    require base_path('routes/domains/ai.php');
    require base_path('routes/domains/system.php');
    require base_path('routes/domains/auth.php');
    require base_path('routes/domains/campaigns.php');
    require base_path('routes/domains/chatbot.php');
    require base_path('routes/domains/companies.php');
    require base_path('routes/domains/conversations.php');
    require base_path('routes/domains/sectors.php');
    require base_path('routes/domains/tags.php');
    require base_path('routes/domains/whatsapp.php');
});
