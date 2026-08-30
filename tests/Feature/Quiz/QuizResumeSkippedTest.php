<?php

namespace Tests\Feature\Quiz;

use App\Enums\QuizResultStatus;
use App\Models\Answer;
use App\Models\GuestSession;
use App\Models\Party;
use App\Models\PartyPosition;
use App\Models\Question;
use App\Models\QuizResult;
use App\Models\Theme;
use App\Services\QuizReliabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /api/quiz/{uuid}/resume-skipped réouvre un QuizResult Completed
 * "too_few" pour permettre de répondre spécifiquement aux questions
 * passées, jamais pour un résultat déjà fiable ni pour un résultat
 * totalement vide (cf. QuizController::resumeSkipped).
 */
class QuizResumeSkippedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: QuizResult, 1: string, 2: array<int, Question>}
     */
    private function completedQuizWith(int $realCount, int $skippedCount): array
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create([
            'session_token' => $guestSession->session_token,
            'status' => QuizResultStatus::Completed,
            'completed_at' => now(),
            'profile_label' => 'Profil politique à préciser',
        ]);

        $theme = Theme::create(['name' => 'Économie']);
        $questions = [];
        $order = 1;

        for ($i = 0; $i < $realCount; $i++, $order++) {
            $question = Question::create(['theme_id' => $theme->id, 'label' => "Réelle {$order}", 'weight' => 1, 'position_order' => $order]);
            Answer::create([
                'quiz_result_id' => $quizResult->id, 'question_id' => $question->id,
                'user_score' => 1, 'was_skipped' => false, 'answered_at' => now(),
            ]);
            $questions[] = $question;
        }

        for ($i = 0; $i < $skippedCount; $i++, $order++) {
            $question = Question::create(['theme_id' => $theme->id, 'label' => "Passée {$order}", 'weight' => 1, 'position_order' => $order]);
            Answer::create([
                'quiz_result_id' => $quizResult->id, 'question_id' => $question->id,
                'user_score' => 0, 'was_skipped' => true, 'answered_at' => now(),
            ]);
            $questions[] = $question;
        }

        return [$quizResult, $guestSession->session_token, $questions];
    }

    public function test_resume_skipped_reopens_a_too_few_result_and_leaves_existing_answers_untouched(): void
    {
        [$quizResult, $sessionToken] = $this->completedQuizWith(10, 20);

        $response = $this->postJson("/api/quiz/{$quizResult->uuid}/resume-skipped", ['session_token' => $sessionToken]);

        $response->assertStatus(200)->assertJsonPath('quiz_result_uuid', $quizResult->uuid);
        $this->assertSame(QuizResultStatus::Pending, $quizResult->fresh()->status);
        // Réouvrir ne doit rien effacer : les 30 lignes de réponses restent
        // identiques (10 réelles, 20 passées) tant qu'aucune nouvelle
        // soumission n'a eu lieu.
        $this->assertDatabaseCount('answers', 30);
        $this->assertSame(10, Answer::where('quiz_result_id', $quizResult->id)->where('was_skipped', false)->count());
        $this->assertSame(20, Answer::where('quiz_result_id', $quizResult->id)->where('was_skipped', true)->count());
    }

    public function test_resume_skipped_returns_409_for_a_quiz_that_is_not_completed(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token]);

        $this->postJson("/api/quiz/{$quizResult->uuid}/resume-skipped", ['session_token' => $guestSession->session_token])
            ->assertStatus(409);
    }

    public function test_resume_skipped_returns_409_for_a_result_with_zero_real_answers(): void
    {
        [$quizResult, $sessionToken] = $this->completedQuizWith(0, 30);

        $this->postJson("/api/quiz/{$quizResult->uuid}/resume-skipped", ['session_token' => $sessionToken])
            ->assertStatus(409);
        $this->assertSame(QuizResultStatus::Completed, $quizResult->fresh()->status);
    }

    public function test_resume_skipped_returns_409_for_an_already_reliable_result(): void
    {
        [$quizResult, $sessionToken] = $this->completedQuizWith(QuizReliabilityService::MIN_RELIABLE_ANSWERS, 0);

        $this->postJson("/api/quiz/{$quizResult->uuid}/resume-skipped", ['session_token' => $sessionToken])
            ->assertStatus(409);
        $this->assertSame(QuizResultStatus::Completed, $quizResult->fresh()->status);
    }

    public function test_resume_skipped_returns_403_for_a_wrong_session_token(): void
    {
        [$quizResult] = $this->completedQuizWith(5, 25);

        $this->postJson("/api/quiz/{$quizResult->uuid}/resume-skipped", ['session_token' => '00000000-0000-0000-0000-000000000000'])
            ->assertStatus(403);
    }

    /**
     * Bout en bout : après réouverture, seules les questions passées sont
     * répondables à nouveau (upsert normal sur ces question_id précis) ;
     * une fois toutes répondues réellement, /quiz/complete recalcule et le
     * résultat devient fiable (Full, 30/30, aucune passée).
     */
    public function test_answering_previously_skipped_questions_after_resume_makes_the_result_reliable_again(): void
    {
        [$quizResult, $sessionToken, $questions] = $this->completedQuizWith(10, 20);
        $skippedQuestions = array_slice($questions, 10);

        $this->postJson("/api/quiz/{$quizResult->uuid}/resume-skipped", ['session_token' => $sessionToken])
            ->assertStatus(200);

        foreach ($skippedQuestions as $question) {
            $this->postJson('/api/answers', [
                'quiz_result_uuid' => $quizResult->uuid,
                'question_id' => $question->id,
                'user_score' => 1,
                'was_skipped' => false,
                'session_token' => $sessionToken,
            ])->assertStatus(200);
        }

        $complete = $this->postJson('/api/quiz/complete', [
            'quiz_result_uuid' => $quizResult->uuid,
            'session_token' => $sessionToken,
        ]);
        $complete->assertStatus(200);

        $results = $this->getJson("/api/results/{$quizResult->uuid}?session_token={$sessionToken}");
        $results->assertStatus(200)
            ->assertJsonPath('reliability.state', 'full')
            ->assertJsonPath('reliability.real_answers_count', 30)
            ->assertJsonPath('reliability.skipped_count', 0);
    }

    /**
     * Reproduction du scénario réel de bout en bout, en passant par les
     * vraies routes HTTP à chaque étape (et non des QuizResult construits
     * directement), pour que le premier /api/quiz/complete persiste de
     * vraies lignes result_party_scores avant la reprise ciblée. Condition
     * nécessaire pour exercer le bug de recalcul (ResultRepository::
     * persistResults faisait un simple create() par parti, jamais un
     * upsert, ce qui violait la contrainte unique (quiz_result_id,
     * party_id) dès le second calcul).
     */
    public function test_completing_the_quiz_a_second_time_after_resume_skipped_succeeds_without_duplicate_key_error(): void
    {
        $theme = Theme::create(['name' => 'Économie']);
        $party = Party::create(['name' => 'PS', 'abbreviation' => 'PS', 'language_community' => 'FR']);
        $questions = [];
        for ($i = 1; $i <= 30; $i++) {
            $question = Question::create(['theme_id' => $theme->id, 'label' => "Q{$i}", 'weight' => 1, 'position_order' => $i]);
            PartyPosition::create([
                'party_id' => $party->id, 'question_id' => $question->id, 'score' => 1,
                'justification' => 'x', 'source_reference' => 'x',
            ]);
            $questions[] = $question;
        }

        $start = $this->postJson('/api/quiz/start', $this->validConsentPayload());
        $uuid = $start->json('quiz_result_uuid');
        $sessionToken = $start->json('session_token');

        // 10 réponses réelles, 20 passées : sous le seuil MIN_RELIABLE_ANSWERS
        // (15), déclenche l'état bloquant "too_few".
        foreach ($questions as $index => $question) {
            $wasSkipped = $index >= 10;
            $this->postJson('/api/answers', [
                'quiz_result_uuid' => $uuid,
                'question_id' => $question->id,
                'user_score' => $wasSkipped ? 0 : 1,
                'was_skipped' => $wasSkipped,
                'session_token' => $sessionToken,
            ])->assertStatus(200);
        }

        // Premier calcul réel : persiste réellement une ligne result_party_scores.
        $firstComplete = $this->postJson('/api/quiz/complete', [
            'quiz_result_uuid' => $uuid, 'session_token' => $sessionToken,
        ]);
        $firstComplete->assertStatus(200);
        $this->assertDatabaseCount('result_party_scores', 1);

        $firstResults = $this->getJson("/api/results/{$uuid}?session_token={$sessionToken}");
        $firstResults->assertStatus(200)->assertJsonPath('reliability.state', 'too_few');

        // Reprise ciblée puis réponse réelle aux 20 questions passées.
        $this->postJson("/api/quiz/{$uuid}/resume-skipped", ['session_token' => $sessionToken])
            ->assertStatus(200);

        foreach (array_slice($questions, 10) as $question) {
            $this->postJson('/api/answers', [
                'quiz_result_uuid' => $uuid,
                'question_id' => $question->id,
                'user_score' => 1,
                'was_skipped' => false,
                'session_token' => $sessionToken,
            ])->assertStatus(200);
        }

        // Second calcul réel : c'est ici que l'ancien code échouait avec une
        // violation de contrainte unique (500, "Une erreur est survenue").
        $secondComplete = $this->postJson('/api/quiz/complete', [
            'quiz_result_uuid' => $uuid, 'session_token' => $sessionToken,
        ]);
        $secondComplete->assertStatus(200);

        $secondResults = $this->getJson("/api/results/{$uuid}?session_token={$sessionToken}");
        $secondResults->assertStatus(200)
            ->assertJsonPath('reliability.state', 'full')
            ->assertJsonCount(1, 'party_scores');

        // Toujours une seule ligne, jamais un doublon ni une ancienne valeur
        // mélangée à la nouvelle : le score reflète bien le second calcul
        // (30 réponses à 1, accord parfait avec la position du parti).
        $this->assertDatabaseCount('result_party_scores', 1);
        $this->assertSame(100.0, (float) $secondResults->json('party_scores.0.compatibility_score'));
    }
}
