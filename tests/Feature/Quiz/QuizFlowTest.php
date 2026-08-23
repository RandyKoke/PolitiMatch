<?php

namespace Tests\Feature\Quiz;

use App\Models\Party;
use App\Models\PartyPosition;
use App\Models\Question;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, Question> */
    private array $questions;

    private Party $ecolo;

    private Party $mr;

    // Au moins QuizReliabilityService::MIN_RELIABLE_ANSWERS (15) questions,
    // sous peine de déclencher systématiquement l'état bloquant
    // "too_few" dès qu'on répond à TOUTES les questions de ce jeu de
    // données minimal — le seuil est un compte absolu, pas une proportion
    // du total (cf. QuizReliabilityServiceTest). Toutes dans la même
    // thématique : préserve intentionnellement l'assertion "Profil
    // politique à préciser" ci-dessous (diversité thématique insuffisante,
    // un mécanisme distinct et inchangé, cf. ProfileLabelService).
    private const QUESTION_COUNT = 15;

    protected function setUp(): void
    {
        parent::setUp();

        $theme = Theme::create(['name' => 'Économie']);
        $this->questions = [];
        for ($i = 1; $i <= self::QUESTION_COUNT; $i++) {
            $this->questions[] = Question::create([
                'theme_id' => $theme->id, 'label' => "Q{$i}", 'weight' => 1, 'position_order' => $i,
            ]);
        }

        $this->ecolo = Party::create(['name' => 'Écolo', 'abbreviation' => 'ECOLO', 'language_community' => 'FR']);
        $this->mr = Party::create(['name' => 'MR', 'abbreviation' => 'MR', 'language_community' => 'FR']);

        foreach ($this->questions as $question) {
            foreach ([$this->ecolo, $this->mr] as $i => $party) {
                PartyPosition::create([
                    'party_id' => $party->id,
                    'question_id' => $question->id,
                    'score' => $i === 0 ? 2 : -2,
                    'justification' => 'x',
                    'source_reference' => 'x',
                ]);
            }
        }
    }

    public function test_full_guest_flow_from_start_to_results(): void
    {
        $start = $this->postJson('/api/quiz/start');
        $start->assertStatus(201)->assertJsonStructure(['quiz_result_uuid', 'session_token']);

        $uuid = $start->json('quiz_result_uuid');
        $sessionToken = $start->json('session_token');
        $this->assertNotNull($sessionToken);

        foreach ($this->questions as $question) {
            $this->postJson('/api/answers', [
                'quiz_result_uuid' => $uuid,
                'question_id' => $question->id,
                'user_score' => 2,
                'was_skipped' => false,
                'session_token' => $sessionToken,
            ])->assertStatus(200);
        }

        $complete = $this->postJson('/api/quiz/complete', [
            'quiz_result_uuid' => $uuid,
            'session_token' => $sessionToken,
        ]);

        $complete->assertStatus(200)->assertJsonStructure([
            'quiz_uuid', 'ranked_scores', 'axis_x', 'axis_y', 'profile_label', 'profile_description',
        ]);
        $scores = collect($complete->json('ranked_scores'));
        $this->assertSame($this->ecolo->id, $scores->firstWhere('rank', 1)['party_id']);
        $this->assertEquals(100.0, $scores->firstWhere('party_id', $this->ecolo->id)['score']);
        $this->assertEquals(0.0, $scores->firstWhere('party_id', $this->mr->id)['score']);
        // Une seule thématique (Économie) dans ce jeu de données minimal :
        // sous le seuil minimal du ProfileLabelService, profil de repli
        // attendu plutôt qu'un libellé forcé sur trop peu de signal.
        $this->assertSame('Profil politique à préciser', $complete->json('profile_label'));

        $results = $this->getJson("/api/results/{$uuid}");
        $results->assertStatus(200)->assertJsonCount(2, 'party_scores');
        $this->assertSame('Profil politique à préciser', $results->json('profile_label'));
        $this->assertNotNull($results->json('profile_description'));
    }

    public function test_full_authenticated_flow(): void
    {
        $user = User::factory()->create();

        $start = $this->actingAs($user)->postJson('/api/quiz/start');
        $start->assertStatus(201);
        $uuid = $start->json('quiz_result_uuid');
        $this->assertNull($start->json('session_token'));

        foreach ($this->questions as $question) {
            $this->actingAs($user)->postJson('/api/answers', [
                'quiz_result_uuid' => $uuid,
                'question_id' => $question->id,
                'user_score' => -2,
                'was_skipped' => false,
            ])->assertStatus(200);
        }

        $this->actingAs($user)->postJson('/api/quiz/complete', ['quiz_result_uuid' => $uuid])
            ->assertStatus(200);
    }

    public function test_a_guest_cannot_answer_another_guests_quiz(): void
    {
        $start = $this->postJson('/api/quiz/start');
        $uuid = $start->json('quiz_result_uuid');

        $this->postJson('/api/answers', [
            'quiz_result_uuid' => $uuid,
            'question_id' => $this->questions[0]->id,
            'user_score' => 1,
            'session_token' => '00000000-0000-0000-0000-000000000000',
        ])->assertStatus(403);
    }

    /**
     * Deux onglets sur la même session invité qui répondent différemment à
     * la même question. Un vrai test de course
     * (deux vraies requêtes HTTP en parallèle) n'est pas réalisable dans ce
     * harnais de test synchrone, mais l'atomicité recherchée
     * (AnswerRepository::upsertAnswer, INSERT ... ON CONFLICT DO UPDATE) est
     * une propriété de la requête SQL elle-même, indépendante du timing —
     * deux soumissions successives sur la même question exercent exactement
     * le même chemin SQL qu'une vraie collision concurrente. Vérifie donc :
     * aucune erreur (jamais un 500 brut), aucun doublon en base (contrainte
     * unique déjà en place), et un résultat prévisible ("dernière écriture
     * gagne").
     */
    public function test_answering_the_same_question_twice_never_errors_and_keeps_the_latest_value(): void
    {
        $start = $this->postJson('/api/quiz/start');
        $uuid = $start->json('quiz_result_uuid');
        $sessionToken = $start->json('session_token');
        $questionId = $this->questions[0]->id;

        $first = $this->postJson('/api/answers', [
            'quiz_result_uuid' => $uuid,
            'question_id' => $questionId,
            'user_score' => -2,
            'was_skipped' => false,
            'session_token' => $sessionToken,
        ]);
        $second = $this->postJson('/api/answers', [
            'quiz_result_uuid' => $uuid,
            'question_id' => $questionId,
            'user_score' => 2,
            'was_skipped' => false,
            'session_token' => $sessionToken,
        ]);

        $first->assertStatus(200);
        $second->assertStatus(200);
        $this->assertDatabaseCount('answers', 1);
        $this->assertDatabaseHas('answers', ['question_id' => $questionId, 'user_score' => 2]);
    }

    public function test_completing_an_unfinished_quiz_is_rejected(): void
    {
        $start = $this->postJson('/api/quiz/start');
        $uuid = $start->json('quiz_result_uuid');
        $sessionToken = $start->json('session_token');

        $this->postJson('/api/answers', [
            'quiz_result_uuid' => $uuid,
            'question_id' => $this->questions[0]->id,
            'user_score' => 1,
            'session_token' => $sessionToken,
        ]);

        $this->postJson('/api/quiz/complete', [
            'quiz_result_uuid' => $uuid,
            'session_token' => $sessionToken,
        ])->assertStatus(422);
    }
}
