<?php

use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\ShareController;
use Illuminate\Support\Facades\Route;

// Redirection/callback OAuth (Socialite) : navigations plein-page, pas des
// appels JSON — elles vivent ici plutôt que dans routes/api.php.
//
// Volontairement AUCUNE contrainte whereIn ici sur {provider} : un premier
// essai avec deux définitions de route ({provider}/redirect contraint aux
// providers configurés, puis un filet non contraint renvoyant 404) s'est
// révélé cassé pour une raison propre au routeur Laravel — RouteCollection
// indexe ses routes par [méthode + URI littérale], donc une seconde route
// enregistrée avec exactement la même URI ({provider}/redirect) ÉCRASE
// silencieusement la première dans cette table de lookup, quelle que soit
// la présence d'un where() différent : TOUTES les requêtes (y compris
// /auth/google/redirect, provider pourtant configuré) retombaient alors sur
// le filet 404, y compris Google. Reproduit et vérifié avant correction.
//
// La validation (provider connu ET réellement configuré) vit donc
// entièrement dans SocialAuthController::redirect()/callback() — un seul
// endroit, jamais de risque de collision de routes. GOOGLE_REDIRECT_URI
// (.env) doit correspondre exactement à /auth/google/callback.
Route::prefix('auth')->group(function () {
    Route::get('{provider}/redirect', [SocialAuthController::class, 'redirect']);
    Route::get('{provider}/callback', [SocialAuthController::class, 'callback']);
});

// Coquille HTML dédiée avec de vraies balises Open Graph/Twitter Card
// calculées côté serveur, enregistrée avant le joker SPA ci-dessous pour le
// faire gagner sur ce chemin précis, seule
// différence avec le reste de l'app — la SPA démarre ensuite normalement en
// dessous, cf. ShareController::showPage.
Route::get('/share/{token}', [ShareController::class, 'showPage']);

// Catch-all SPA : toute route non reconnue par ailleurs renvoie la coquille
// HTML compilée par Vite, et c'est Vue Router qui prend la main côté client
// (Home, Start, Auth login/register, Quiz, Result, Compare, PartyDetail,
// Dashboard...). Nécessaire pour qu'un accès direct (URL tapée, rechargement
// de page, lien externe) vers une route SPA fonctionne, pas seulement une
// navigation initiée depuis l'app elle-même.
//
// Pas d'exclusion 'auth' ici : /auth/login et /auth/register sont des routes
// purement client (Vue Router), qui ont besoin de cette même coquille HTML,
// les exclure causerait un 404 Laravel dessus. Les vraies routes serveur
// /auth/{provider}/redirect et /callback
// ci-dessus n'ont pas besoin d'être exclues non plus : étant enregistrées
// avant ce joker, Laravel les fait déjà gagner sur toute URL qu'elles
// matchent réellement (ex. /auth/google/redirect) — la seule exclusion utile
// reste 'api', dont les routes vivent de toute façon dans une collection
// séparée (routes/api.php, cf. bootstrap/app.php) jamais concurrencée par ce
// joker ; conservée ici uniquement par défense en profondeur.
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
