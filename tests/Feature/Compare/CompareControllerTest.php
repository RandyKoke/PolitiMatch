<?php

namespace Tests\Feature\Compare;

use App\Enums\QuizResultStatus;
use App\Models\Answer;
use App\Models\GuestSession;
use App\Models\PartyPosition;
use App\Models\Question;
use App\Models\QuizResult;
use App\Services\QuizReliabilityService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompareControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function startGuestQuiz(): array
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token]);

        return [$quizResult, $guestSession->session_token];
    }

    /**
     * Exemple concret du document expert : sortie du nucléaire (Q7),
     * Écolo/MR aux antipodes. Vérifie que le comparateur restitue
     * exactement le score et la justification transcrits par le seeder.
     */
    public function test_compare_returns_exact_expert_data_for_the_nuclear_energy_question(): void
    {
        [$quizResult, $sessionToken] = $this->startGuestQuiz();
        $question = Question::where('label', 'like', 'La Belgique devrait sortir définitivement%')->firstOrFail();

        Answer::create([
            'quiz_result_id' => $quizResult->id, 'question_id' => $question->id,
            'user_score' => 2, 'was_skipped' => false, 'answered_at' => now(),
        ]);

        $response = $this->getJson('/api/compare?'.http_build_query([
            'quiz_result_uuid' => $quizResult->uuid,
            'session_token' => $sessionToken,
            'theme_id' => $question->theme_id,
        ]));

        $response->assertStatus(200);
        $this->assertSame(2, $response->json("user.scores.{$question->id}"));

        $ecolo = collect($response->json('parties'))->firstWhere('name', 'Écolo');
        $mr = collect($response->json('parties'))->firstWhere('name', 'MR');

        $this->assertSame(2, $ecolo['positions'][$question->id]['score']);
        $this->assertStringContainsString('sortir complètement du nucléaire dès 2035', $ecolo['positions'][$question->id]['justification']);
        $this->assertSame(-2, $mr['positions'][$question->id]['score']);
    }

    /**
     * Second exemple réel du jeu de données (axe sociétal) : les droits
     * LGBTQIA+ (Q26, axe sociétal) servent ici d'exemple. La laïcité de
     * l'État (Q29) est classée 'aucun' (hors axes) et ne convient donc pas
     * comme exemple d'axe sociétal.
     */
    public function test_compare_returns_exact_expert_data_for_the_lgbtqia_rights_question(): void
    {
        [$quizResult, $sessionToken] = $this->startGuestQuiz();
        $question = Question::where('label', 'like', 'Les droits des personnes LGBTQIA+%')->firstOrFail();

        $response = $this->getJson('/api/compare?'.http_build_query([
            'quiz_result_uuid' => $quizResult->uuid,
            'session_token' => $sessionToken,
            'axe_ideologique' => 'societal',
        ]));

        $response->assertStatus(200);
        $defi = collect($response->json('parties'))->firstWhere('name', 'DéFI');
        $this->assertSame(1, $defi['positions'][$question->id]['score']);
        $this->assertSame('Programme électoral 2024 (DéFI)', $defi['positions'][$question->id]['source_reference']);
    }

    public function test_compare_on_a_quiz_with_no_answers_returns_all_null_scores(): void
    {
        [$quizResult, $sessionToken] = $this->startGuestQuiz();

        $response = $this->getJson('/api/compare?'.http_build_query([
            'quiz_result_uuid' => $quizResult->uuid,
            'session_token' => $sessionToken,
        ]));

        $response->assertStatus(200)->assertJsonPath('has_answers', false);
        $scores = collect($response->json('user.scores'));
        $this->assertTrue($scores->every(fn ($score) => $score === null));
    }

    public function test_compare_returns_404_for_an_unknown_quiz(): void
    {
        $this->getJson('/api/compare?'.http_build_query([
            'quiz_result_uuid' => '00000000-0000-0000-0000-000000000000',
        ]))->assertStatus(404);
    }

    public function test_compare_returns_403_for_a_wrong_session_token(): void
    {
        [$quizResult] = $this->startGuestQuiz();

        $this->getJson('/api/compare?'.http_build_query([
            'quiz_result_uuid' => $quizResult->uuid,
            'session_token' => '00000000-0000-0000-0000-000000000000',
        ]))->assertStatus(403);
    }

    /**
     * Un session_token mal formé (pas un UUID du tout) ne doit jamais
     * atteindre une requête SQL brute contre une colonne typée uuid
     * (PostgreSQL) : CompareRequest le rejette dès la validation (422,
     * message en français), jamais un 500 ni un comportement silencieux.
     */
    public function test_compare_returns_a_clean_french_422_for_a_malformed_session_token(): void
    {
        [$quizResult] = $this->startGuestQuiz();

        $response = $this->getJson('/api/compare?'.http_build_query([
            'quiz_result_uuid' => $quizResult->uuid,
            'session_token' => 'ceci-nest-pas-un-uuid',
        ]));

        $response->assertStatus(422);
        $this->assertStringContainsString('UUID valide', $response->json('message'));
    }

    public function test_compare_returns_a_clean_french_422_for_a_malformed_quiz_uuid(): void
    {
        $response = $this->getJson('/api/compare?'.http_build_query([
            'quiz_result_uuid' => 'ceci-nest-pas-un-uuid',
        ]));

        $response->assertStatus(422);
        $this->assertStringContainsString('UUID valide', $response->json('message'));
    }

    /**
     * GuestSession.expires_at ne gouverne QUE l'éligibilité à la migration
     * de compte (AccountMigrationService::migrate) — ce n'est pas une durée
     * de vie générale du token, jamais vérifiée par QuizAccessService. Un
     * visiteur qui revient consulter son comparateur après ce délai avec un
     * lien/token déjà en sa possession doit donc continuer à fonctionner :
     * comportement délibéré (cf. philosophie "un lien ne doit jamais se
     * retrouver cassé" déjà appliquée ailleurs dans ce projet), documenté
     * ici explicitement pour ne pas être "corrigé" par erreur plus tard.
     */
    public function test_compare_still_works_with_a_valid_token_whose_guest_session_has_expired(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->subMonth()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token]);

        $this->getJson('/api/compare?'.http_build_query([
            'quiz_result_uuid' => $quizResult->uuid,
            'session_token' => $guestSession->session_token,
        ]))->assertStatus(200);
    }

    public function test_compare_filters_questions_by_theme(): void
    {
        [$quizResult, $sessionToken] = $this->startGuestQuiz();
        $economicTheme = Question::where('axe_ideologique', 'economique')->first()->theme_id;

        $response = $this->getJson('/api/compare?'.http_build_query([
            'quiz_result_uuid' => $quizResult->uuid,
            'session_token' => $sessionToken,
            'theme_id' => $economicTheme,
        ]));

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('questions')));
        foreach ($response->json('questions') as $question) {
            $this->assertNotNull($question['theme_name']);
        }
    }

    public function test_compare_still_works_when_a_party_has_no_position_for_a_question(): void
    {
        [$quizResult, $sessionToken] = $this->startGuestQuiz();
        $question = Question::first();

        // Supprime volontairement une position pour tester la résistance
        // du comparateur à une donnée manquante (ne devrait pas se produire
        // avec un seeder complet, mais le code doit rester robuste).
        PartyPosition::where('question_id', $question->id)->limit(1)->delete();

        $response = $this->getJson('/api/compare?'.http_build_query([
            'quiz_result_uuid' => $quizResult->uuid,
            'session_token' => $sessionToken,
        ]));

        $response->assertStatus(200);
        $missing = collect($response->json('parties'))->first(fn ($party) => $party['positions'][$question->id] === null);
        $this->assertNotNull($missing);
    }

    /**
     * Régression : même bug et même correction que
     * QuizStateControllerTest::test_state_questions_survive_a_real_database_cache_round_trip
     * — la valeur mise en cache par loadStaticComparisonData() contenait
     * encore deux objets Collection imbriqués (`question_ids`, `positions`
     * par parti) jusqu'à cette correction, invisibles avec le driver de
     * cache 'array' des tests (aucune sérialisation réelle). Forcer le
     * driver 'database' et appeler l'endpoint deux fois (cache miss puis
     * hit) reproduit et garde la correction.
     */
    public function test_compare_survives_a_real_database_cache_round_trip(): void
    {
        config(['cache.default' => 'database']);
        [$quizResult, $sessionToken] = $this->startGuestQuiz();

        $params = http_build_query(['quiz_result_uuid' => $quizResult->uuid, 'session_token' => $sessionToken]);

        $this->getJson("/api/compare?{$params}")->assertStatus(200);
        $this->getJson("/api/compare?{$params}")
            ->assertStatus(200)
            ->assertJsonCount(6, 'parties');
    }

    /**
     * @return array{0: QuizResult, 1: string}
     */
    private function completedQuizWith(int $realCount, int $skippedCount): array
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create([
            'session_token' => $guestSession->session_token,
            'status' => QuizResultStatus::Completed,
            'completed_at' => now(),
        ]);

        $questions = Question::orderBy('position_order')->take($realCount + $skippedCount)->get();
        foreach ($questions->take($realCount) as $question) {
            Answer::create([
                'quiz_result_id' => $quizResult->id, 'question_id' => $question->id,
                'user_score' => 1, 'was_skipped' => false, 'answered_at' => now(),
            ]);
        }
        foreach ($questions->skip($realCount) as $question) {
            Answer::create([
                'quiz_result_id' => $quizResult->id, 'question_id' => $question->id,
                'user_score' => 0, 'was_skipped' => true, 'answered_at' => now(),
            ]);
        }

        return [$quizResult, $guestSession->session_token];
    }

    /**
     * Le comparateur reste volontairement permissif pour un quiz encore en
     * cours (Pending, cf. test_compare_on_a_quiz_with_no_answers_
     * returns_all_null_scores ci-dessus, inchangé), mais devient inaccessible
     * pour un QuizResult Completed dont la fiabilité est bloquante.
     */
    public function test_compare_is_blocked_for_a_completed_quiz_with_zero_real_answers(): void
    {
        [$quizResult, $sessionToken] = $this->completedQuizWith(0, 5);

        $response = $this->getJson('/api/compare?'.http_build_query([
            'quiz_result_uuid' => $quizResult->uuid,
            'session_token' => $sessionToken,
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('blocked', true)
            ->assertJsonPath('reliability.state', 'empty');
        $this->assertArrayNotHasKey('parties', $response->json());
    }

    public function test_compare_is_blocked_for_a_completed_quiz_with_too_few_real_answers(): void
    {
        [$quizResult, $sessionToken] = $this->completedQuizWith(10, 5);

        $response = $this->getJson('/api/compare?'.http_build_query([
            'quiz_result_uuid' => $quizResult->uuid,
            'session_token' => $sessionToken,
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('blocked', true)
            ->assertJsonPath('reliability.state', 'too_few');
    }

    public function test_compare_works_normally_for_a_completed_quiz_with_enough_real_answers(): void
    {
        [$quizResult, $sessionToken] = $this->completedQuizWith(QuizReliabilityService::MIN_RELIABLE_ANSWERS, 0);

        $response = $this->getJson('/api/compare?'.http_build_query([
            'quiz_result_uuid' => $quizResult->uuid,
            'session_token' => $sessionToken,
        ]));

        $response->assertStatus(200)->assertJsonPath('has_answers', true);
        $this->assertArrayNotHasKey('blocked', $response->json());
        $this->assertGreaterThan(0, count($response->json('parties')));
    }
}
