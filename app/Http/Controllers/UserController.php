<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateAvatarRequest;
use App\Models\QuizResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
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
}
