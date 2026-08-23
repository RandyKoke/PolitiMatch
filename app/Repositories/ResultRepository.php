<?php

namespace App\Repositories;

use App\Enums\QuizResultStatus;
use App\Models\QuizResult;
use App\Models\ResultPartyScore;
use Illuminate\Support\Facades\DB;

class ResultRepository
{
    /**
     * Verrou optimiste (Phase 1) : bascule pending -> computing en une seule
     * requête conditionnelle. true seulement si CE processus a posé le verrou.
     */
    public function acquireComputingLock(int $quizResultId): bool
    {
        return QuizResult::where('id', $quizResultId)
            ->where('status', QuizResultStatus::Pending)
            ->update(['status' => QuizResultStatus::Computing]) === 1;
    }

    public function loadQuizResult(int $quizResultId): QuizResult
    {
        return QuizResult::findOrFail($quizResultId);
    }

    /**
     * Persistance atomique (Phase 5) : les 6 scores partis + les axes + le
     * profil politique + le passage à 'completed' vivent dans la même
     * transaction.
     *
     * Upsert atomique (INSERT ... ON CONFLICT DO UPDATE), même pattern que
     * AnswerRepository::upsertAnswer() sur la contrainte unique
     * (quiz_result_id, party_id) — jamais un simple INSERT : un recalcul sur
     * un quiz_result_id déjà résolu une première fois (reprise ciblée après
     * un profil peu fiable, ou "Relancer le calcul" après un échec) doit
     * remplacer les anciennes lignes de score, pas entrer en conflit avec
     * elles. Un simple create() ici faisait échouer systématiquement tout
     * second calcul avec une violation de la contrainte d'unicité, capturée
     * par MatchingService comme une erreur générique.
     *
     * @param  array<int, array{party_id: int, score: float|null, rank: int}>  $rankedScores
     */
    public function persistResults(
        int $quizResultId,
        array $rankedScores,
        ?float $axisX,
        ?float $axisY,
        string $profileLabel,
        string $profileDescription,
    ): void {
        DB::transaction(function () use ($quizResultId, $rankedScores, $axisX, $axisY, $profileLabel, $profileDescription) {
            $rows = array_map(fn (array $row) => [
                'quiz_result_id' => $quizResultId,
                'party_id' => $row['party_id'],
                'compatibility_score' => $row['score'],
                'rank' => $row['rank'],
                'calculated_at' => now(),
            ], $rankedScores);

            ResultPartyScore::upsert(
                $rows,
                ['quiz_result_id', 'party_id'],
                ['compatibility_score', 'rank', 'calculated_at'],
            );

            QuizResult::where('id', $quizResultId)->update([
                'political_axis_x' => $axisX,
                'political_axis_y' => $axisY,
                'profile_label' => $profileLabel,
                'profile_description' => $profileDescription,
                'status' => QuizResultStatus::Completed,
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * Best effort, hors transaction : ne doit jamais écraser un statut déjà
     * 'completed' posé entre-temps par un calcul concurrent réussi (cf. spec
     * technique, correction v4→v5).
     */
    public function markFailed(int $quizResultId): void
    {
        QuizResult::where('id', $quizResultId)
            ->where('status', QuizResultStatus::Computing)
            ->update(['status' => QuizResultStatus::Failed]);
    }
}
