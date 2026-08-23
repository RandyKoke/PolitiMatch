<?php

namespace Tests\Feature\Results;

use App\Enums\QuizResultStatus;
use App\Models\Answer;
use App\Models\GuestSession;
use App\Models\Party;
use App\Models\Question;
use App\Models\QuizResult;
use App\Models\ResultPartyScore;
use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/results/{uuid} applique la garde de fiabilité
 * (QuizReliabilityService) via QuizResult::toResultPayload() : les cas de
 * frontière exacts du seuil de fiabilité, plus la cascade "aucun
 * classement/graphique en état bloquant".
 */
class ResultsReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private function completedQuizWith(int $realCount, int $skippedCount): QuizResult
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create([
            'session_token' => $guestSession->session_token,
            'status' => QuizResultStatus::Completed,
            'completed_at' => now(),
            'profile_label' => 'Progressiste équilibré',
            'profile_description' => 'Une description.',
            'political_axis_x' => 0.4,
            'political_axis_y' => -0.3,
        ]);

        $theme = Theme::create(['name' => 'Économie']);
        $order = 1;
        for ($i = 0; $i < $realCount; $i++, $order++) {
            $question = Question::create(['theme_id' => $theme->id, 'label' => "R{$order}", 'weight' => 1, 'position_order' => $order]);
            Answer::create([
                'quiz_result_id' => $quizResult->id, 'question_id' => $question->id,
                'user_score' => 1, 'was_skipped' => false, 'answered_at' => now(),
            ]);
        }
        for ($i = 0; $i < $skippedCount; $i++, $order++) {
            $question = Question::create(['theme_id' => $theme->id, 'label' => "S{$order}", 'weight' => 1, 'position_order' => $order]);
            Answer::create([
                'quiz_result_id' => $quizResult->id, 'question_id' => $question->id,
                'user_score' => 0, 'was_skipped' => true, 'answered_at' => now(),
            ]);
        }

        $party = Party::create(['name' => 'PS', 'abbreviation' => 'PS', 'language_community' => 'FR']);
        ResultPartyScore::create([
            'quiz_result_id' => $quizResult->id, 'party_id' => $party->id,
            'compatibility_score' => 80, 'rank' => 1, 'calculated_at' => now(),
        ]);

        return $quizResult;
    }

    public function test_zero_real_answers_is_blocked_and_hides_party_scores_and_axes(): void
    {
        $quizResult = $this->completedQuizWith(0, 30);

        $response = $this->getJson("/api/results/{$quizResult->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('reliability.state', 'empty')
            ->assertJsonPath('reliability.real_answers_count', 0)
            ->assertJsonPath('political_axis_x', null)
            ->assertJsonPath('political_axis_y', null)
            ->assertJsonCount(0, 'party_scores');
    }

    public function test_fourteen_real_answers_is_blocked_and_hides_party_scores_and_axes(): void
    {
        $quizResult = $this->completedQuizWith(14, 16);

        $response = $this->getJson("/api/results/{$quizResult->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('reliability.state', 'too_few')
            ->assertJsonPath('reliability.real_answers_count', 14)
            ->assertJsonPath('political_axis_x', null)
            ->assertJsonCount(0, 'party_scores');
    }

    public function test_fifteen_real_answers_is_not_blocked_and_shows_party_scores_and_axes(): void
    {
        $quizResult = $this->completedQuizWith(15, 15);

        $response = $this->getJson("/api/results/{$quizResult->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('reliability.state', 'partial')
            ->assertJsonPath('reliability.real_answers_count', 15)
            ->assertJsonPath('political_axis_x', '0.40')
            ->assertJsonCount(1, 'party_scores');
    }

    public function test_twenty_nine_real_answers_is_not_blocked_and_shows_party_scores_and_axes(): void
    {
        $quizResult = $this->completedQuizWith(29, 1);

        $response = $this->getJson("/api/results/{$quizResult->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('reliability.state', 'partial')
            ->assertJsonPath('reliability.real_answers_count', 29)
            ->assertJsonCount(1, 'party_scores');
    }

    public function test_thirty_real_answers_is_full_with_no_skipped(): void
    {
        $quizResult = $this->completedQuizWith(30, 0);

        $response = $this->getJson("/api/results/{$quizResult->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('reliability.state', 'full')
            ->assertJsonPath('reliability.skipped_count', 0)
            ->assertJsonCount(1, 'party_scores');
    }
}
