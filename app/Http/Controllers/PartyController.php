<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\PartyPosition;
use App\Models\QuizResult;
use App\Models\ResultPartyScore;
use App\Repositories\PartyPositionRepository;
use App\Repositories\PartyRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartyController extends Controller
{
    public function __construct(
        private readonly PartyRepository $parties,
        private readonly PartyPositionRepository $partyPositions,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['parties' => $this->parties->listActive()]);
    }

    /**
     * Fiche parti : faits et sources uniquement (cf. cahier des charges,
     * Module Fiches Partis) — aucun jugement de valeur ajouté ici, on
     * retransmet tel quel le contenu validé par l'expert politique.
     *
     * Un parti désactivé (is_active = false, ex. retiré du quiz actif) reste
     * autrement totalement inaccessible même depuis
     * un ancien résultat qui le référence encore dans son historique — un
     * utilisateur ne pouvait plus consulter la fiche d'un parti avec lequel
     * il avait été comparé, alors que cette donnée reste légitime dans SON
     * historique. `?quiz={uuid}` (déjà posé par ResultsView/CompareView sur
     * leurs liens vers une fiche parti, cf. PartyDetailView, mais jusqu'ici
     * jamais exploité ici) permet de prouver ce lien historique réel : si un
     * ResultPartyScore rattache bien ce parti à CE quiz précis, l'accès est
     * autorisé même désactivé. Aucune vérification de propriété du quiz
     * requise : le contenu d'une fiche parti (nom, description, positions)
     * n'est pas une donnée privée, c'est exactement ce qui est déjà exposé
     * publiquement pour n'importe quel parti actif — seule la question posée
     * ici est "ce parti a-t-il réellement fait partie de ce quiz", pas "cet
     * utilisateur est-il le propriétaire du quiz".
     */
    public function show(Request $request, Party $party): JsonResponse
    {
        if (! $party->is_active) {
            $quizUuid = $request->query('quiz');
            $referencedInQuizHistory = is_string($quizUuid) && ResultPartyScore::where('party_id', $party->id)
                ->whereIn('quiz_result_id', QuizResult::where('uuid', $quizUuid)->pluck('id'))
                ->exists();

            abort_unless($referencedInQuizHistory, 404);
        }

        $notablePositions = $this->partyPositions->topPositionsForParty($party->id)
            ->map(fn (PartyPosition $position) => [
                'question_label' => $position->question_label,
                'score' => $position->score,
                'justification' => $position->justification,
                'source_reference' => $position->source_reference,
            ]);

        return response()->json([
            'party' => $party->only([
                'id', 'name', 'abbreviation', 'logo_url', 'color_hex', 'description', 'slogan',
                'language_community', 'is_active', 'ideological_x', 'ideological_y',
            ]),
            'notable_positions' => $notablePositions,
        ]);
    }
}
