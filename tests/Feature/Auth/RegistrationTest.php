<?php

namespace Tests\Feature\Auth;

use App\Models\GuestSession;
use App\Models\QuizResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username' => 'randyk',
            'email' => 'randy@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(201)
            ->assertJson(['account_created' => true, 'migrated' => false])
            ->assertJsonMissingPath('user.password_hash');

        $this->assertDatabaseHas('users', ['email' => 'randy@example.com', 'username' => 'randyk']);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'username' => 'nouveau',
            'email' => 'dup@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Sens inverse, OAuth d'abord puis tentative classique : un compte créé
     * exclusivement via OAuth (password_hash
     * NULL, cf. SocialAuthService::handleCallback) doit bloquer une
     * inscription classique sur la même adresse email, avec la même
     * contrainte d'unicité que n'importe quel autre compte — pas de
     * traitement spécial ni de fuite d'information sur l'origine du compte.
     */
    public function test_registration_fails_with_an_email_already_used_by_an_oauth_only_account(): void
    {
        User::factory()->create(['email' => 'oauth@example.com', 'password_hash' => null]);

        $response = $this->postJson('/api/auth/register', [
            'username' => 'nouveauclassique',
            'email' => 'oauth@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_registration_fails_with_duplicate_username(): void
    {
        User::factory()->create(['username' => 'randyk']);

        $response = $this->postJson('/api/auth/register', [
            'username' => 'randyk',
            'email' => 'autreadresse@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(422);
    }

    /**
     * throttle:5,1 sur /api/auth/register (routes/api.php) : zone sensible,
     * ne doit jamais régresser silencieusement — c'est la protection contre
     * l'énumération/brute force sur cette route.
     */
    public function test_registration_route_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/register', []);
        }

        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(429);
    }

    /**
     * Choix retenu dans la grille de propositions (AvatarPicker), envoyé
     * avec la requête d'inscription : doit être exactement ce qui est
     * persisté, pas un seed régénéré côté serveur qui ignorerait le choix.
     */
    public function test_registration_persists_the_chosen_avatar_seed(): void
    {
        $seed = (string) Str::uuid();

        $response = $this->postJson('/api/auth/register', [
            'username' => 'avecavatar',
            'email' => 'avecavatar@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'avatar_seed' => $seed,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'avecavatar@example.com', 'avatar_seed' => $seed]);
    }

    /**
     * Comportement de repli : aucun avatar_seed transmis (JS désactivé,
     * échec du chargement des suggestions...) ne doit jamais bloquer la
     * création du compte — un seed aléatoire est généré côté serveur.
     */
    public function test_registration_falls_back_to_a_random_avatar_seed_when_none_is_provided(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username' => 'sansavatar',
            'email' => 'sansavatar@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(201);
        $user = User::where('email', 'sansavatar@example.com')->firstOrFail();
        $this->assertNotNull($user->avatar_seed);
        $this->assertTrue(Str::isUuid($user->avatar_seed));
    }

    /**
     * Ne jamais faire confiance à une valeur brute envoyée par le client :
     * un avatar_seed qui n'est pas un UUID valide doit être rejeté, pas
     * accepté tel quel.
     */
    public function test_registration_rejects_a_non_uuid_avatar_seed(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username' => 'mauvaisavatar',
            'email' => 'mauvaisavatar@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'avatar_seed' => 'ceci-nest-pas-un-uuid',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Scénario critique explicitement demandé : un échec de migration de
     * session invité ne doit jamais faire perdre la création du compte.
     */
    public function test_account_is_created_even_when_session_migration_fails(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'username' => 'sansmigration',
            'email' => 'sansmigration@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ], [
            'X-Session-Token' => 'jeton-inexistant-00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertStatus(201)->assertJson([
            'account_created' => true,
            'migrated' => false,
            'reason' => 'session_not_found',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'sansmigration@example.com']);
    }

    /**
     * Scénario distinct de "session inconnue" ci-dessus : une session invité
     * qui a réellement existé mais dont
     * expires_at est dépassé (AccountMigrationService::migrate le détecte
     * déjà, cf. AccountMigrationServiceTest côté service). Ce test vérifie
     * le comportement de bout en bout via l'endpoint réel d'inscription :
     * la création du compte ne doit jamais dépendre du succès de la
     * migration, y compris pour ce motif d'échec précis.
     */
    public function test_account_is_created_even_when_guest_session_has_expired(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->subDay()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token, 'completed_at' => now()]);

        $response = $this->postJson('/api/auth/register', [
            'username' => 'sessionexpiree',
            'email' => 'sessionexpiree@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ], [
            'X-Session-Token' => $guestSession->session_token,
        ]);

        $response->assertStatus(201)->assertJson([
            'account_created' => true,
            'migrated' => false,
            'reason' => 'session_expired',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'sessionexpiree@example.com']);
        // Aucune perte de donnée côté invité : le QuizResult reste rattaché
        // à la session invité expirée, jamais migré silencieusement ni
        // supprimé.
        $this->assertNull($quizResult->fresh()->user_id);
        $this->assertNull($guestSession->fresh()->migrated_at);
    }

    public function test_account_creation_migrates_valid_guest_session(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create([
            'session_token' => $guestSession->session_token,
            'completed_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/register', [
            'username' => 'avecmigration',
            'email' => 'avecmigration@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ], [
            'X-Session-Token' => $guestSession->session_token,
        ]);

        $response->assertStatus(201)->assertJson(['migrated' => true, 'reason' => null]);

        $user = User::where('email', 'avecmigration@example.com')->firstOrFail();
        $this->assertSame($user->id, $quizResult->fresh()->user_id);
        $this->assertNotNull($guestSession->fresh()->migrated_at);
    }
}
