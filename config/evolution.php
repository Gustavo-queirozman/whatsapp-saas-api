<?php

return [
    'base_url' => env('EVOLUTION_BASE_URL'),
    'api_key' => env('EVOLUTION_API_KEY'),
    'default_integration' => env('EVOLUTION_DEFAULT_INTEGRATION', 'WHATSAPP-BAILEYS'),
    'timeout' => (int) env('EVOLUTION_TIMEOUT', 15),
    'webhook_secret' => env('EVOLUTION_WEBHOOK_SECRET'),
    'webhook_secret_header' => env('EVOLUTION_WEBHOOK_SECRET_HEADER', 'X-Evolution-Webhook-Secret'),
    'webhook_log_channel' => env('EVOLUTION_WEBHOOK_LOG_CHANNEL', 'evolution_webhooks'),
];
