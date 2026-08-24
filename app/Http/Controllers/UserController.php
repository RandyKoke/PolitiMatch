<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateAvatarRequest;
use App\Models\QuizResult;
use App\Services\QuizAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct(private readonly QuizAccessService $access) {}

    /**
     * Historique des quiz de l'utilisateur connecté (cahier des charges,
     * Module Historique). Tous les statuts sont renvoyés, pas seulement
     * 'completed' : un quiz 'pending' abandonné ou 'failed' reste une
     * information légitime pour l'utilisateur ("Refaire le quiz" a du sens
     * pour ces cas aussi), au frontend de distinguer l'affichage selon le
     * statut plutôt qu'au backend de les cacher.
     */
    public function results(): JsonResponse
    {
        $quizResults = QuizResult::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get(['uuid', 'status', 'completed_at', 'profile_label', 'created_at']);

        return response()->json(['quiz_results' => $quizResults]);
    }

    /**
     * Changement d'avatar depuis le tableau de bord (grille de propositions
     * identique à l'inscription, cf. AvatarController::suggestions). Ne
     * touche qu'au propre compte de l'utilisateur authentifié — jamais un id
     * fourni par le client, uniquement Auth::user().
     */
    public function updateAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        $user = Auth::user();
        $user->update(['avatar_seed' => $request->string('avatar_seed')]);

        return response()->json(['user' => $user]);
    }

    /**
     * Suppression définitive, à la demande de l'utilisateur, de l'un de ses
     * propres résultats, quel que soit son statut (pending/computing/failed/
     * completed) : côté tableau de bord, "Supprimer" propose la même action
     * partout plutôt que de réserver la suppression aux résultats terminés.
     * answers et result_party_scores disparaissent avec lui (ON DELETE
     * CASCADE sur quiz_result_id), de même que le lien de partage éventuel,
     * share_token n'étant qu'une colonne de quiz_results lui-même.
     */
    public function destroyResult(QuizResult $quizResult): JsonResponse
    {
        $this->access->ensureAccess($quizResult, null);

        $quizResult->delete();

        return response()->json(['message' => 'Résultat supprimé.']);
    }
}
