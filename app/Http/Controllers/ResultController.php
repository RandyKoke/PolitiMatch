<?php

namespace App\Http\Controllers;

use App\Enums\QuizResultStatus;
use App\Models\QuizResult;
use App\Services\OgImageService;
use App\Services\QuizAccessService;
use App\Services\QuizReliabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ResultController extends Controller
{
    public function __construct(
        private readonly QuizReliabilityService $reliability,
        private readonly OgImageService $ogImages,
        private readonly QuizAccessService $access,
    ) {}

    /**
     * Réservé au propriétaire (compte connecté ou session_token exact du
     * visiteur anonyme, via QuizAccessService — même vérification à temps
     * constant que pour écrire une réponse). Distinct à dessein du lien de
     * partage public (ShareController::show, /share/{token}) : un UUID
     * interne, contrairement à un share_token, n'est ni révocable ni
     * conçu pour circuler publiquement (il vit dans l'URL, donc
     * potentiellement dans un historique de navigateur, des logs ou un
     * lien copié par erreur), donc jamais traité comme suffisant à lui
     * seul pour prouver un accès légitime.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $quizResult = QuizResult::withFullQuizData()->where('uuid', $uuid)->firstOrFail();

        $this->access->ensureAccess($quizResult, $request->query('session_token'));

        if ($quizResult->status !== QuizResultStatus::Completed) {
            return response()->json([
                'message' => "Ce résultat n'est pas encore disponible.",
                'status' => $quizResult->status,
            ], 409);
        }

        return response()->json($quizResult->toResultPayload($this->reliability));
    }

    /**
     * Accessible dans deux cas distincts, à la différence de show() ci-dessus
     * qui ne connaît que le propriétaire : soit le propriétaire (compte ou
     * session_token, comme show()), soit un résultat au partage actif
     * (is_shared = true), puisque cette même route sert aussi le bouton de
     * téléchargement de ShareView.vue, accessible à quiconque reçoit un lien
     * de partage. Bloquée pour un état de fiabilité insuffisant, comme
     * show()/ShareController::show()/CompareController::index : la carte
     * inclurait potentiellement le parti le plus compatible, une donnée que
     * ces trois autres points d'accès masquent déjà dans ce cas.
     */
    public function downloadImage(Request $request, string $uuid): JsonResponse|BinaryFileResponse
    {
        $quizResult = QuizResult::with('resultPartyScores.party')->where('uuid', $uuid)->firstOrFail();

        if (! $quizResult->is_shared) {
            $this->access->ensureAccess($quizResult, $request->query('session_token'));
        }

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
