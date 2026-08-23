<?php

namespace App\Http\Controllers;

use App\Enums\QuizResultStatus;
use App\Models\QuizResult;
use App\Services\OgImageService;
use App\Services\QuizReliabilityService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ResultController extends Controller
{
    public function __construct(
        private readonly QuizReliabilityService $reliability,
        private readonly OgImageService $ogImages,
    ) {}

    /**
     * Public par possession de l'UUID (même modèle de sécurité que le
     * session_token du Guest Flow) : c'est ce qui permet à un visiteur
     * anonyme de consulter son propre résultat juste après le quiz, avant
     * toute création de compte.
     */
    public function show(string $uuid): JsonResponse
    {
        $quizResult = QuizResult::withFullQuizData()->where('uuid', $uuid)->firstOrFail();

        if ($quizResult->status !== QuizResultStatus::Completed) {
            return response()->json([
                'message' => "Ce résultat n'est pas encore disponible.",
                'status' => $quizResult->status,
            ], 409);
        }

        return response()->json($quizResult->toResultPayload($this->reliability));
    }

    /**
     * Carte de résultat téléchargeable : même modèle d'accès que show()
     * ci-dessus (public par
     * possession de l'UUID, jamais conditionné à un partage actif — cf.
     * commentaire de OgImageService::ensureDownloadImageGenerated). Bloquée
     * pour un état de fiabilité insuffisant, comme show()/ShareController::
     * show()/CompareController::index : la carte inclurait potentiellement
     * le parti le plus compatible, une donnée que ces trois autres points
     * d'accès masquent déjà dans ce cas.
     */
    public function downloadImage(string $uuid): JsonResponse|BinaryFileResponse
    {
        $quizResult = QuizResult::with('resultPartyScores.party')->where('uuid', $uuid)->firstOrFail();

        if ($quizResult->status !== QuizResultStatus::Completed) {
            return response()->json([
                'message' => "Ce résultat n'est pas encore disponible.",
                'status' => $quizResult->status,
            ], 409);
        }

        if ($this->reliability->evaluate($quizResult->answers)['state']->isBlocked()) {
            return response()->json([
                'message' => "Ce résultat n'a pas assez de réponses pour générer une carte à télécharger.",
            ], 409);
        }

        $absolutePath = $this->ogImages->ensureDownloadImageGenerated($quizResult);
        if ($absolutePath === null) {
            return response()->json([
                'message' => "La génération de l'image a échoué. Réessaie dans un instant.",
            ], 500);
        }

        return response()->download($absolutePath, 'politimatch-resultat.png', [
            'Content-Type' => 'image/png',
        ]);
    }
}
