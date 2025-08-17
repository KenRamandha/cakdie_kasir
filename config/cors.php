<?php

return [
    'paths' => ['api/*', 'storage/*', 'sanctum/csrf-cookie', 'company-logo-*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['Content-Disposition'],
    'max_age' => 0,
    'supports_credentials' => false,
];
