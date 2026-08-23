<?php

namespace Tests\Unit\Repositories;

use App\Models\GuestSession;
use App\Models\Question;
use App\Models\QuizResult;
use App\Models\Theme;
use App\Repositories\AnswerRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnswerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private AnswerRepository $repository;

    private QuizResult $quizResult;

    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new AnswerRepository;

        $theme = Theme::create(['name' => 'Économie']);
        $this->question = Question::create(['theme_id' => $theme->id, 'label' => 'Q1', 'weight' => 1, 'position_order' => 1]);
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $this->quizResult = QuizResult::create(['session_token' => $guestSession->session_token]);
    }

    public function test_creates_a_new_answer_when_none_exists_yet(): void
    {
        $answer = $this->repository->upsertAnswer($this->quizResult->id, $this->question->id, 1, false);

        $this->assertSame(1, $answer->user_score);
        $this->assertDatabaseCount('answers', 1);
    }

    /**
     * Deux appels successifs pour la même (quiz_result_id, question_id) ne
     * doivent jamais produire
     * de doublon ni d'exception, et la dernière valeur doit toujours
     * l'emporter, exactement la garantie qu'un INSERT ... ON CONFLICT DO
     * UPDATE atomique fournit indépendamment du timing réel des requêtes.
     */
    public function test_a_second_call_for_the_same_question_updates_in_place_instead_of_duplicating(): void
    {
        $this->repository->upsertAnswer($this->quizResult->id, $this->question->id, -2, false);
        $second = $this->repository->upsertAnswer($this->quizResult->id, $this->question->id, 2, true);

        $this->assertDatabaseCount('answers', 1);
        $this->assertSame(2, $second->user_score);
        $this->assertTrue($second->was_skipped);
    }

    public function test_never_throws_across_many_rapid_successive_calls_on_the_same_question(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->repository->upsertAnswer($this->quizResult->id, $this->question->id, $i % 2 === 0 ? 1 : -1, false);
        }

        $this->assertDatabaseCount('answers', 1);
    }
}
