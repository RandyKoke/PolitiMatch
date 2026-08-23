<?php

namespace App\Services;

use App\Enums\QuizReliabilityState;
use App\Models\Answer;
use App\Models\Question;
use Illuminate\Support\Collection;

/**
 * Seuil unique de fiabilité d'un résultat, basé sur le nombre de réponses
 * RÉELLEMENT données (was_skipped = false), jamais les questions
 * passées, qui comptent pour la progression du quiz (cf. QuizController::
 * complete()) mais pas pour la fiabilité d'un profil. Unique source de
 * vérité pour la question "ce résultat est-il assez fiable pour être
 * affiché/partagé/comparé ?", utilisée par ResultController, ShareController
 * et CompareController plutôt que trois vérifications ad hoc répétées à
 * trois endroits différents.
 *
 * Distinct de ProfileLabelService::MIN_THEMES_WITH_SCORE : cette dernière
 * mesure une diversité THÉMATIQUE (au moins 2 des 5 thématiques doivent
 * avoir un score exploitable) et reste inchangée — les deux mécanismes
 * coexistent pour des raisons différentes. Un résultat peut par exemple
 * avoir 20 réponses réelles concentrées sur une seule thématique : fiable
 * au sens de ce service (>= MIN_RELIABLE_ANSWERS), mais toujours "Profil
 * politique à préciser" côté ProfileLabelService, faute de diversité — les
 * deux libellés/comportements restent corrects simultanément.
 */
class QuizReliabilityService
{
    // Sur 25 à 30 questions actives (cahier des charges §5.1). En dessous de
    // ce nombre de réponses réelles, la combinaison de règles de scoring n'a
    // plus assez de signal pour produire un classement de partis ou un
    // graphique de positionnement dignes de confiance.
    public const MIN_RELIABLE_ANSWERS = 15;

    /**
     * @param  Collection<int, Answer>  $answers  toutes les réponses du QuizResult (réelles + passées)
     * @return array{state: QuizReliabilityState, real_answers_count: int, skipped_count: int, total_questions: int}
     */
    public function evaluate(Collection $answers): array
    {
        $realAnswersCount = $answers->where('was_skipped', false)->count();
        $skippedCount = $answers->where('was_skipped', true)->count();
        $totalQuestions = Question::active()->count();

        $state = match (true) {
            $realAnswersCount === 0 => QuizReliabilityState::Empty,
            $realAnswersCount < self::MIN_RELIABLE_ANSWERS => QuizReliabilityState::TooFew,
            $skippedCount > 0 => QuizReliabilityState::Partial,
            default => QuizReliabilityState::Full,
        };

        return [
            'state' => $state,
            'real_answers_count' => $realAnswersCount,
            'skipped_count' => $skippedCount,
            'total_questions' => $totalQuestions,
        ];
    }
}
