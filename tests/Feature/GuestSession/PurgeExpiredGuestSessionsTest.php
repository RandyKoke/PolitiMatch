<?php

namespace Tests\Feature\GuestSession;

use App\Console\Commands\PurgeExpiredGuestSessions;
use App\Enums\QuizResultStatus;
use App\Models\Answer;
use App\Models\GuestSession;
use App\Models\Party;
use App\Models\Question;
use App\Models\QuizResult;
use App\Models\ResultPartyScore;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Purge automatique des GuestSession réellement abandonnées, jamais des
 * sessions encore valides ou déjà migrées vers un compte.
 */
class PurgeExpiredGuestSessionsTest extends TestCase
{
    use RefreshDatabase;

    private function guestQuizWithAnswerAndScore(GuestSession $guestSession): QuizResult
    {
        $quizResult = QuizResult::create([
            'session_token' => $guestSession->session_token,
            'status' => QuizResultStatus::Completed,
            'completed_at' => now(),
        ]);

        $theme = Theme::create(['name' => 'Économie']);
        $question = Question::create(['theme_id' => $theme->id, 'label' => 'Q1', 'weight' => 1, 'position_order' => 1]);
        Answer::create([
            'quiz_result_id' => $quizResult->id, 'question_id' => $question->id,
            'user_score' => 1, 'was_skipped' => false, 'answered_at' => now(),
        ]);

        $party = Party::create(['name' => 'PS', 'abbreviation' => 'PS', 'language_community' => 'FR']);
        ResultPartyScore::create([
            'quiz_result_id' => $quizResult->id, 'party_id' => $party->id,
            'compatibility_score' => 80, 'rank' => 1, 'calculated_at' => now(),
        ]);

        return $quizResult;
    }

    public function test_deletes_an_expired_never_migrated_session_and_all_its_cascaded_data(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->subHours(1)]);
        $quizResult = $this->guestQuizWithAnswerAndScore($guestSession);

        $this->artisan(PurgeExpiredGuestSessions::class)->assertExitCode(0);

        $this->assertDatabaseMissing('guest_sessions', ['id' => $guestSession->id]);
        $this->assertDatabaseMissing('quiz_results', ['id' => $quizResult->id]);
        $this->assertDatabaseMissing('answers', ['quiz_result_id' => $quizResult->id]);
        $this->assertDatabaseMissing('result_party_scores', ['quiz_result_id' => $quizResult->id]);
    }

    /**
     * Le critère de suppression est expires_at, jamais l'âge brut de la
     * session : une session créée il y a plusieurs heures mais dont
     * expires_at est encore dans le futur ne doit jamais être supprimée.
     */
    public function test_never_deletes_a_session_still_within_its_expiration_window_even_if_several_hours_old(): void
    {
        $guestSession = GuestSession::create([
            'created_at' => now()->subHours(40),
            'expires_at' => now()->addHours(8),
        ]);

        $this->artisan(PurgeExpiredGuestSessions::class)->assertExitCode(0);

        $this->assertDatabaseHas('guest_sessions', ['id' => $guestSession->id]);
    }

    public function test_never_deletes_an_expired_session_that_was_already_migrated_to_an_account(): void
    {
        $user = User::create([
            'username' => 'randy', 'email' => 'randy@example.com', 'password_hash' => 'x',
        ]);
        $guestSession = GuestSession::create([
            'expires_at' => now()->subHours(1),
            'migrated_at' => now()->subMinutes(30),
            'user_id' => $user->id,
        ]);

        $this->artisan(PurgeExpiredGuestSessions::class)->assertExitCode(0);

        $this->assertDatabaseHas('guest_sessions', ['id' => $guestSession->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_reports_the_exact_number_of_purged_sessions(): void
    {
        GuestSession::create(['expires_at' => now()->subHours(1)]);
        GuestSession::create(['expires_at' => now()->subDays(3)]);
        GuestSession::create(['expires_at' => now()->addHours(10)]);

        $this->artisan(PurgeExpiredGuestSessions::class)
            ->expectsOutputToContain('2 session(s) invitée(s) expirée(s) purgée(s).')
            ->assertExitCode(0);
    }
}
