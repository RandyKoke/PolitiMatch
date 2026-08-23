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
use App\Services\QuizReliabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/results/{uuid}/download-image, carte de résultat téléchargeable
 * distincte de l'image Open Graph (jamais conditionnée à un partage actif),
 * avec la même garde de fiabilité que ResultController::show et
 * ShareController::show pour un état bloquant.
 */
class ResultDownloadImageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (glob(public_path('result-images/*.png')) ?: [] as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function completedQuizWith(int $realCount): QuizResult
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create([
            'session_token' => $guestSession->session_token,
            'status' => QuizResultStatus::Completed,
            'completed_at' => now(),
            'profile_label' => 'Progressiste équilibré',
            'profile_description' => 'Une description.',
        ]);

        $theme = Theme::create(['name' => 'Économie']);
        for ($i = 1; $i <= $realCount; $i++) {
            $question = Question::create(['theme_id' => $theme->id, 'label' => "Q{$i}", 'weight' => 1, 'position_order' => $i]);
            Answer::create([
                'quiz_result_id' => $quizResult->id, 'question_id' => $question->id,
                'user_score' => 1, 'was_skipped' => false, 'answered_at' => now(),
            ]);
        }

        $party = Party::create(['name' => 'PS', 'abbreviation' => 'PS', 'language_community' => 'FR']);
        ResultPartyScore::create([
            'quiz_result_id' => $quizResult->id, 'party_id' => $party->id,
            'compatibility_score' => 80, 'rank' => 1, 'calculated_at' => now(),
        ]);

        return $quizResult;
    }

    public function test_downloads_a_real_png_for_a_reliable_completed_result(): void
    {
        $quizResult = $this->completedQuizWith(QuizReliabilityService::MIN_RELIABLE_ANSWERS);

        $response = $this->get("/api/results/{$quizResult->uuid}/download-image");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("\x89PNG", $response->streamedContent());
    }

    public function test_never_requires_the_result_to_have_been_shared_first(): void
    {
        $quizResult = $this->completedQuizWith(QuizReliabilityService::MIN_RELIABLE_ANSWERS)->fresh();
        $this->assertNull($quizResult->share_token);
        $this->assertFalse($quizResult->is_shared);

        $response = $this->get("/api/results/{$quizResult->uuid}/download-image");

        $response->assertOk();
        // Le téléchargement ne doit jamais activer le partage public comme
        // effet de bord.
        $this->assertDatabaseHas('quiz_results', [
            'id' => $quizResult->id, 'share_token' => null, 'is_shared' => false,
        ]);
    }

    public function test_returns_409_for_a_quiz_not_yet_completed(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create([
            'session_token' => $guestSession->session_token,
            'status' => QuizResultStatus::Pending,
        ]);

        $response = $this->get("/api/results/{$quizResult->uuid}/download-image");

        $response->assertStatus(409);
    }

    public function test_returns_409_for_a_blocked_reliability_state(): void
    {
        $quizResult = $this->completedQuizWith(QuizReliabilityService::MIN_RELIABLE_ANSWERS - 1);

        $response = $this->get("/api/results/{$quizResult->uuid}/download-image");

        $response->assertStatus(409);
    }

    public function test_returns_404_for_an_unknown_uuid(): void
    {
        $response = $this->get('/api/results/00000000-0000-0000-0000-000000000000/download-image');

        $response->assertStatus(404);
    }
}
