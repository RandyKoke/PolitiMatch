<?php

namespace Tests;

use App\Http\Controllers\QuizController;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    /**
     * Corps de requête minimal et valide pour POST /api/quiz/start depuis
     * que StartQuizRequest exige un consentement RGPD explicite : centralisé
     * ici pour que tous les tests restent corrects si
     * QuizController::CURRENT_CONSENT_VERSION change un jour.
     *
     * @return array<string, mixed>
     */
    protected function validConsentPayload(): array
    {
        return [
            'consent' => true,
            'consent_version' => QuizController::CURRENT_CONSENT_VERSION,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Une vraie requête de la SPA envoie toujours un Referer/Origin
        // same-origin ; sans ça, Sanctum ne considère pas la requête comme
        // "stateful" et ne démarre pas la session (EnsureFrontendRequestsAreStateful).
        $this->withHeader('Referer', config('app.url'));

        // Le driver de cache "array" (CACHE_STORE=array, phpunit.xml) n'est
        // pas réinitialisé automatiquement entre deux méthodes de test,
        // contrairement à la base de données (RefreshDatabase). Sans ce
        // flush, les compteurs du middleware `throttle` (RateLimiter,
        // adossé à ce même cache) s'accumuleraient silencieusement d'un
        // test à l'autre au sein d'un même run, jusqu'à déclencher un 429
        // dans un test sans aucun rapport avec le rate limiting.
        Cache::flush();
    }
}
