<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Préfixe explicite (3e argument de throttle:maxAttempts,decayMinutes,prefix)
// sur CHAQUE route throttlée de ce fichier : sans lui, la clé de comptage
// de Laravel (ThrottleRequests::resolveRequestSignature) ne dépend QUE de
// l'IP (ou de l'utilisateur connecté), jamais de la route elle-même, donc
// toutes les routes throttlées sans préfixe partageraient silencieusement
// UN SEUL compteur global. Sans ce préfixe, épuiser la limite de
// /api/answers pourrait suffire à bloquer /api/quiz/complete (et
// inversement), et un visiteur qui se tromperait plusieurs fois sur
// /auth/login perdrait aussi des tentatives sur /auth/register, alors que
// ces routes n'ont rien à voir entre elles.
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1,auth-register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1,auth-login');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');

    Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword'])->middleware('throttle:5,1,auth-forgot-password');
    Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:5,1,auth-reset-password');
});

// Pas de middleware auth:sanctum : ces routes servent aussi bien les
// visiteurs anonymes (Guest Flow) que les utilisateurs connectés — la
// propriété du quiz est vérifiée au cas par cas par QuizAccessService.
Route::get('questions', [QuizController::class, 'questions']);
Route::post('quiz/start', [QuizController::class, 'start']);
// Marge volontairement large : un utilisateur répond normalement à 6-15
// questions par minute selon son rythme, submitAnswer() (quizStore.js)
// verrouille déjà tout double-clic côté frontend (flag `submitting`).
// Cette limite est un garde-fou supplémentaire contre un script, pas une
// contrainte pensée pour gêner un usage normal, même très rapide.
Route::post('answers', [QuizController::class, 'storeAnswer'])->middleware('throttle:100,1,quiz-answers');
// Ne se déclenche normalement qu'une fois par quiz terminé ; la marge
// couvre largement une nouvelle tentative après une erreur réseau.
Route::post('quiz/complete', [QuizController::class, 'complete'])->middleware('throttle:15,1,quiz-complete');
Route::get('quiz/{quizResult:uuid}/state', [QuizController::class, 'state']);
Route::post('quiz/{quizResult:uuid}/retry', [QuizController::class, 'retry']);
Route::post('quiz/{quizResult:uuid}/resume-skipped', [QuizController::class, 'resumeSkipped']);

Route::get('results/{uuid}', [ResultController::class, 'show']);
Route::get('results/{uuid}/download-image', [ResultController::class, 'downloadImage']);
// Endpoint public par UUID : un clic répété par hésitation reste largement
// absorbé par cette marge.
Route::post('results/{quizResult:uuid}/share', [ShareController::class, 'create'])->middleware('throttle:30,1,share-create');

Route::get('parties', [PartyController::class, 'index']);
Route::get('parties/{party}', [PartyController::class, 'show']);
Route::get('compare', [CompareController::class, 'index']);

// Entièrement public (share_token, pas de session_token/auth) — cf.
// ShareController::show pour le raisonnement complet.
Route::get('share/{token}', [ShareController::class, 'show']);

Route::get('user/results', [UserController::class, 'results'])->middleware('auth:sanctum');
Route::patch('user/avatar', [UserController::class, 'updateAvatar'])->middleware('auth:sanctum');

// Public (utilisé avant la création du compte, pendant l'inscription) : pas
// de données sensibles renvoyées (seeds aléatoires + URL de rendu), throttle
// plus généreux que les routes d'auth (5,1) car sans risque de brute force/
// énumération, mais borné pour éviter un usage abusif comme proxy d'appels
// vers l'API DiceBear tierce.
Route::get('avatars/suggestions', [AvatarController::class, 'suggestions'])->middleware('throttle:30,1,avatars-suggestions');
