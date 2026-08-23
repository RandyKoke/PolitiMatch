<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Échec de la persistance atomique des résultats (Phase 5) — transitoire,
 * le quiz repasse en status 'failed' et peut être relancé via /retry.
 */
class MatchingPersistenceException extends Exception
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Échec de la persistance des résultats de matching.', previous: $previous);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Le calcul des résultats a échoué. Réessayez via /quiz/{uuid}/retry.',
        ], 500);
    }
}
