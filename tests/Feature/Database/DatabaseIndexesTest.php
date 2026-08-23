<?php

namespace Tests\Feature\Database;

use App\Enums\QuizResultStatus;
use App\Models\Answer;
use App\Models\GuestSession;
use App\Models\Party;
use App\Models\Question;
use App\Models\QuizResult;
use App\Models\ResultPartyScore;
use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * answers.question_id et result_party_scores.party_id n'avaient jusqu'ici
 * d'index que comme second membre de leur contrainte d'unicité composite
 * respective, jamais en tant qu'index standalone. Vérifie ici directement
 * le catalogue PostgreSQL (pg_indexes), pas seulement que la migration
 * s'exécute sans erreur : c'est la seule façon de prouver que l'index
 * demandé existe vraiment, avec le bon nom de colonne.
 */
class DatabaseIndexesTest extends TestCase
{
    use RefreshDatabase;

    private function hasStandaloneIndexOn(string $table, string $column): bool
    {
        $indexes = DB::select(
            "SELECT indexdef FROM pg_indexes WHERE tablename = ? AND indexdef LIKE ?",
            [$table, "%({$column})%"],
        );

        return collect($indexes)->contains(
            fn ($row) => str_contains($row->indexdef, "({$column})") && ! str_contains($row->indexdef, ', ')
        );
    }

    public function test_answers_question_id_has_a_standalone_index(): void
    {
        $this->assertTrue($this->hasStandaloneIndexOn('answers', 'question_id'));
    }

    public function test_result_party_scores_party_id_has_a_standalone_index(): void
    {
        $this->assertTrue($this->hasStandaloneIndexOn('result_party_scores', 'party_id'));
    }

    /**
     * Non-régression : les contraintes d'unicité composite préexistantes
     * doivent rester parfaitement fonctionnelles après l'ajout des nouveaux
     * index standalone, pas remplacées ni cassées par eux.
     */
    public function test_answers_composite_unique_constraint_still_rejects_duplicates(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create([
            'session_token' => $guestSession->session_token,
            'status' => QuizResultStatus::Pending,
        ]);
        $theme = Theme::create(['name' => 'Économie']);
        $question = Question::create(['theme_id' => $theme->id, 'label' => 'Q1', 'weight' => 1, 'position_order' => 1]);

        Answer::create([
            'quiz_result_id' => $quizResult->id, 'question_id' => $question->id,
            'user_score' => 1, 'was_skipped' => false, 'answered_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Answer::create([
            'quiz_result_id' => $quizResult->id, 'question_id' => $question->id,
            'user_score' => -1, 'was_skipped' => false, 'answered_at' => now(),
        ]);
    }

    public function test_result_party_scores_composite_unique_constraint_still_rejects_duplicates(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create([
            'session_token' => $guestSession->session_token,
            'status' => QuizResultStatus::Completed,
            'completed_at' => now(),
        ]);
        $party = Party::create(['name' => 'PS', 'abbreviation' => 'PS', 'language_community' => 'FR']);

        ResultPartyScore::create([
            'quiz_result_id' => $quizResult->id, 'party_id' => $party->id,
            'compatibility_score' => 80, 'rank' => 1, 'calculated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ResultPartyScore::create([
            'quiz_result_id' => $quizResult->id, 'party_id' => $party->id,
            'compatibility_score' => 50, 'rank' => 2, 'calculated_at' => now(),
        ]);
    }
}
