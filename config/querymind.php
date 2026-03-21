<?php

return [
    'cache_store' => env('QUERYMIND_CACHE_STORE', env('CACHE_STORE', 'database')),

    'rate_limits' => [
        'per_minute' => env('QUERYMIND_RATE_LIMIT_PER_MINUTE', 60),
    ],

    'crawler' => [
        'max_pages' => (int) env('QUERYMIND_CRAWLER_MAX_PAGES', 25),
        'timeout_seconds' => (int) env('QUERYMIND_CRAWLER_TIMEOUT', 15),
    ],

    'chunking' => [
        'target_size' => (int) env('QUERYMIND_CHUNK_TARGET_SIZE', 850),
        'max_size' => (int) env('QUERYMIND_CHUNK_MAX_SIZE', 1000),
        'overlap' => (int) env('QUERYMIND_CHUNK_OVERLAP', 120),
    ],
];
