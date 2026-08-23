<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Frontend et backend sont servis depuis la même origine Railway (voir
    | cahier des charges §8) : aucune requête cross-origin n'a normalement
    | lieu en production. Cette config reste utile en développement, où le
    | serveur Vite (npm run dev) tourne sur un port distinct de php artisan
    | serve, et pour l'endpoint /sanctum/csrf-cookie utilisé par le flux
    | d'authentification SPA.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Jamais '*' : incompatible avec 'supports_credentials' => true (les
    // cookies de session Sanctum exigent une origine explicite).
    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', env('APP_URL', '')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
