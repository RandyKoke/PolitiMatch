<?php

namespace Tests\Unit\Services;

use App\Enums\QuizReliabilityState;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Theme;
use App\Services\QuizReliabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Les 5 cas de frontière exacts du seuil de fiabilité (0, 14, 15, 29, 30
 * réponses réelles), plus la distinction Partial/Full sur le nombre de
 * questions passées.
 */
class QuizReliabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuizReliabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(QuizReliabilityService::class);

        // Question::active()->count() (total_questions) doit refléter un
        // jeu de données réaliste de 30 questions actives, indépendamment
        // du nombre de réponses simulées ci-dessous.
        $theme = Theme::create(['name' => 'Économie']);
        for ($i = 1; $i <= 30; $i++) {
            Question::create(['theme_id' => $theme->id, 'label' => "Q{$i}", 'weight' => 1, 'position_order' => $i]);
        }
    }

    /**
     * @return Collection<int, Answer>
     */
    private function makeAnswers(int $realCount, int $skippedCount): Collection
    {
        $answers = new Collection();
        for ($i = 0; $i < $realCount; $i++) {
            $answers->push(new Answer(['user_score' => 1, 'was_skipped' => false]));
        }
        for ($i = 0; $i < $skippedCount; $i++) {
            $answers->push(new Answer(['user_score' => 0, 'was_skipped' => true]));
        }

        return $answers;
    }

    public function test_zero_real_answers_is_empty_and_blocked(): void
    {
        $result = $this->service->evaluate($this->makeAnswers(0, 30));

        $this->assertSame(QuizReliabilityState::Empty, $result['state']);
        $this->assertTrue($result['state']->isBlocked());
        $this->assertSame(0, $result['real_answers_count']);
        $this->assertSame(30, $result['skipped_count']);
        $this->assertSame(30, $result['total_questions']);
    }

    public function test_fourteen_real_answers_is_too_few_and_blocked(): void
    {
        $result = $this->service->evaluate($this->makeAnswers(14, 16));

        $this->assertSame(QuizReliabilityState::TooFew, $result['state']);
        $this->assertTrue($result['state']->isBlocked());
        $this->assertSame(14, $result['real_answers_count']);
    }

    public function test_fifteen_real_answers_with_skips_is_partial_and_not_blocked(): void
    {
        $result = $this->service->evaluate($this->makeAnswers(15, 15));

        $this->assertSame(QuizReliabilityState::Partial, $result['state']);
        $this->assertFalse($result['state']->isBlocked());
        $this->assertSame(15, $result['real_answers_count']);
        $this->assertSame(15, $result['skipped_count']);
    }

    public function test_twenty_nine_real_answers_with_one_skip_is_partial_and_not_blocked(): void
    {
        $result = $this->service->evaluate($this->makeAnswers(29, 1));

        $this->assertSame(QuizReliabilityState::Partial, $result['state']);
        $this->assertFalse($result['state']->isBlocked());
        $this->assertSame(29, $result['real_answers_count']);
        $this->assertSame(1, $result['skipped_count']);
    }

    public function test_thirty_real_answers_with_no_skip_is_full_and_not_blocked(): void
    {
        $result = $this->service->evaluate($this->makeAnswers(30, 0));

        $this->assertSame(QuizReliabilityState::Full, $result['state']);
        $this->assertFalse($result['state']->isBlocked());
        $this->assertSame(30, $result['real_answers_count']);
        $this->assertSame(0, $result['skipped_count']);
    }

    /**
     * MIN_RELIABLE_ANSWERS lui-même, documenté explicitement comme
     * régression : toute modification accidentelle de ce seuil doit faire
     * échouer un test nommément dédié, pas seulement les tests de
     * frontière ci-dessus (qui resteraient silencieusement muets sur *quelle*
     * valeur exacte est attendue).
     */
    public function test_the_threshold_constant_is_fifteen(): void
    {
        $this->assertSame(15, QuizReliabilityService::MIN_RELIABLE_ANSWERS);
    }
}
