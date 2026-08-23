<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Verrou optimiste non acquis car le résultat existe déjà (status =
 * 'completed') — définitif, consulter GET /api/results/{uuid} plutôt que
 * de relancer un calcul.
 */
class AlreadyCompletedException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Ce quiz est déjà terminé, ses résultats sont disponibles.',
        ], 409);
    }
}
