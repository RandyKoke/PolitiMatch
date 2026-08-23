<?php

namespace App\Repositories;

use App\Models\PartyPosition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Enumerable;

class PartyPositionRepository
{
    /**
     * Positions des partis pour un ensemble de questions, tous partis
     * confondus — le regroupement par party_id est laissé au MatchingService.
     */
    public function loadPositionsForQuestions(Enumerable|array $questionIds): Collection
    {
        return PartyPosition::whereIn('question_id', $questionIds)->get();
    }

    /**
     * Positions les plus emblématiques d'un parti, au sens des questions les
     * plus discriminantes (weight le plus élevé) — utilisé pour enrichir sa
     * fiche (GET /api/parties/{id}).
     */
    public function topPositionsForParty(int $partyId, int $limit = 3): Collection
    {
        return PartyPosition::query()
            ->join('questions', 'questions.id', '=', 'party_positions.question_id')
            ->where('party_positions.party_id', $partyId)
            ->orderByDesc('questions.weight')
            ->limit($limit)
            ->get(['party_positions.*', 'questions.label as question_label']);
    }
}
