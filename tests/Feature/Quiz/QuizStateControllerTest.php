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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizStateControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, Question> */
    private array $questions;

    protected function setUp(): void
    {
        parent::setUp();

        $theme = Theme::create(['name' => 'Économie']);
        $this->questions = [
            Question::create(['theme_id' => $theme->id, 'label' => 'Q1', 'weight' => 2, 'position_order' => 1]),
            Question::create(['theme_id' => $theme->id, 'label' => 'Q2', 'weight' => 1, 'position_order' => 2]),
        ];

        $party = Party::create(['name' => 'PS', 'abbreviation' => 'PS', 'language_community' => 'FR']);
        foreach ($this->questions as $question) {
            PartyPosition::create([
                'party_id' => $party->id, 'question_id' => $question->id, 'score' => 1,
                'justification' => 'x', 'source_reference' => 'x',
            ]);
        }
    }

    private function startGuestQuiz(): array
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token]);

        return [$quizResult, $guestSession->session_token];
    }

    /**
     * Cas nominal de reprise (Guest Flow) : l'utilisateur a répondu à une
     * seule des deux questions avant un rechargement de page — le endpoint
     * doit renvoyer exactement cette réponse, pas les deux, et la liste
     * complète des questions pour reconstruire l'écran.
     */
    public function test_state_returns_metadata_answers_and_questions_for_a_pending_quiz(): void
    {
        [$quizResult, $sessionToken] = $this->startGuestQuiz();
        Answer::create([
            'quiz_result_id' => $quizResult->id, 'question_id' => $this->questions[0]->id,
            'user_score' => 2, 'was_skipped' => false, 'answered_at' => now(),
        ]);

        $response = $this->getJson("/api/quiz/{$quizResult->uuid}/state?".http_build_query([
            'session_token' => $sessionToken,
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('quiz_result.uuid', $quizResult->uuid)
            ->assertJsonPath('quiz_result.status', 'pending')
            ->assertJsonPath('quiz_result.completed_at', null)
            ->assertJsonPath('quiz_result.user_id', null)
            ->assertJsonPath('quiz_result.session_token', $sessionToken)
            ->assertJsonCount(1, 'answers')
            ->assertJsonPath('answers.0.question_id', $this->questions[0]->id)
            ->assertJsonPath('answers.0.user_score', 2)
            ->assertJsonPath('answers.0.was_skipped', false)
            ->assertJsonCount(2, 'questions');
    }

    public function test_state_works_the_same_way_for_an_authenticated_users_quiz(): void
    {
        $user = User::factory()->create();
        $quizResult = QuizResult::create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/quiz/{$quizResult->uuid}/state");

        $response->assertStatus(200)
            ->assertJsonPath('quiz_result.user_id', $user->id)
            ->assertJsonPath('quiz_result.session_token', null)
            ->assertJsonCount(0, 'answers');
    }

    /**
     * Comportement documenté : un quiz terminé reste consultable via cet
     * endpoint (200, pas un statut d'erreur) — c'est un endpoint de lecture
     * pure, la règle "on ne modifie plus un quiz non-pending" reste
     * appliquée uniquement côté écriture (storeAnswer). Le champ `status`
     * dans la réponse permet au frontend de rediriger vers /results plutôt
     * que de proposer de continuer à répondre.
     */
    public function test_state_still_works_for_a_completed_quiz(): void
    {
        [$quizResult, $sessionToken] = $this->startGuestQuiz();
        $quizResult->update(['status' => QuizResultStatus::Completed, 'completed_at' => now()]);

        $response = $this->getJson("/api/quiz/{$quizResult->uuid}/state?".http_build_query([
            'session_token' => $sessionToken,
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('quiz_result.status', 'completed')
            ->assertJsonPath('quiz_result.completed_at', fn ($value) => $value !== null);
    }

    /**
     * Un rechargement de page pile pendant le calcul (MatchingService::
     * computeMatching, statut 'computing' persisté
     * dès l'acquisition du verrou, cf. ResultRepository::acquireComputingLock)
     * doit permettre au frontend de détecter cet état réel plutôt que de
     * repartir d'un quiz vide — cet endpoint de lecture pure doit donc
     * refléter fidèlement 'computing', comme il le fait déjà pour
     * 'completed' ci-dessus.
     */
    public function test_state_reports_computing_status_for_a_quiz_being_calculated(): void
    {
        [$quizResult, $sessionToken] = $this->startGuestQuiz();
        $quizResult->update(['status' => QuizResultStatus::Computing]);

        $response = $this->getJson("/api/quiz/{$quizResult->uuid}/state?".http_build_query([
            'session_token' => $sessionToken,
        ]));

        $response->assertStatus(200)->assertJsonPath('quiz_result.status', 'computing');
    }

    public function test_state_returns_404_for_an_unknown_uuid(): void
    {
        $this->getJson('/api/quiz/00000000-0000-0000-0000-000000000000/state')
            ->assertStatus(404);
    }

    public function test_state_returns_403_for_a_wrong_session_token(): void
    {
        [$quizResult] = $this->startGuestQuiz();

        $this->getJson("/api/quiz/{$quizResult->uuid}/state?".http_build_query([
            'session_token' => '00000000-0000-0000-0000-000000000000',
        ]))->assertStatus(403);
    }

    public function test_state_returns_403_without_any_session_token_for_a_guest_quiz(): void
    {
        [$quizResult] = $this->startGuestQuiz();

        $this->getJson("/api/quiz/{$quizResult->uuid}/state")->assertStatus(403);
    }

    public function test_state_returns_403_for_another_users_quiz(): void
    {
        $owner = User::factory()->create();
        $quizResult = QuizResult::create(['user_id' => $owner->id]);

        $intruder = User::factory()->create();

        $this->actingAs($intruder)->getJson("/api/quiz/{$quizResult->uuid}/state")
            ->assertStatus(403);
    }

    public function test_state_returns_403_for_a_users_quiz_accessed_as_a_guest(): void
    {
        $owner = User::factory()->create();
        $quizResult = QuizResult::create(['user_id' => $owner->id]);

        $this->getJson("/api/quiz/{$quizResult->uuid}/state")->assertStatus(403);
    }

    /**
     * Régression : le cache de test tourne sur le driver 'array' (phpunit.xml),
     * qui garde les objets PHP en mémoire sans jamais les (dé)sérialiser —
     * il ne peut donc pas détecter un bug de (dé)sérialisation propre au
     * driver 'database' réellement utilisé en dev/prod. Ce test force ce
     * driver et appelle l'endpoint deux fois (le second appel lit le cache
     * au lieu de recalculer) pour garder la preuve du bug découvert : avec
     * config('cache.serializable_classes') = false (durcissement sécurité
     * volontaire de ce projet), désérialiser une Collection Eloquent depuis
     * le cache database renvoyait silencieusement un objet
     * __PHP_Incomplete_Class. Corrigé en mettant en cache un tableau brut
     * (toArray()) plutôt que des objets Eloquent — cf. commentaire de
     * QuizController::loadActiveQuestions().
     */
    public function test_state_questions_survive_a_real_database_cache_round_trip(): void
    {
        config(['cache.default' => 'database']);
        [$quizResult, $sessionToken] = $this->startGuestQuiz();

        // Premier appel : peuple le cache (miss). Second appel : le lit
        // (hit) — c'est ce second appel qui échouait avant la correction.
        $this->getJson("/api/quiz/{$quizResult->uuid}/state?".http_build_query(['session_token' => $sessionToken]))
            ->assertStatus(200);

        $this->getJson("/api/quiz/{$quizResult->uuid}/state?".http_build_query(['session_token' => $sessionToken]))
            ->assertStatus(200)
            ->assertJsonCount(2, 'questions');
    }
}
