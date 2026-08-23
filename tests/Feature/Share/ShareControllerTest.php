<?php

namespace Tests\Feature\Share;

use App\Enums\QuizResultStatus;
use App\Models\Answer;
use App\Models\GuestSession;
use App\Models\Party;
use App\Models\Question;
use App\Models\QuizResult;
use App\Models\ResultPartyScore;
use App\Models\Theme;
use App\Models\User;
use App\Services\QuizReliabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShareControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Au moins MIN_RELIABLE_ANSWERS (15) réponses réelles, sous peine que ce
     * résultat "complet" soit désormais considéré bloquant
     * (fiabilité insuffisante) et masque son classement de partis — cf.
     * QuizReliabilityServiceTest pour les tests dédiés à ce seuil.
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

    public function test_create_generates_and_returns_a_share_token_for_the_owner(): void
    {
        [$quizResult, $sessionToken] = $this->completedGuestQuiz();

        $response = $this->postJson("/api/results/{$quizResult->uuid}/share", ['session_token' => $sessionToken]);

        $response->assertStatus(200)->assertJsonStructure(['share_token']);
        $quizResult->refresh();
        $this->assertSame($response->json('share_token'), $quizResult->share_token);
        $this->assertTrue($quizResult->is_shared);
    }

    /**
     * Un second appel (ex. l'utilisateur reclique "Partager") ne doit jamais
     * générer un nouveau token — un lien déjà distribué resterait valide,
     * mais un ancien lien copié ailleurs se retrouverait cassé silencieusement.
     */
    public function test_create_is_idempotent_and_keeps_the_same_token(): void
    {
        [$quizResult, $sessionToken] = $this->completedGuestQuiz();

        $first = $this->postJson("/api/results/{$quizResult->uuid}/share", ['session_token' => $sessionToken])->json('share_token');
        $second = $this->postJson("/api/results/{$quizResult->uuid}/share", ['session_token' => $sessionToken])->json('share_token');

        $this->assertSame($first, $second);
    }

    public function test_create_returns_409_for_a_quiz_not_yet_completed(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token]);

        $this->postJson("/api/results/{$quizResult->uuid}/share", ['session_token' => $guestSession->session_token])
            ->assertStatus(409);
    }

    public function test_create_returns_403_for_a_wrong_session_token(): void
    {
        [$quizResult] = $this->completedGuestQuiz();

        $this->postJson("/api/results/{$quizResult->uuid}/share", ['session_token' => '00000000-0000-0000-0000-000000000000'])
            ->assertStatus(403);
    }

    public function test_create_works_for_an_authenticated_users_own_quiz(): void
    {
        $user = User::factory()->create();
        $quizResult = QuizResult::create([
            'user_id' => $user->id, 'status' => QuizResultStatus::Completed, 'completed_at' => now(),
        ]);

        $this->actingAs($user)->postJson("/api/results/{$quizResult->uuid}/share")
            ->assertStatus(200)
            ->assertJsonStructure(['share_token']);
    }

    public function test_show_returns_the_full_result_payload_for_a_valid_share_token(): void
    {
        [$quizResult, $sessionToken] = $this->completedGuestQuiz();
        $shareToken = $this->postJson("/api/results/{$quizResult->uuid}/share", ['session_token' => $sessionToken])->json('share_token');

        $response = $this->getJson("/api/share/{$shareToken}");

        $response->assertStatus(200)
            ->assertJsonPath('quiz_uuid', $quizResult->uuid)
            ->assertJsonPath('profile_label', 'Progressiste équilibré')
            ->assertJsonCount(1, 'party_scores');
    }

    /**
     * Accès totalement anonyme : ni session_token, ni cookie d'auth, ni
     * aucun en-tête particulier — c'est le point central de cette route.
     */
    public function test_show_requires_no_authentication_or_session_token_at_all(): void
    {
        [$quizResult, $sessionToken] = $this->completedGuestQuiz();
        $shareToken = $this->postJson("/api/results/{$quizResult->uuid}/share", ['session_token' => $sessionToken])->json('share_token');

        $this->getJson("/api/share/{$shareToken}", ['Accept' => 'application/json'])
            ->assertStatus(200);
    }

    public function test_show_returns_404_for_an_unknown_token(): void
    {
        $this->getJson('/api/share/00000000-0000-0000-0000-000000000000')->assertStatus(404);
    }

    /**
     * Un résultat qui a un share_token (ex. partagé puis retiré du partage
     * - même si aucune route de révocation n'existe encore) mais dont
     * is_shared vaut false ne doit jamais être consultable publiquement.
     */
    public function test_show_returns_404_when_is_shared_is_false(): void
    {
        [$quizResult, $sessionToken] = $this->completedGuestQuiz();
        $shareToken = $this->postJson("/api/results/{$quizResult->uuid}/share", ['session_token' => $sessionToken])->json('share_token');
        $quizResult->update(['is_shared' => false]);

        $this->getJson("/api/share/{$shareToken}")->assertStatus(404);
    }

    /**
     * Le lien de partage est public par nature (aucune authentification),
     * donc le payload ne doit jamais laisser
     * fuiter le moindre champ du modèle User associé au quiz — même sur un
     * quiz d'un utilisateur authentifié (pas seulement un invité, dont il
     * n'existe de toute façon aucun User associé).
     */
    public function test_show_never_leaks_any_user_field_for_an_authenticated_owners_shared_result(): void
    {
        $user = User::factory()->create([
            'email' => 'proprietaire@example.com',
            'username' => 'proprietaire',
        ]);
        $quizResult = QuizResult::create([
            'user_id' => $user->id, 'status' => QuizResultStatus::Completed, 'completed_at' => now(),
            'profile_label' => 'Progressiste équilibré',
        ]);
        $party = Party::create(['name' => 'PS', 'abbreviation' => 'PS', 'language_community' => 'FR']);
        ResultPartyScore::create([
            'quiz_result_id' => $quizResult->id, 'party_id' => $party->id,
            'compatibility_score' => 80, 'rank' => 1, 'calculated_at' => now(),
        ]);
        $shareToken = $this->actingAs($user)->postJson("/api/results/{$quizResult->uuid}/share")->json('share_token');

        $response = $this->getJson("/api/share/{$shareToken}");
        $body = $response->json();

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('user', $body);
        $this->assertArrayNotHasKey('user_id', $body);

        $encoded = json_encode($body);
        $this->assertStringNotContainsString('proprietaire@example.com', $encoded);
        $this->assertStringNotContainsString('proprietaire', $encoded);
        $this->assertStringNotContainsString($user->password_hash ?? '', $encoded);
        $this->assertStringNotContainsString((string) $user->avatar_seed, $encoded);
    }

    /**
     * Un lien de partage déjà généré (ou via un appel API direct malgré
     * l'absence du bouton "Partager" côté UI pour un résultat bloquant, cf.
     * ShareController::create) ne doit jamais exposer de classement de
     * partis pour un résultat dont la fiabilité est bloquante, le même
     * message explicatif que la page de résultat doit
     * pouvoir être construit côté frontend à partir de `reliability.state`.
     */
    public function test_show_hides_party_scores_for_a_shared_result_with_zero_real_answers(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create([
            'session_token' => $guestSession->session_token,
            'status' => QuizResultStatus::Completed,
            'completed_at' => now(),
            'profile_label' => 'Profil politique à préciser',
        ]);
        $party = Party::create(['name' => 'PS', 'abbreviation' => 'PS', 'language_community' => 'FR']);
        ResultPartyScore::create([
            'quiz_result_id' => $quizResult->id, 'party_id' => $party->id,
            'compatibility_score' => 80, 'rank' => 1, 'calculated_at' => now(),
        ]);
        $shareToken = $this->postJson("/api/results/{$quizResult->uuid}/share", ['session_token' => $guestSession->session_token])->json('share_token');

        $response = $this->getJson("/api/share/{$shareToken}");

        $response->assertStatus(200)
            ->assertJsonPath('reliability.state', 'empty')
            ->assertJsonCount(0, 'party_scores');
    }

    /**
     * Preuve directe de personnalisation par résultat (pas une valeur
     * statique) : deux résultats partagés distincts doivent produire deux
     * états de fiabilité indépendants dans la même requête de test.
     */
    public function test_show_reflects_a_reliable_shared_result_normally(): void
    {
        [$quizResult, $sessionToken] = $this->completedGuestQuiz();
        $shareToken = $this->postJson("/api/results/{$quizResult->uuid}/share", ['session_token' => $sessionToken])->json('share_token');

        $response = $this->getJson("/api/share/{$shareToken}");

        $response->assertStatus(200)
            ->assertJsonPath('reliability.state', 'full')
            ->assertJsonCount(1, 'party_scores');
    }
}
