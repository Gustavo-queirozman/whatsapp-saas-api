<?php

return [
    'base_url' => env('EVOLUTION_BASE_URL'),
    'api_key' => env('EVOLUTION_API_KEY'),
    'default_integration' => env('EVOLUTION_DEFAULT_INTEGRATION', 'WHATSAPP-BAILEYS'),
    'timeout' => (int) env('EVOLUTION_TIMEOUT', 15),
];
