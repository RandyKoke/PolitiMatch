<?php

namespace Tests\Feature\RateLimiting;

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
 * Les endpoints de soumission de réponses, de complétion du quiz et de
 * création de lien de partage sont soumis à une limitation de fréquence,
 * comme les routes d'authentification et de suggestions d'avatar
 * (cf. routes/api.php). Marges volontairement généreuses (100/1, 15/1,
 * 30/1) : un garde-fou basique contre un script, jamais une contrainte
 * pensée pour gêner un usage normal, même très rapide, pendant une
 * démonstration publique.
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    private function guestQuizWithQuestions(int $count): array
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create([
            'session_token' => $guestSession->session_token,
            'status' => QuizResultStatus::Pending,
        ]);

        $theme = Theme::create(['name' => 'Économie']);
        $questions = [];
        for ($i = 1; $i <= $count; $i++) {
            $questions[] = Question::create([
                'theme_id' => $theme->id, 'label' => "Q{$i}", 'weight' => 1, 'position_order' => $i,
            ]);
        }

        return [$quizResult, $guestSession->session_token, $questions];
    }

    /**
     * Au moins MIN_RELIABLE_ANSWERS (15) réponses réelles, pour obtenir un
     * résultat partageable (share/create exige un quiz Completed).
     */
    private function completedGuestQuiz(): array
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
        for ($i = 1; $i <= QuizReliabilityService::MIN_RELIABLE_ANSWERS; $i++) {
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

        return [$quizResult, $guestSession->session_token];
    }

    public function test_answers_route_is_rate_limited_at_100_per_minute(): void
    {
        [$quizResult, $sessionToken, $questions] = $this->guestQuizWithQuestions(1);
        $question = $questions[0];

        for ($i = 0; $i < 100; $i++) {
            $response = $this->postJson('/api/answers', [
                'quiz_result_uuid' => $quizResult->uuid,
                'question_id' => $question->id,
                'user_score' => 1,
                'was_skipped' => false,
                'session_token' => $sessionToken,
            ]);
            $this->assertNotEquals(429, $response->status(), "La requête n°{$i} n'aurait pas dû être limitée.");
        }

        $blocked = $this->postJson('/api/answers', [
            'quiz_result_uuid' => $quizResult->uuid,
            'question_id' => $question->id,
            'user_score' => 1,
            'was_skipped' => false,
            'session_token' => $sessionToken,
        ]);
        $blocked->assertStatus(429);
    }

    /**
     * Non-régression explicite demandée : un enchaînement réaliste de 30
     * réponses (le nombre réel de questions du quiz complet, cf. cahier des
     * charges §5.1) ne doit jamais être bloqué.
     */
    public function test_a_realistic_sequence_of_30_answers_is_never_blocked(): void
    {
        [$quizResult, $sessionToken, $questions] = $this->guestQuizWithQuestions(30);

        foreach ($questions as $question) {
            $this->postJson('/api/answers', [
                'quiz_result_uuid' => $quizResult->uuid,
                'question_id' => $question->id,
                'user_score' => 1,
                'was_skipped' => false,
                'session_token' => $sessionToken,
            ])->assertStatus(200);
        }
    }

    public function test_quiz_complete_route_is_rate_limited_at_15_per_minute(): void
    {
        [$quizResult, $sessionToken] = $this->guestQuizWithQuestions(0);

        for ($i = 0; $i < 15; $i++) {
            $response = $this->postJson('/api/quiz/complete', [
                'quiz_result_uuid' => $quizResult->uuid,
                'session_token' => $sessionToken,
            ]);
            $this->assertNotEquals(429, $response->status(), "La requête n°{$i} n'aurait pas dû être limitée.");
        }

        $blocked = $this->postJson('/api/quiz/complete', [
            'quiz_result_uuid' => $quizResult->uuid,
            'session_token' => $sessionToken,
        ]);
        $blocked->assertStatus(429);
    }

    public function test_share_create_route_is_rate_limited_at_30_per_minute(): void
    {
        [$quizResult, $sessionToken] = $this->completedGuestQuiz();

        for ($i = 0; $i < 30; $i++) {
            $response = $this->postJson("/api/results/{$quizResult->uuid}/share", [
                'session_token' => $sessionToken,
            ]);
            $this->assertNotEquals(429, $response->status(), "La requête n°{$i} n'aurait pas dû être limitée.");
        }

        $blocked = $this->postJson("/api/results/{$quizResult->uuid}/share", [
            'session_token' => $sessionToken,
        ]);
        $blocked->assertStatus(429);
    }

    /**
     * Découvert en construisant les tests ci-dessus : sans le préfixe
     * explicite ajouté à chaque throttle:X,Y de routes/api.php, la clé de
     * comptage du middleware ne dépend que de l'IP (même IP pour toutes les
     * requêtes de test), donc TOUTES les routes throttlées partageaient un
     * seul et même compteur — épuiser /api/answers (15 réponses) suffisait
     * à faire échouer /api/quiz/complete juste après, à sa propre limite de
     * 15, alors que cette dernière route n'avait jamais été appelée que
     * cette seule fois. Preuve directe et déterministe de l'indépendance
     * des compteurs après correction : 20 requêtes sur /api/answers (bien
     * en dessous de sa propre limite de 100, mais AU-DESSUS de la limite de
     * 15 de /api/quiz/complete si les deux compteurs étaient encore
     * partagés) suivies d'un unique appel à /api/quiz/complete, qui doit
     * réussir.
     */
    public function test_distinct_throttled_routes_never_share_the_same_counter(): void
    {
        [$quizResult, $sessionToken, $questions] = $this->guestQuizWithQuestions(20);

        foreach ($questions as $question) {
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

        $this->assertNotEquals(429, $complete->status());
    }
}
