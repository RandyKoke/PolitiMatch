<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Verrou optimiste non acquis car un autre calcul est en cours (status =
 * 'computing') — temporaire, un nouvel appel peut réussir une fois l'autre
 * calcul terminé.
 */
class ComputationInProgressException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Un calcul est déjà en cours pour ce quiz. Réessayez dans quelques instants.',
        ], 409);
    }
}
