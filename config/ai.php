<?php

return [
    'default' => env('AI_PROVIDER', 'openai'),

    'conversation_history_limit' => (int) env('AI_CONVERSATION_HISTORY_LIMIT', 30),

    'providers' => [
        'openai' => [
            'timeout' => (int) env('OPENAI_TIMEOUT', 30),
            'models' => [
                'summary' => env('AI_OPENAI_SUMMARY_MODEL', env('OPENAI_MODEL', 'gpt-4.1-mini')),
                'suggest_reply' => env('AI_OPENAI_SUGGEST_REPLY_MODEL', env('OPENAI_MODEL', 'gpt-4.1-mini')),
                'classify_intent' => env('AI_OPENAI_CLASSIFY_INTENT_MODEL', env('OPENAI_MODEL', 'gpt-4.1-mini')),
            ],
        ],
    ],
];
