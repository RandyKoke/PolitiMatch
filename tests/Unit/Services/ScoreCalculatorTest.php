<?php

namespace Tests\Unit\Services;

use App\Models\Answer;
use App\Models\GuestSession;
use App\Models\Party;
use App\Models\PartyPosition;
use App\Models\Question;
use App\Models\QuizResult;
use App\Models\Theme;
use App\Services\ScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private ScoreCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ScoreCalculator;
    }

    /**
     * Exemple numérique de la spec technique §2.1 : question "sortie du
     * nucléaire", weight=3, utilisateur répond +2. Écolo (+2) doit atteindre
     * 100.0%, MR (-2) doit tomber à 0.0%.
     */
    public function test_matches_the_nuclear_energy_worked_example_from_the_spec(): void
    {
        $theme = Theme::create(['name' => 'Environnement']);
        $question = Question::create([
            'theme_id' => $theme->id,
            'label' => 'La Belgique devrait sortir définitivement de l\'énergie nucléaire.',
            'weight' => 3,
            'position_order' => 1,
        ]);

        $ecolo = Party::create(['name' => 'Écolo', 'abbreviation' => 'ECOLO', 'language_community' => 'FR']);
        $mr = Party::create(['name' => 'MR', 'abbreviation' => 'MR', 'language_community' => 'FR']);

        $ecoloPosition = PartyPosition::create([
            'party_id' => $ecolo->id, 'question_id' => $question->id, 'score' => 2,
            'justification' => 'x', 'source_reference' => 'x',
        ]);
        $mrPosition = PartyPosition::create([
            'party_id' => $mr->id, 'question_id' => $question->id, 'score' => -2,
            'justification' => 'x', 'source_reference' => 'x',
        ]);

        $answers = $this->answersFor($question->id, userScore: 2);

        $this->assertSame(100.0, $this->calculator->calculateScore($answers, collect([$ecoloPosition])));
        $this->assertSame(0.0, $this->calculator->calculateScore($answers, collect([$mrPosition])));
    }

    public function test_returns_null_when_every_question_was_skipped(): void
    {
        $theme = Theme::create(['name' => 'Économie']);
        $question = Question::create([
            'theme_id' => $theme->id, 'label' => 'Q', 'weight' => 2, 'position_order' => 1,
        ]);
        $party = Party::create(['name' => 'PS', 'abbreviation' => 'PS', 'language_community' => 'FR']);
        $position = PartyPosition::create([
            'party_id' => $party->id, 'question_id' => $question->id, 'score' => 1,
            'justification' => 'x', 'source_reference' => 'x',
        ]);

        $answers = $this->answersFor($question->id, userScore: 0, wasSkipped: true);

        $this->assertNull($this->calculator->calculateScore($answers, collect([$position])));
    }

    public function test_ignores_a_question_with_no_expert_position_for_this_party(): void
    {
        $theme = Theme::create(['name' => 'Économie']);
        $question = Question::create([
            'theme_id' => $theme->id, 'label' => 'Q', 'weight' => 2, 'position_order' => 1,
        ]);

        $answers = $this->answersFor($question->id, userScore: 1);

        // Aucune position experte pour ce parti sur cette question : ignorée,
        // donc max_possible_distance reste à 0 -> données insuffisantes.
        $this->assertNull($this->calculator->calculateScore($answers, collect()));
    }

    private function answersFor(int $questionId, int $userScore, bool $wasSkipped = false)
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token]);

        Answer::create([
            'quiz_result_id' => $quizResult->id,
            'question_id' => $questionId,
            'user_score' => $userScore,
            'was_skipped' => $wasSkipped,
            'answered_at' => now(),
        ]);

        return Answer::with('question')->where('quiz_result_id', $quizResult->id)->get();
    }
}
