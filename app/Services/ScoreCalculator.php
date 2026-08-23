<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\PartyPosition;
use Illuminate\Support\Collection;

class ScoreCalculator
{
    /**
     * Score de compatibilité d'un parti pour un quiz donné (spec technique
     * §2.1) : distance pondérée entre les réponses et les positions du
     * parti, normalisée en pourcentage.
     *
     * @param  Collection<int, Answer>  $answers
     * @param  Collection<int, PartyPosition>  $positionsForParty
     */
    public function calculateScore(Collection $answers, Collection $positionsForParty): ?float
    {
        $positionByQuestion = $positionsForParty->keyBy('question_id');

        $weightedDistanceSum = 0;
        $maxPossibleSum = 0;

        foreach ($answers as $answer) {
            if ($answer->was_skipped) {
                continue;
            }

            $position = $positionByQuestion->get($answer->question_id);
            if ($position === null) {
                // Pas de position experte pour cette question : ignorée du calcul.
                continue;
            }

            $weight = $answer->question->weight;
            $weightedDistanceSum += abs($answer->user_score - $position->score) * $weight;
            $maxPossibleSum += 4 * $weight; // 4 = |−2 − (+2)|, distance max entre deux réponses.
        }

        // Toutes les questions exploitables ont été passées (ou aucune position
        // experte disponible) : "données insuffisantes", jamais 0.0 (désaccord total).
        if ($maxPossibleSum === 0) {
            return null;
        }

        return round(100 - ($weightedDistanceSum / $maxPossibleSum * 100), 1);
    }
}
