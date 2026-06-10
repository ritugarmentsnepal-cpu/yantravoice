<?php

/**
 * CORS Configuration — Yantra Voice Studio
 *
 * Same-origin setup: Apache serves both the frontend and API,
 * so CORS is only needed if a separate frontend or mobile client
 * is introduced later.
 */
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => true,
];
