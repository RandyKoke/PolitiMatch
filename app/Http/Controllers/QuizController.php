<?php

namespace App\Http\Controllers;

use App\Enums\QuizResultStatus;
use App\Http\Requests\Quiz\CompleteQuizRequest;
use App\Http\Requests\Quiz\QuizStateRequest;
use App\Http\Requests\Quiz\RetryQuizRequest;
use App\Http\Requests\Quiz\StoreAnswerRequest;
use App\Models\Answer;
use App\Models\GuestSession;
use App\Models\Question;
use App\Models\QuizResult;
use App\Enums\QuizReliabilityState;
use App\Repositories\AnswerRepository;
use App\Services\MatchingService;
use App\Services\QuizAccessService;
use App\Services\QuizReliabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class QuizController extends Controller
{
    // Durée de vie d'une session invité créée au démarrage d'un quiz, et
    // fenêtre de grâce utilisée par le job planifié de purge (cahier des
    // charges §5.2, PurgeExpiredGuestSessions) : un visiteur qui revient
    // dans ce délai retrouve sa session intacte ; passé ce délai sans
    // migration vers un compte, la session (et le QuizResult associé) est
    // supprimée. La purge automatique suffit à elle seule au besoin
    // d'hygiène de la base de données : réduire ce délai pénaliserait
    // inutilement le confort d'un visiteur qui reviendrait plusieurs jours
    // plus tard sans compte.
    private const GUEST_SESSION_LIFETIME_DAYS = 30;

    public function __construct(
        private readonly AnswerRepository $answers,
        private readonly QuizAccessService $access,
        private readonly MatchingService $matchingService,
        private readonly QuizReliabilityService $reliability,
    ) {}

    /**
     * Questionnaire actif — change rarement, mis en cache (cf. cahier des
     * charges §13).
     */
    public function questions(): JsonResponse
    {
        return response()->json(['questions' => $this->loadActiveQuestions()]);
    }

    /**
     * Mis en cache sous forme de tableau brut (toArray()), jamais d'objets
     * Eloquent : config('cache.serializable_classes') vaut délibérément
     * `false` dans ce projet (durcissement sécurité par défaut, empêche la
     * désérialisation de n'importe quelle classe depuis le cache database —
     * protection contre l'injection d'objet si la table `cache` était un
     * jour compromise). Avec ce réglage, `unserialize(..., ['allowed_classes'
     * => false])` renvoie un `__PHP_Incomplete_Class` pour tout objet
     * (Collection, Question, l'enum AxisType...), silencieusement — un bug
     * préexistant resté invisible tant que rien ne déclarait de type de
     * retour strict dessus. Ne jamais assouplir `serializable_classes` pour
     * contourner ça : mettre en cache des données brutes est la bonne
     * pratique de toute façon (payload plus léger, aucune dépendance à
     * l'état interne des modèles).
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadActiveQuestions(): array
    {
        return Cache::remember('quiz.questions.active', now()->addHour(), function () {
            return Question::active()
                ->with('theme')
                ->get(['id', 'theme_id', 'label', 'explanation', 'weight', 'axe_ideologique', 'position_order'])
                ->toArray();
        });
    }

    /**
     * Démarre un quiz : GuestSession + QuizResult pour un visiteur anonyme,
     * ou simple QuizResult rattaché au compte pour un utilisateur connecté.
     */
    public function start(): JsonResponse
    {
        if (Auth::check()) {
            $quizResult = QuizResult::create(['user_id' => Auth::id()]);

            return response()->json([
                'quiz_result_uuid' => $quizResult->uuid,
                'session_token' => null,
            ], 201);
        }

        $guestSession = GuestSession::create([
            'expires_at' => now()->addDays(self::GUEST_SESSION_LIFETIME_DAYS),
        ]);

        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token]);

        return response()->json([
            'quiz_result_uuid' => $quizResult->uuid,
            'session_token' => $guestSession->session_token,
        ], 201);
    }

    /**
     * Une réponse à la fois (30 requêtes séquentielles, choix délibéré —
     * cf. spec technique §8) : permet la reprise de session sur abandon.
     */
    public function storeAnswer(StoreAnswerRequest $request): JsonResponse
    {
        $data = $request->validated();

        $quizResult = QuizResult::where('uuid', $data['quiz_result_uuid'])->firstOrFail();
        $this->access->ensureAccess($quizResult, $data['session_token'] ?? null);

        if ($quizResult->status !== QuizResultStatus::Pending) {
            return response()->json([
                'message' => 'Ce quiz est déjà terminé ou en cours de calcul, impossible de modifier les réponses.',
            ], 409);
        }

        $answer = $this->answers->upsertAnswer(
            $quizResult->id,
            $data['question_id'],
            $data['user_score'],
            $data['was_skipped'] ?? false,
        );

        return response()->json(['answer' => $answer]);
    }

    /**
     * Reprise d'un quiz après rechargement de page (Guest Flow comme
     * utilisateur connecté) : le frontend ne connaît, après un F5, que
     * quiz_result_uuid et session_token (persistés en localStorage) — cet
     * endpoint reconstruit tout le reste en un seul appel plutôt que de
     * forcer deux requêtes séparées (questions + réponses déjà données).
     *
     * Toujours 200, quel que soit le statut du quiz : c'est un endpoint de
     * lecture pure, jamais une action de modification — la règle "on ne
     * modifie plus un quiz non-pending" reste appliquée uniquement là où
     * elle a du sens, côté écriture (storeAnswer, 409 ci-dessus). Le champ
     * `status` de la réponse permet au frontend de décider lui-même s'il
     * doit reprendre le quiz (pending) ou rediriger directement vers les
     * résultats (completed).
     */
    public function state(QuizStateRequest $request, QuizResult $quizResult): JsonResponse
    {
        $data = $request->validated();
        $this->access->ensureAccess($quizResult, $data['session_token'] ?? null);

        $answers = $this->answers->loadAnswersForQuiz($quizResult->id);

        return response()->json([
            'quiz_result' => [
                'uuid' => $quizResult->uuid,
                'status' => $quizResult->status,
                'completed_at' => $quizResult->completed_at,
                'user_id' => $quizResult->user_id,
                'session_token' => $quizResult->session_token,
            ],
            'answers' => $answers->map(fn (Answer $answer) => [
                'question_id' => $answer->question_id,
                'user_score' => $answer->user_score,
                'was_skipped' => $answer->was_skipped,
                'answered_at' => $answer->answered_at,
            ])->values(),
            'questions' => $this->loadActiveQuestions(),
        ]);
    }

    public function complete(CompleteQuizRequest $request): JsonResponse
    {
        $data = $request->validated();

        $quizResult = QuizResult::where('uuid', $data['quiz_result_uuid'])->firstOrFail();
        $this->access->ensureAccess($quizResult, $data['session_token'] ?? null);

        // 25 à 30 questions selon le contenu réellement actif (cahier des
        // charges §5.1) : jamais un compte figé en dur à 30.
        $expectedCount = Question::active()->count();
        $answeredCount = $this->answers->countForQuiz($quizResult->id);

        if ($answeredCount < $expectedCount) {
            return response()->json([
                'message' => "Le quiz n'est pas terminé : {$answeredCount}/{$expectedCount} question(s) répondue(s).",
            ], 422);
        }

        return response()->json($this->matchingService->computeMatching($quizResult->id));
    }

    public function retry(RetryQuizRequest $request, QuizResult $quizResult): JsonResponse
    {
        $data = $request->validated();
        $this->access->ensureAccess($quizResult, $data['session_token'] ?? null);

        $reset = QuizResult::where('id', $quizResult->id)
            ->where('status', QuizResultStatus::Failed)
            ->update(['status' => QuizResultStatus::Pending]);

        if ($reset === 0) {
            return response()->json([
                'message' => "Ce quiz n'est pas en échec, impossible de relancer le calcul.",
            ], 409);
        }

        return response()->json($this->matchingService->computeMatching($quizResult->id));
    }

    /**
     * Reprise ciblée : réouvre un QuizResult Completed dont la fiabilité
     * est "too_few" (0 < réponses réelles < MIN_RELIABLE_ANSWERS)
     * pour permettre à l'utilisateur de répondre spécifiquement aux
     * questions qu'il avait passées — jamais pour un résultat déjà fiable
     * (rouvrir un bon résultat n'aurait aucun sens et casserait un lien de
     * partage actif sans raison) ni pour un résultat vide (0 réponse : cf.
     * le flux "Refaire le test" distinct côté frontend, qui relance un quiz
     * entièrement neuf plutôt que de rouvrir un QuizResult sans aucun
     * signal exploitable — inutile de faire "reprendre" un quiz à quelqu'un
     * qui n'a en réalité jamais commencé à y répondre).
     *
     * Repasse le statut à Pending : storeAnswer() redevient alors utilisable
     * pour ces questions précises (was_skipped=true → une vraie réponse,
     * via le même upsert atomique que d'habitude, aucun risque de doublon).
     * Le frontend (quizStore.resumeSkippedOnly) détermine ensuite lui-même,
     * à partir de was_skipped sur chaque Answer déjà renvoyée par
     * GET /quiz/{uuid}/state, quelles questions précises représenter à
     * l'utilisateur — aucune donnée supplémentaire à renvoyer ici.
     */
    public function resumeSkipped(RetryQuizRequest $request, QuizResult $quizResult): JsonResponse
    {
        $data = $request->validated();
        $this->access->ensureAccess($quizResult, $data['session_token'] ?? null);

        if ($quizResult->status !== QuizResultStatus::Completed) {
            return response()->json([
                'message' => "Ce résultat n'est pas terminé, impossible de reprendre les questions passées.",
            ], 409);
        }

        $reliabilityData = $this->reliability->evaluate($quizResult->loadMissing('answers')->answers);
        if ($reliabilityData['state'] !== QuizReliabilityState::TooFew) {
            return response()->json([
                'message' => "Ce résultat ne nécessite pas de reprise ciblée.",
            ], 409);
        }

        $quizResult->update(['status' => QuizResultStatus::Pending]);

        return response()->json(['quiz_result_uuid' => $quizResult->uuid]);
    }
}
