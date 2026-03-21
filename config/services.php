<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openrouter' => [
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'api_key' => env('NVIDIA_TEXT_API_KEY', env('NVIDIA_API_KEY')),
        'chat_model' => env('OPENROUTER_CHAT_MODEL', 'nvidia/nemotron-3-super-120b-a12b:free'),
        'embedding_model' => env('OPENROUTER_EMBEDDING_MODEL', 'nvidia/llama-nemotron-embed-vl-1b-v2:free'),
        'app_name' => env('OPENROUTER_APP_NAME', env('APP_NAME', 'SiteBotAI')),
        'site_url' => env('OPENROUTER_SITE_URL', env('APP_URL')),
    ],

    'chroma' => [
        'host' => env('CHROMA_HOST', 'http://127.0.0.1:8001'),
        'timeout' => env('CHROMA_TIMEOUT', 120),
        'health_endpoint' => env('CHROMA_HEALTH_ENDPOINT', 'health'),
        'add_document_endpoint' => env('CHROMA_ADD_DOCUMENT_ENDPOINT', 'add_document'),
        'query_endpoint' => env('CHROMA_QUERY_ENDPOINT', 'query'),
        'delete_document_endpoint' => env('CHROMA_DELETE_DOCUMENT_ENDPOINT', 'delete_document'),
        'enable_delete_endpoint' => env('CHROMA_ENABLE_DELETE_ENDPOINT', false),
    ],

];
