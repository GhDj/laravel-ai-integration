<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used when
    | no provider is explicitly specified. You may set this to any of the
    | providers defined in the "providers" array below.
    |
    */
    'default' => env('AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many AI providers as you wish. Each provider
    | has its own configuration options such as API keys, models, and other
    | provider-specific settings.
    |
    */
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4o'),
            'default_embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'timeout' => env('OPENAI_TIMEOUT', 30),
            'max_tokens' => env('OPENAI_MAX_TOKENS', 4096),
        ],

        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-sonnet-4-20250514'),
            'timeout' => env('ANTHROPIC_TIMEOUT', 30),
            'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 4096),
            'api_version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-1.5-pro'),
            'default_embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'text-embedding-004'),
            'timeout' => env('GEMINI_TIMEOUT', 30),
            'max_tokens' => env('GEMINI_MAX_TOKENS', 4096),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for AI API calls. This helps prevent exceeding
    | API rate limits and manage costs. Rate limits are enforced per provider.
    |
    */
    'rate_limiting' => [
        'enabled' => env('AI_RATE_LIMITING_ENABLED', false),
        'cache_prefix' => 'ai_rate_limit',
        'default_limit' => 60,
        'default_window' => 60,

        'providers' => [
            'openai' => [
                'limit' => env('OPENAI_RATE_LIMIT', 60),
                'window' => env('OPENAI_RATE_WINDOW', 60),
            ],
            'claude' => [
                'limit' => env('ANTHROPIC_RATE_LIMIT', 60),
                'window' => env('ANTHROPIC_RATE_WINDOW', 60),
            ],
            'gemini' => [
                'limit' => env('GEMINI_RATE_LIMIT', 60),
                'window' => env('GEMINI_RATE_WINDOW', 60),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Tracking
    |--------------------------------------------------------------------------
    |
    | Enable cost tracking to monitor API usage and costs. Pricing is defined
    | per 1000 tokens. Update these values based on current provider pricing.
    |
    */
    'cost_tracking' => [
        'enabled' => env('AI_COST_TRACKING_ENABLED', false),

        'pricing' => [
            'openai' => [
                'gpt-4o' => ['prompt' => 0.0025, 'completion' => 0.01],
                'gpt-4o-mini' => ['prompt' => 0.00015, 'completion' => 0.0006],
                'gpt-4-turbo' => ['prompt' => 0.01, 'completion' => 0.03],
                'gpt-3.5-turbo' => ['prompt' => 0.0005, 'completion' => 0.0015],
                'text-embedding-3-small' => ['prompt' => 0.00002, 'completion' => 0],
                'text-embedding-3-large' => ['prompt' => 0.00013, 'completion' => 0],
                'default' => ['prompt' => 0.01, 'completion' => 0.03],
            ],
            'claude' => [
                'claude-sonnet-4-20250514' => ['prompt' => 0.003, 'completion' => 0.015],
                'claude-3-5-sonnet-20241022' => ['prompt' => 0.003, 'completion' => 0.015],
                'claude-3-opus-20240229' => ['prompt' => 0.015, 'completion' => 0.075],
                'claude-3-haiku-20240307' => ['prompt' => 0.00025, 'completion' => 0.00125],
                'default' => ['prompt' => 0.003, 'completion' => 0.015],
            ],
            'gemini' => [
                'gemini-1.5-pro' => ['prompt' => 0.00125, 'completion' => 0.005],
                'gemini-1.5-flash' => ['prompt' => 0.000075, 'completion' => 0.0003],
                'text-embedding-004' => ['prompt' => 0.000025, 'completion' => 0],
                'default' => ['prompt' => 0.00125, 'completion' => 0.005],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompt Templates
    |--------------------------------------------------------------------------
    |
    | Configure prompt templates for reusable AI prompts. Templates support
    | variable substitution using {{ variable }} syntax and can be loaded
    | from files or defined inline.
    |
    */
    'prompts' => [
        // Default path for prompt template files
        'path' => resource_path('prompts'),

        // Additional namespaced paths for organizing templates
        'paths' => [
            // 'admin' => resource_path('prompts/admin'),
        ],

        // Inline template definitions
        'templates' => [
            // Example templates:
            // 'summarize' => [
            //     'system' => 'You are a helpful assistant that summarizes text concisely.',
            //     'template' => 'Please summarize the following text:\n\n{{ text }}',
            // ],
            // 'translate' => [
            //     'system' => 'You are a professional translator.',
            //     'template' => 'Translate the following text to {{ language }}:\n\n{{ text }}',
            //     'defaults' => ['language' => 'English'],
            // ],
        ],
    ],
];
