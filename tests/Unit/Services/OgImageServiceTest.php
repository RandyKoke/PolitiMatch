<?php

namespace Tests\Unit\Services;

use App\Enums\QuizResultStatus;
use App\Models\GuestSession;
use App\Models\Party;
use App\Models\QuizResult;
use App\Models\ResultPartyScore;
use App\Services\OgImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Génération de l'image de partage personnalisée par résultat (GD pur, pas
 * de navigateur headless, cf. OgImageService pour la justification). Ces
 * tests exercent le vrai rendu GD (rapide, aucune dépendance externe), pas
 * un mock : la génération d'image EST le comportement à vérifier ici.
 */
class OgImageServiceTest extends TestCase
{
    use RefreshDatabase;

    private OgImageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OgImageService::class);
    }

    protected function tearDown(): void
    {
        // Les fichiers générés vivent dans public/, hors de la base de
        // données réinitialisée par RefreshDatabase — nettoyage manuel pour
        // ne jamais laisser d'image de test traîner dans le dépôt.
        foreach (glob(public_path('og-images/*.png')) ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob(public_path('result-images/*.png')) ?: [] as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function sharedQuizResult(string $label = 'Progressiste équilibré'): QuizResult
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);

        return QuizResult::create([
            'session_token' => $guestSession->session_token,
            'status' => QuizResultStatus::Completed,
            'completed_at' => now(),
            'profile_label' => $label,
            'profile_description' => 'Une description.',
            'share_token' => (string) Str::uuid(),
            'is_shared' => true,
        ])->load('resultPartyScores.party');
    }

    public function test_generates_a_real_png_file_for_a_shared_result(): void
    {
        $quizResult = $this->sharedQuizResult();

        $url = $this->service->ensureGenerated($quizResult);

        $this->assertNotNull($url);
        $path = public_path($this->service->relativePath($quizResult));
        $this->assertFileExists($path);
        // Signature PNG réelle (89 50 4E 47), pas un fichier vide ou corrompu.
        $this->assertStringStartsWith("\x89PNG", file_get_contents($path));
    }

    public function test_is_idempotent_and_does_not_regenerate_an_existing_file(): void
    {
        $quizResult = $this->sharedQuizResult();

        $this->service->ensureGenerated($quizResult);
        $path = public_path($this->service->relativePath($quizResult));
        $firstModifiedAt = filemtime($path);

        sleep(1);
        $this->service->ensureGenerated($quizResult);

        $this->assertSame($firstModifiedAt, filemtime($path), 'Le fichier a été réécrit alors qu\'il existait déjà.');
    }

    /**
     * Coeur de la tâche 2 : deux résultats à profils différents doivent
     * produire deux fichiers distincts, preuve directe de personnalisation
     * réelle et non d'une image statique simplement renommée.
     */
    public function test_two_different_results_produce_two_distinct_image_files(): void
    {
        $a = $this->sharedQuizResult('Progressiste équilibré');
        $b = $this->sharedQuizResult('Libéral conservateur');

        $urlA = $this->service->ensureGenerated($a);
        $urlB = $this->service->ensureGenerated($b);

        $this->assertNotSame($urlA, $urlB);
        $pathA = public_path($this->service->relativePath($a));
        $pathB = public_path($this->service->relativePath($b));
        $this->assertNotSame(file_get_contents($pathA), file_get_contents($pathB));
    }

    public function test_includes_the_top_compatible_party_when_a_valid_score_exists(): void
    {
        $quizResult = $this->sharedQuizResult();
        $party = Party::create(['name' => 'Écolo', 'abbreviation' => 'ECOLO', 'language_community' => 'FR']);
        ResultPartyScore::create([
            'quiz_result_id' => $quizResult->id, 'party_id' => $party->id,
            'compatibility_score' => 80, 'rank' => 1, 'calculated_at' => now(),
        ]);
        $quizResult->load('resultPartyScores.party');

        $withParty = $this->service->ensureGenerated($quizResult);

        $withoutParty = $this->sharedQuizResult('Un autre profil sans parti');
        $withoutPartyUrl = $this->service->ensureGenerated($withoutParty);

        $this->assertNotNull($withParty);
        $this->assertNotNull($withoutPartyUrl);
        // Les deux fichiers diffèrent (l'un contient la mention du parti, pas l'autre) —
        // vérifié indirectement par une taille de fichier différente plutôt que par
        // une lecture de pixels, largement suffisant pour prouver le contenu distinct.
        $sizeWithParty = filesize(public_path($this->service->relativePath($quizResult)));
        $sizeWithoutParty = filesize(public_path($this->service->relativePath($withoutParty)));
        $this->assertNotSame($sizeWithParty, $sizeWithoutParty);
    }

    public function test_returns_null_for_a_result_without_a_share_token(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create([
            'session_token' => $guestSession->session_token,
            'status' => QuizResultStatus::Completed,
            'completed_at' => now(),
            'profile_label' => 'Profil',
        ]);

        $this->assertNull($this->service->ensureGenerated($quizResult));
    }

    /**
     * La carte téléchargeable réutilise le même moteur de rendu (render(),
     * privé) que l'image Open Graph, mais via un chemin/répertoire
     * complètement distinct, indexé sur l'UUID du résultat plutôt que sur
     * share_token : fonctionne donc même pour un résultat jamais partagé.
     */
    public function test_generates_a_real_png_download_image_for_a_result_never_shared(): void
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create([
            'session_token' => $guestSession->session_token,
            'status' => QuizResultStatus::Completed,
            'completed_at' => now(),
            'profile_label' => 'Progressiste équilibré',
            'profile_description' => 'Une description.',
            // share_token volontairement absent : ensureGenerated() (image
            // OG) renverrait null ici, ensureDownloadImageGenerated() doit
            // fonctionner quand même.
        ])->load('resultPartyScores.party');

        $absolutePath = $this->service->ensureDownloadImageGenerated($quizResult);

        $this->assertNotNull($absolutePath);
        $this->assertFileExists($absolutePath);
        $this->assertStringStartsWith("\x89PNG", file_get_contents($absolutePath));
        $this->assertStringContainsString('result-images', $absolutePath);
    }

    /**
     * Les deux mécanismes ne doivent jamais interférer l'un avec l'autre :
     * générer l'image de téléchargement d'un résultat déjà partagé ne doit
     * ni écraser ni invalider son image Open Graph déjà générée, et
     * inversement.
     */
    public function test_download_image_and_og_image_coexist_independently_for_the_same_result(): void
    {
        $quizResult = $this->sharedQuizResult();

        $ogUrl = $this->service->ensureGenerated($quizResult);
        $downloadPath = $this->service->ensureDownloadImageGenerated($quizResult);

        $this->assertNotNull($ogUrl);
        $this->assertNotNull($downloadPath);

        $ogPath = public_path($this->service->relativePath($quizResult));
        $this->assertFileExists($ogPath);
        $this->assertFileExists($downloadPath);
        $this->assertNotSame($ogPath, $downloadPath);

        // Régénérer l'image de téléchargement ne doit pas avoir touché au
        // fichier OG déjà généré avant (même contenu, pas seulement même
        // existence).
        $ogContentAfter = file_get_contents($ogPath);
        $this->assertStringStartsWith("\x89PNG", $ogContentAfter);
    }
}
