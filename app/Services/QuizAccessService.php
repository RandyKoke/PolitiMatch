<?php

namespace App\Services;

use App\Models\QuizResult;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class QuizAccessService
{
    /**
     * Vérifie que l'appelant a le droit d'agir sur ce QuizResult : soit le
     * User connecté qui le possède, soit le session_token exact du visiteur
     * anonyme (comparaison à temps constant, timing-safe).
     *
     * @throws AuthorizationException
     */
    public function ensureAccess(QuizResult $quizResult, ?string $sessionToken): void
    {
        if ($quizResult->user_id !== null) {
            if (! Auth::check() || Auth::id() !== $quizResult->user_id) {
                throw new AuthorizationException('Ce quiz ne vous appartient pas.');
            }

            return;
        }

        if ($sessionToken === null || ! Str::isUuid($sessionToken) || ! hash_equals((string) $quizResult->session_token, $sessionToken)) {
            throw new AuthorizationException('Session invalide pour ce quiz.');
        }
    }
}
