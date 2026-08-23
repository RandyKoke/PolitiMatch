<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Nettoyage des routes OAuth orphelines : /auth/{provider}/redirect et
 * /callback n'étaient plus filtrées que par l'enum SocialProvider complet
 * (google, facebook, github), pas par la configuration réelle
 * (config/services.php, qui ne contient plus que google depuis le nettoyage
 * précédent). Corrigé dans SocialAuthController (pas dans routes/web.php,
 * cf. commentaire de ce fichier pour la raison exacte : deux routes
 * enregistrées avec la même URI littérale s'écrasent l'une l'autre dans
 * RouteCollection, indépendamment de leurs contraintes where() respectives).
 */
class SocialAuthRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_redirect_route_is_functional_for_the_only_configured_provider(): void
    {
        $response = $this->get('/auth/google/redirect');

        // 302 vers l'écran de consentement Google, jamais 404 : la route
        // matche bien et dispatche vers le vrai contrôleur pour un provider
        // réellement configuré.
        $response->assertStatus(302);
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_facebook_routes_return_a_clean_404(): void
    {
        $this->get('/auth/facebook/redirect')->assertStatus(404);
        $this->get('/auth/facebook/callback')->assertStatus(404);
    }

    public function test_github_routes_return_a_clean_404(): void
    {
        $this->get('/auth/github/redirect')->assertStatus(404);
        $this->get('/auth/github/callback')->assertStatus(404);
    }

    /**
     * Provider qui n'existe même pas dans l'enum SocialProvider : doit
     * recevoir exactement le même 404 propre, jamais une ValueError brute
     * (SocialProvider::tryFrom(), pas ::from(), dans le contrôleur).
     */
    public function test_an_entirely_unknown_provider_returns_a_clean_404_instead_of_a_raw_exception(): void
    {
        $response = $this->get('/auth/instagram/redirect');

        $response->assertStatus(404);
    }

    /**
     * Non-régression : la connexion Google reste entièrement fonctionnelle
     * de bout en bout (route -> contrôleur -> SocialAuthService -> création
     * de compte -> redirection avec statut=authenticated), pas seulement au
     * niveau du service déjà couvert isolément par SocialAuthServiceTest.
     */
    public function test_google_callback_still_works_end_to_end_after_the_routing_change(): void
    {
        $providerMock = Mockery::mock(SocialiteProvider::class);
        $providerMock->shouldReceive('user')->andReturn(
            SocialiteUser::fake(['id' => 'google-e2e-1', 'email' => 'e2e@example.com']),
        );
        Socialite::shouldReceive('driver')->with('google')->andReturn($providerMock);

        $response = $this->get('/auth/google/callback?code=fake-code');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/oauth/callback', $location);
        $this->assertStringContainsString('status=authenticated', $location);
        $this->assertDatabaseHas('users', ['email' => 'e2e@example.com']);
    }
}
