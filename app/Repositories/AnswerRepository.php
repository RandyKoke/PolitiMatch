<?php

namespace App\Repositories;

use App\Models\Answer;
use Illuminate\Database\Eloquent\Collection;

class AnswerRepository
{
    /**
     * Réponses d'un quiz avec leur question (poids, axe, thème) chargée en
     * une fois — évite le N+1 dans ScoreCalculator/PoliticalAxisCalculator/
     * ProfileLabelService.
     */
    public function loadAnswersForQuiz(int $quizResultId): Collection
    {
        return Answer::with('question.theme')->where('quiz_result_id', $quizResultId)->get();
    }

    public function countForQuiz(int $quizResultId): int
    {
        return Answer::where('quiz_result_id', $quizResultId)->count();
    }

    /**
     * `Model::upsert()` (INSERT ... ON CONFLICT DO UPDATE, atomique côté
     * base) plutôt que `updateOrCreate()` : `updateOrCreate()` fait un
     * SELECT puis un INSERT/UPDATE séparés, et deux
     * requêtes quasi simultanées sur la même question (deux onglets sur la
     * même session invité) peuvent toutes les deux constater "aucune ligne"
     * puis tenter d'insérer, la seconde se heurtant alors à la contrainte
     * unique (quiz_result_id, question_id) avec une QueryException non
     * interceptée (500 brut). Un upsert atomique élimine cette fenêtre de
     * course : la base elle-même sérialise le conflit, la requête la plus
     * récente à committer l'emporte de façon prévisible ("dernière écriture
     * gagne"), jamais d'exception ni de doublon.
     */
    public function upsertAnswer(int $quizResultId, int $questionId, int $userScore, bool $wasSkipped): Answer
    {
        Answer::upsert(
            [[
                'quiz_result_id' => $quizResultId,
                'question_id' => $questionId,
                'user_score' => $userScore,
                'was_skipped' => $wasSkipped,
                'answered_at' => now(),
            ]],
            ['quiz_result_id', 'question_id'],
            ['user_score', 'was_skipped', 'answered_at'],
        );

        // upsert() renvoie un nombre de lignes affectées, pas le modèle —
        // rechargé explicitement pour garder le même contrat de retour
        // qu'avant (le contrôleur renvoie l'Answer complète au client).
        return Answer::where('quiz_result_id', $quizResultId)->where('question_id', $questionId)->firstOrFail();
    }
}
