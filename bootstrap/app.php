<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Service unique derrière le proxy/edge de la plateforme d'hébergement
        // (Railway) : le conteneur ne reçoit jamais directement le trafic
        // public, seulement du trafic déjà filtré par cette même plateforme,
        // donc faire confiance à '*' ici ne fait pas confiance à Internet en
        // général, seulement au réseau interne de la plateforme. Nécessaire
        // pour que Laravel lise correctement X-Forwarded-Proto/-For/-Host :
        // sans ça, $request->isSecure() renvoie toujours false (la connexion
        // TCP vue par PHP est en clair, la terminaison TLS ayant déjà eu lieu
        // en amont), et url()/asset() génèrent des liens http:// même sur un
        // site servi en https:// (contenu mixte constaté en production sur
        // les polices préchargées avant ce correctif).
        $middleware->trustProxies(at: '*');

        // Authentifie les requêtes /api/* via le cookie de session Sanctum
        // (mode SPA, pas de token Bearer — cf. cahier des charges §Auth).
        $middleware->statefulApi();

        // Aucune route serveur nommée 'login' n'existe : /auth/login est une
        // route Vue Router pure (catch-all SPA, cf. routes/web.php). Sans ce
        // réglage, le comportement par défaut de Laravel pour un invité
        // refusé par 'auth:sanctum' tente `route('login')` et plante en 500
        // (RouteNotFoundException) dès que la requête n'envoie pas
        // `Accept: application/json` — bug réel constaté (curl brut, ou tout
        // client sans cet en-tête) même si axios (donc le SPA en usage
        // normal) l'envoie toujours et n'était pas concrètement affecté.
        // Toujours renvoyer null : jamais de redirection serveur, seulement
        // une réponse 401 (JSON ou non), cohérent avec une app 100% SPA.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // 'Too Many Attempts.' / 'Unauthenticated.' sont des chaînes codées
        // en dur dans le framework (ThrottleRequests middleware / Authenticate
        // middleware), jamais passées par le système de traduction — donc
        // jamais couvertes par lang/fr/*.php, quel que soit APP_LOCALE.
        // Seul moyen de les franciser : intercepter ces deux exceptions
        // précisément, sans toucher au comportement (mêmes codes HTTP, mêmes
        // en-têtes Retry-After/X-RateLimit-*, cf. ThrottleRequestsException).
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Trop de tentatives. Réessaie dans un instant.'], 429, $e->getHeaders());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Non authentifié.'], 401);
        });

        // Scénario réel (pas seulement théorique) : un onglet resté ouvert
        // dont la session a expiré avant l'envoi d'un formulaire.
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Ta session a expiré. Recharge la page et réessaie.'], 419);
        });
    })->create();
