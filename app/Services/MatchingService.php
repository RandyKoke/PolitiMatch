<?php

namespace App\Services;

use App\Enums\QuizResultStatus;
use App\Exceptions\AlreadyCompletedException;
use App\Exceptions\ComputationInProgressException;
use App\Exceptions\MatchingPersistenceException;
use App\Models\Party;
use App\Repositories\AnswerRepository;
use App\Repositories\PartyPositionRepository;
use App\Repositories\ResultRepository;
use Throwable;

class MatchingService
{
    public function __construct(
        private readonly ResultRepository $resultRepository,
        private readonly AnswerRepository $answerRepository,
        private readonly PartyPositionRepository $partyPositionRepository,
        private readonly ScoreCalculator $scoreCalculator,
        private readonly PoliticalAxisCalculator $axisCalculator,
        private readonly ProfileLabelService $profileLabelService,
    ) {}

    /**
     * @return array{quiz_uuid: string, ranked_scores: array, axis_x: float|null, axis_y: float|null, profile_label: string, profile_description: string}
     *
     * @throws ComputationInProgressException
     * @throws AlreadyCompletedException
     * @throws MatchingPersistenceException
     */
    public function computeMatching(int $quizResultId): array
    {
        if (! $this->resultRepository->acquireComputingLock($quizResultId)) {
            $current = $this->resultRepository->loadQuizResult($quizResultId);

            throw match ($current->status) {
                QuizResultStatus::Completed => new AlreadyCompletedException,
                // 'computing' (concurrence réelle) ou tout autre statut inattendu
                // à ce stade (ex. 'failed' appelé hors du flux /retry) : même
                // réponse temporaire, l'appelant est invité à réessayer.
                default => new ComputationInProgressException,
            };
        }

        $quizResult = $this->resultRepository->loadQuizResult($quizResultId);
        $answers = $this->answerRepository->loadAnswersForQuiz($quizResultId);
        $questionIds = $answers->pluck('question_id');
        $positionsByParty = $this->partyPositionRepository
            ->loadPositionsForQuestions($questionIds)
            ->groupBy('party_id');

        $rankedScores = Party::query()->get()->map(fn (Party $party) => [
            'party_id' => $party->id,
            'score' => $this->scoreCalculator->calculateScore($answers, $positionsByParty->get($party->id) ?? collect()),
        ])->all();

        // Tri décroissant, scores null en dernier. Note : structurellement, ce
        // cas mixte ne peut pas se produire (was_skipped est un attribut par
        // question, pas par parti, donc max_possible_distance est identique
        // pour les 6 partis) — soit tous les scores sont null, soit aucun ne
        // l'est. Le tri gère le cas mixte par robustesse, pas parce qu'il est
        // attendu (cf. spec technique, "point de vigilance documentaire").
        usort($rankedScores, function (array $a, array $b): int {
            if ($a['score'] === null && $b['score'] === null) {
                return 0;
            }

            return match (true) {
                $a['score'] === null => 1,
                $b['score'] === null => -1,
                default => $b['score'] <=> $a['score'],
            };
        });

        foreach ($rankedScores as $i => &$row) {
            $row['rank'] = $i + 1;
        }
        unset($row);

        $axes = $this->axisCalculator->calculate($answers);
        // UUID du quiz_result comme graine déterministe (cf.
        // ProfileLabelService::generate()) : stable pour ce quiz précis,
        // y compris en cas de recalcul via /retry.
        $profile = $this->profileLabelService->generate($answers, $quizResult->uuid);

        try {
            $this->resultRepository->persistResults(
                $quizResultId,
                $rankedScores,
                $axes['axis_x'],
                $axes['axis_y'],
                $profile['label'],
                $profile['description'],
            );
        } catch (Throwable $e) {
            report($e);
            $this->resultRepository->markFailed($quizResultId);

            throw new MatchingPersistenceException($e);
        }

        return [
            'quiz_uuid' => $quizResult->uuid,
            'ranked_scores' => $rankedScores,
            'axis_x' => $axes['axis_x'],
            'axis_y' => $axes['axis_y'],
            'profile_label' => $profile['label'],
            'profile_description' => $profile['description'],
        ];
    }
}
