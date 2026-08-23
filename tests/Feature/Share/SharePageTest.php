<?php

namespace Tests\Feature\Share;

use App\Enums\QuizResultStatus;
use App\Models\GuestSession;
use App\Models\QuizResult;
use App\Services\OgImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La coquille HTML de /share/{token} (ShareController::showPage,
 * routes/web.php) doit porter de vraies balises Open Graph/Twitter Card
 * reflétant le profil réellement partagé, jamais un titre générique
 * identique pour tous les résultats, et jamais de fuite sur l'existence
 * d'un résultat non partagé/inconnu, testée ici au niveau de la page HTML
 * plutôt que de l'API.
 */
class SharePageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Les images générées vivent dans public/, hors de la base
        // réinitialisée par RefreshDatabase : nettoyage manuel pour ne
        // jamais laisser d'image de test traîner dans le dépôt.
        foreach (glob(public_path('og-images/*.png')) ?: [] as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function sharedQuizResult(string $label, string $description): QuizResult
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);

        return QuizResult::create([
            'session_token' => $guestSession->session_token,
            'status' => QuizResultStatus::Completed,
            'completed_at' => now(),
            'profile_label' => $label,
            'profile_description' => $description,
            'is_shared' => true,
            'share_token' => (string) Str::uuid(),
        ]);
    }

    public function test_renders_the_real_profile_label_in_the_page_title_and_og_title(): void
    {
        $quizResult = $this->sharedQuizResult('Progressiste équilibré', 'Une description de profil.');

        $response = $this->get("/share/{$quizResult->share_token}");

        $response->assertStatus(200);
        $response->assertSee('<title>Mon profil politique sur PolitiMatch : Progressiste équilibré</title>', false);
        $response->assertSee('<meta property="og:title" content="Mon profil politique sur PolitiMatch : Progressiste équilibré">', false);
    }

    public function test_renders_the_real_profile_description_in_og_and_twitter_tags(): void
    {
        $quizResult = $this->sharedQuizResult('Libéral modéré', 'Description spécifique et distincte.');

        $response = $this->get("/share/{$quizResult->share_token}");

        $response->assertSee('<meta property="og:description" content="Description spécifique et distincte.">', false);
        $response->assertSee('<meta name="twitter:description" content="Description spécifique et distincte.">', false);
    }

    /**
     * og:image pointe vers l'image personnalisée générée par résultat
     * (public/og-images/{token}.png), pas l'image générique.
     */
    public function test_includes_an_absolute_personalized_og_image_url(): void
    {
        $quizResult = $this->sharedQuizResult('Profil X', 'Description X.');

        $response = $this->get("/share/{$quizResult->share_token}");

        $response->assertStatus(200);
        $body = $response->getContent();
        $this->assertMatchesRegularExpression(
            '#<meta property="og:image" content="https?://[^"]+/og-images/'.preg_quote($quizResult->share_token, '#').'\.png">#',
            $body
        );
    }

    /**
     * Coeur de la tâche 2 au niveau HTTP : deux résultats à profils
     * distincts doivent recevoir deux URL og:image DIFFÉRENTES — preuve
     * qu'il ne s'agit pas d'une image statique renommée mais d'une
     * personnalisation réelle par résultat.
     */
    public function test_two_different_results_produce_two_different_og_image_urls(): void
    {
        $a = $this->sharedQuizResult('Profil A', 'Description A.');
        $b = $this->sharedQuizResult('Profil B', 'Description B.');

        $bodyA = $this->get("/share/{$a->share_token}")->getContent();
        $bodyB = $this->get("/share/{$b->share_token}")->getContent();

        preg_match('#<meta property="og:image" content="([^"]+)">#', $bodyA, $matchA);
        preg_match('#<meta property="og:image" content="([^"]+)">#', $bodyB, $matchB);

        $this->assertNotEmpty($matchA[1] ?? null);
        $this->assertNotEmpty($matchB[1] ?? null);
        $this->assertNotSame($matchA[1], $matchB[1]);
    }

    /**
     * Un résultat non partagé (ou un token inconnu) ne doit jamais recevoir
     * d'image personnalisée : ce serait une fuite indirecte de l'existence
     * d'un résultat privé, exactement comme pour og:title/og:description
     * (cf. test_falls_back_to_generic_tags_for_an_unknown_token... ci-dessus).
     */
    public function test_non_shared_result_still_receives_the_generic_image(): void
    {
        $notShared = $this->sharedQuizResult('Profil Privé', 'Description privée.');
        $notShared->update(['is_shared' => false]);

        $response = $this->get("/share/{$notShared->share_token}");

        $body = $response->getContent();
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="https?://[^"]+og-image\.png">#', $body);
        $this->assertStringNotContainsString('/og-images/', $body);
    }

    /**
     * Repli propre explicite : si la génération de l'image personnalisée
     * échoue pour une raison quelconque, la page doit
     * continuer à s'afficher normalement avec l'image générique, jamais une
     * page cassée.
     */
    public function test_falls_back_to_the_generic_image_when_generation_fails(): void
    {
        $this->mock(OgImageService::class, function ($mock) {
            $mock->shouldReceive('ensureGenerated')->andReturn(null);
        });

        $quizResult = $this->sharedQuizResult('Profil Y', 'Description Y.');

        $response = $this->get("/share/{$quizResult->share_token}");

        $response->assertStatus(200);
        $this->assertMatchesRegularExpression(
            '#<meta property="og:image" content="https?://[^"]+og-image\.png">#',
            $response->getContent()
        );
    }

    /**
     * Preuve directe de dynamisme (pas un texte statique identique partout,
     * cf. consigne explicite de l'audit) : deux résultats réels produisent
     * deux og:title distincts.
     */
    public function test_two_different_results_produce_two_different_og_titles(): void
    {
        $a = $this->sharedQuizResult('Profil A', 'Description A.');
        $b = $this->sharedQuizResult('Profil B', 'Description B.');

        $bodyA = $this->get("/share/{$a->share_token}")->getContent();
        $bodyB = $this->get("/share/{$b->share_token}")->getContent();

        $this->assertStringContainsString('Profil A', $bodyA);
        $this->assertStringContainsString('Profil B', $bodyB);
        $this->assertStringNotContainsString('Profil B', $bodyA);
        $this->assertStringNotContainsString('Profil A', $bodyB);
    }

    /**
     * Jamais de 404, et jamais un titre qui laisserait deviner si le token
     * est inconnu ou correspond à un résultat existant mais non partagé —
     * les deux cas doivent produire EXACTEMENT la même coquille générique
     * (cf. point 2 de cet audit, même exigence appliquée ici).
     */
    public function test_falls_back_to_generic_tags_for_an_unknown_token_without_leaking_its_existence(): void
    {
        $notShared = $this->sharedQuizResult('Profil Privé', 'Description privée.');
        $notShared->update(['is_shared' => false]);

        $unknownResponse = $this->get('/share/'.((string) Str::uuid()));
        $notSharedResponse = $this->get("/share/{$notShared->share_token}");

        // og:url reflète toujours le chemin réellement demandé (jamais une
        // fuite : c'est un simple écho, identique pour un token qui existe
        // ou non) — seules les balises qui POURRAIENT distinguer les deux
        // cas sont comparées ici, pas la page entière.
        $genericTitle = '<title>PolitiMatch</title>';
        $genericDescription = '<meta property="og:description" content="PolitiMatch aide les jeunes Belges francophones à comprendre leurs valeurs politiques et à trouver les partis qui leur correspondent, simplement et sans jargon.">';

        $unknownResponse->assertStatus(200)->assertSee($genericTitle, false)->assertSee($genericDescription, false);
        $notSharedResponse->assertStatus(200)->assertSee($genericTitle, false)->assertSee($genericDescription, false);
        $this->assertStringNotContainsString('Profil Privé', $notSharedResponse->getContent());
        $this->assertStringNotContainsString('Description privée', $notSharedResponse->getContent());
    }

    /**
     * La SPA doit continuer à démarrer normalement sous cette coquille : ce
     * n'est qu'un jeu de balises <head> différent, jamais une page à part
     * qui remplacerait l'application.
     */
    public function test_still_boots_the_spa_underneath(): void
    {
        $quizResult = $this->sharedQuizResult('Profil', 'Description.');

        $response = $this->get("/share/{$quizResult->share_token}");

        $response->assertSee('<div id="app"></div>', false);
    }
}
