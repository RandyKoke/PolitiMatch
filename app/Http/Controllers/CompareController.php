<?php

namespace App\Http\Controllers;

use App\Enums\QuizResultStatus;
use App\Http\Requests\Quiz\CompareRequest;
use App\Models\Party;
use App\Models\Question;
use App\Models\QuizResult;
use App\Repositories\AnswerRepository;
use App\Repositories\PartyPositionRepository;
use App\Services\QuizAccessService;
use App\Services\QuizReliabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CompareController extends Controller
{
    public function __construct(
        private readonly QuizAccessService $access,
        private readonly AnswerRepository $answers,
        private readonly PartyPositionRepository $partyPositions,
        private readonly QuizReliabilityService $reliability,
    ) {}

    /**
     * Tableau comparateur (cf. cahier des charges, Module Comparateur) :
     * positions de l'utilisateur face aux 6 partis, filtrable par thème ou
     * par axe. Fonctionne aussi bien pour un quiz encore en cours (invité
     * comme connecté) que terminé — ce n'est pas le score de compatibilité
     * calculé (ResultPartyScore), juste une confrontation brute
     * question par question.
     *
     * La seule exception à ce fonctionnement délibérément permissif est un
     * QuizResult Completed dont la fiabilité est bloquante (Empty/TooFew),
     * jamais un quiz encore en cours (Pending), pour lequel le comparateur
     * reste volontairement accessible comme avant.
     */
    public function index(CompareRequest $request): JsonResponse
    {
        $data = $request->validated();

        $quizResult = QuizResult::where('uuid', $data['quiz_result_uuid'])->firstOrFail();
        $this->access->ensureAccess($quizResult, $data['session_token'] ?? null);

        $rawAnswers = $this->answers->loadAnswersForQuiz($quizResult->id);

        if ($quizResult->status === QuizResultStatus::Completed) {
            $reliabilityData = $this->reliability->evaluate($rawAnswers);
            if ($reliabilityData['state']->isBlocked()) {
                return response()->json([
                    'quiz_uuid' => $quizResult->uuid,
                    'blocked' => true,
                    'reliability' => $reliabilityData,
                ]);
            }
        }

        $static = $this->loadStaticComparisonData($data['theme_id'] ?? null, $data['axe_ideologique'] ?? null);
        $userAnswers = $rawAnswers->keyBy('question_id');

        return response()->json([
            'quiz_uuid' => $quizResult->uuid,
            'has_answers' => $userAnswers->isNotEmpty(),
            'questions' => $static['questions'],
            'user' => [
                'scores' => collect($static['question_ids'])->mapWithKeys(function (int $questionId) use ($userAnswers) {
                    $answer = $userAnswers->get($questionId);
                    $score = ($answer !== null && ! $answer->was_skipped) ? $answer->user_score : null;

                    return [$questionId => $score];
                }),
            ],
            'parties' => $static['parties'],
        ]);
    }

    /**
     * Questions + positions partis + liste des partis : indépendant des
     * réponses de l'utilisateur, donc mis en cache (contenu qui change très
     * peu — cf. cahier des charges §13).
     *
     * Uniquement des tableaux/scalaires bruts dans la valeur mise en cache,
     * jamais un objet Collection (même une Collection d'entiers) : voir le
     * commentaire détaillé sur QuizController::loadActiveQuestions() pour
     * le pourquoi (config('cache.serializable_classes') = false).
     *
     * @return array{questions: array, question_ids: array<int, int>, parties: array}
     */
    private function loadStaticComparisonData(?int $themeId, ?string $axeIdeologique): array
    {
        $cacheKey = 'compare.static.'.($themeId ?? 'all').'.'.($axeIdeologique ?? 'all');

        return Cache::remember($cacheKey, now()->addHour(), function () use ($themeId, $axeIdeologique) {
            $questionsQuery = Question::active()->with('theme:id,name');
            if ($themeId !== null) {
                $questionsQuery->where('theme_id', $themeId);
            }
            if ($axeIdeologique !== null) {
                $questionsQuery->where('axe_ideologique', $axeIdeologique);
            }
            $questions = $questionsQuery->get();
            $questionIds = $questions->pluck('id');

            $positionsByParty = $this->partyPositions->loadPositionsForQuestions($questionIds)->groupBy('party_id');
            $parties = Party::where('is_active', true)->orderBy('name')->get();

            return [
                'questions' => $questions->map(fn (Question $q) => [
                    'id' => $q->id,
                    'label' => $q->label,
                    'theme_name' => $q->theme->name,
                    'axe_ideologique' => $q->axe_ideologique,
                    'weight' => $q->weight,
                ])->values()->all(),
                'question_ids' => $questionIds->all(),
                'parties' => $parties->map(function (Party $party) use ($positionsByParty, $questions) {
                    $partyPositions = ($positionsByParty->get($party->id) ?? collect())->keyBy('question_id');

                    return [
                        'id' => $party->id,
                        'name' => $party->name,
                        'color_hex' => $party->color_hex,
                        'positions' => $questions->mapWithKeys(function (Question $q) use ($partyPositions) {
                            $position = $partyPositions->get($q->id);

                            return [$q->id => $position ? [
                                'score' => $position->score,
                                'justification' => $position->justification,
                                'source_reference' => $position->source_reference,
                            ] : null];
                        })->all(),
                    ];
                })->values()->all(),
            ];
        });
    }
}
