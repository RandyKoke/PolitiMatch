<?php

namespace Tests\Unit\Services;

use App\Enums\AxisType;
use App\Models\Answer;
use App\Models\GuestSession;
use App\Models\Party;
use App\Models\PartyPosition;
use App\Models\Question;
use App\Models\QuizResult;
use App\Models\Theme;
use App\Services\PoliticalAxisCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PoliticalAxisCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private PoliticalAxisCalculator $calculator;

    private Theme $theme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PoliticalAxisCalculator;
        $this->theme = Theme::create(['name' => 'Société']);
    }

    /**
     * Exemple numérique de la spec technique §2.2 ("Mariage pour tous",
     * axe_ideologique=societal, weight=2, user_score=+2) : le facteur correct (2)
     * donne 1.0. Avec le facteur incorrect historique (4, bug v2), le
     * résultat serait 0.5 — cette assertion garde le calcul contre cette
     * régression précise.
     */
    public function test_matches_the_marriage_pour_tous_worked_example_and_uses_factor_two_not_four(): void
    {
        $question = $this->question(AxisType::Societal, weight: 2);
        $answers = $this->answersFor([[$question->id, 2]]);

        $result = $this->calculator->calculate($answers);

        $this->assertSame(1.0, $result['axis_y']);
        $this->assertNotEquals(0.5, $result['axis_y']);
    }

    public function test_axis_x_and_axis_y_are_independent_and_can_be_null_separately(): void
    {
        $economicQuestion = $this->question(AxisType::Economique, weight: 1);
        $answers = $this->answersFor([[$economicQuestion->id, -1]]);

        $result = $this->calculator->calculate($answers);

        $this->assertNotNull($result['axis_x']);
        $this->assertNull($result['axis_y']);
    }

    public function test_both_axes_are_null_when_all_relevant_questions_are_skipped(): void
    {
        $economicQuestion = $this->question(AxisType::Economique, weight: 1);
        $societalQuestion = $this->question(AxisType::Societal, weight: 1);
        $answers = $this->answersFor([
            [$economicQuestion->id, 2, true],
            [$societalQuestion->id, -2, true],
        ]);

        $result = $this->calculator->calculate($answers);

        $this->assertNull($result['axis_x']);
        $this->assertNull($result['axis_y']);
    }

    /**
     * calculateForParty() doit appliquer très exactement la même formule que
     * calculate() (score de parti à la place de user_score) — même exemple
     * numérique que le test ci-dessus ("Mariage pour tous", weight=2,
     * score=+2 → 1.0 avec le facteur 2, jamais 0.5 avec le facteur 4).
     */
    public function test_calculate_for_party_matches_the_same_worked_example_as_calculate(): void
    {
        $question = $this->question(AxisType::Societal, weight: 2);
        $positions = $this->positionsFor([[$question->id, 2]]);

        $result = $this->calculator->calculateForParty($positions);

        $this->assertSame(1.0, $result['axis_y']);
    }

    /**
     * Contrairement à calculate() (réponses utilisateur, was_skipped
     * possible), party_positions couvre toutes les questions actives par
     * construction (une ligne par (parti, question), contrainte unique en
     * base) — aucune notion de "passé" à filtrer, chaque position fournie
     * compte dans le calcul.
     */
    public function test_calculate_for_party_has_no_notion_of_skipped(): void
    {
        $question = $this->question(AxisType::Economique, weight: 1);
        $positions = $this->positionsFor([[$question->id, -2]]);

        $result = $this->calculator->calculateForParty($positions);

        $this->assertSame(-1.0, $result['axis_x']);
    }

    public function test_calculate_for_party_axes_are_independent_and_can_be_null_separately(): void
    {
        $economicQuestion = $this->question(AxisType::Economique, weight: 1);
        $positions = $this->positionsFor([[$economicQuestion->id, 1]]);

        $result = $this->calculator->calculateForParty($positions);

        $this->assertNotNull($result['axis_x']);
        $this->assertNull($result['axis_y']);
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $positionsSpec
     */
    private function positionsFor(array $positionsSpec): Collection
    {
        $party = Party::create(['name' => 'Parti Test', 'abbreviation' => 'PT', 'language_community' => 'FR']);

        foreach ($positionsSpec as [$questionId, $score]) {
            PartyPosition::create([
                'party_id' => $party->id, 'question_id' => $questionId,
                'score' => $score, 'justification' => 'x', 'source_reference' => 'x',
            ]);
        }

        return PartyPosition::with('question')->where('party_id', $party->id)->get();
    }

    private function question(AxisType $axisType, int $weight): Question
    {
        return Question::create([
            'theme_id' => $this->theme->id,
            'label' => 'Q',
            'weight' => $weight,
            'axe_ideologique' => $axisType,
            'position_order' => 1,
        ]);
    }

    /**
     * @param  array<int, array{0: int, 1: int, 2?: bool}>  $answersSpec
     */
    private function answersFor(array $answersSpec): Collection
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token]);

        foreach ($answersSpec as $spec) {
            Answer::create([
                'quiz_result_id' => $quizResult->id,
                'question_id' => $spec[0],
                'user_score' => $spec[1],
                'was_skipped' => $spec[2] ?? false,
                'answered_at' => now(),
            ]);
        }

        return Answer::with('question')->where('quiz_result_id', $quizResult->id)->get();
    }
}
