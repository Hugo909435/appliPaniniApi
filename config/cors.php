<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Méthodes REST usuelles uniquement
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Restreindre aux frontends connus (fallback localhost pour dev)
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    // Autorise localhost/127.0.0.1 sur n'importe quel port en dev
    'allowed_origins_patterns' => [
        '#^https?://localhost(:\\d+)?$#',
        '#^https?://127\\.0\\.0\\.1(:\\d+)?$#',
    ],

    // Limiter aux en-têtes standards autorisés
    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Les tokens sont portés en Authorization Bearer, pas de cookies
    'supports_credentials' => false,
];
