<?php

namespace Tests\Feature\Quiz;

use App\Enums\QuizResultStatus;
use App\Exceptions\AlreadyCompletedException;
use App\Exceptions\ComputationInProgressException;
use App\Models\Answer;
use App\Models\GuestSession;
use App\Models\Party;
use App\Models\PartyPosition;
use App\Models\Question;
use App\Models\QuizResult;
use App\Models\Theme;
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuizResult(): QuizResult
    {
        $theme = Theme::create(['name' => 'Économie']);
        $question = Question::create(['theme_id' => $theme->id, 'label' => 'Q', 'weight' => 1, 'position_order' => 1]);
        $party = Party::create(['name' => 'PS', 'abbreviation' => 'PS', 'language_community' => 'FR']);
        PartyPosition::create([
            'party_id' => $party->id, 'question_id' => $question->id, 'score' => 1,
            'justification' => 'x', 'source_reference' => 'x',
        ]);

        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token]);
        Answer::create([
            'quiz_result_id' => $quizResult->id, 'question_id' => $question->id,
            'user_score' => 1, 'was_skipped' => false, 'answered_at' => now(),
        ]);

        return $quizResult;
    }

    /**
     * Simule la concurrence sans vrai parallélisme : un quiz déjà en
     * 'computing' (comme si un autre processus avait posé le verrou) doit
     * faire échouer un second appel avec le même code que produirait une
     * vraie course — c'est exactement le chemin de code qu'exercerait une
     * vraie concurrence (l'UPDATE ... WHERE status='pending' renvoie 0 ligne).
     */
    public function test_a_quiz_already_computing_rejects_a_concurrent_call(): void
    {
        $quizResult = $this->makeQuizResult();
        $quizResult->update(['status' => QuizResultStatus::Computing]);

        $this->expectException(ComputationInProgressException::class);
        app(MatchingService::class)->computeMatching($quizResult->id);
    }

    public function test_a_completed_quiz_rejects_a_new_computation(): void
    {
        $quizResult = $this->makeQuizResult();
        $quizResult->update(['status' => QuizResultStatus::Completed, 'completed_at' => now()]);

        $this->expectException(AlreadyCompletedException::class);
        app(MatchingService::class)->computeMatching($quizResult->id);
    }

    public function test_successful_computation_persists_results_and_marks_completed(): void
    {
        $quizResult = $this->makeQuizResult();

        $result = app(MatchingService::class)->computeMatching($quizResult->id);

        $this->assertSame($quizResult->uuid, $result['quiz_uuid']);
        $this->assertSame(QuizResultStatus::Completed, $quizResult->fresh()->status);
        $this->assertNotNull($quizResult->fresh()->completed_at);
        $this->assertDatabaseCount('result_party_scores', 1);
        // Une seule thématique répondue : sous le seuil minimal du
        // ProfileLabelService (cf. ProfileLabelServiceTest) — le profil
        // "prudent" de repli est attendu, jamais un libellé forcé.
        $this->assertSame('Profil politique à préciser', $result['profile_label']);
        $this->assertSame('Profil politique à préciser', $quizResult->fresh()->profile_label);
    }

    /**
     * ScoreCalculatorTest couvre déjà ce cas au niveau du service isolé
     * (ignoré, pas traité comme un désaccord total),
     * mais rien ne vérifiait jusqu'ici que le calcul de bout en bout
     * (MatchingService::computeMatching, tel qu'exécuté réellement via
     * /api/quiz/complete) aboutit sans exception quand un parti n'a
     * délibérément aucune position pour une des questions répondues. Deux
     * partis, deux questions : PS a une position sur les deux, MR seulement
     * sur la première — le calcul doit aboutir pour les deux partis, avec un
     * score partiel cohérent pour MR (pas null, puisqu'il lui reste une
     * question exploitable), jamais une exception ni un score faussé.
     */
    public function test_computation_succeeds_when_a_party_is_missing_a_position_for_one_answered_question(): void
    {
        $theme = Theme::create(['name' => 'Économie']);
        $q1 = Question::create(['theme_id' => $theme->id, 'label' => 'Q1', 'weight' => 1, 'position_order' => 1]);
        $q2 = Question::create(['theme_id' => $theme->id, 'label' => 'Q2', 'weight' => 1, 'position_order' => 2]);

        $ps = Party::create(['name' => 'PS', 'abbreviation' => 'PS', 'language_community' => 'FR']);
        $mr = Party::create(['name' => 'MR', 'abbreviation' => 'MR', 'language_community' => 'FR']);

        PartyPosition::create(['party_id' => $ps->id, 'question_id' => $q1->id, 'score' => 2, 'justification' => 'x', 'source_reference' => 'x']);
        PartyPosition::create(['party_id' => $ps->id, 'question_id' => $q2->id, 'score' => -2, 'justification' => 'x', 'source_reference' => 'x']);
        // MR : aucune position sur Q2, volontairement (donnée manquante simulée).
        PartyPosition::create(['party_id' => $mr->id, 'question_id' => $q1->id, 'score' => 2, 'justification' => 'x', 'source_reference' => 'x']);

        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token]);
        Answer::create(['quiz_result_id' => $quizResult->id, 'question_id' => $q1->id, 'user_score' => 2, 'was_skipped' => false, 'answered_at' => now()]);
        Answer::create(['quiz_result_id' => $quizResult->id, 'question_id' => $q2->id, 'user_score' => -2, 'was_skipped' => false, 'answered_at' => now()]);

        $result = app(MatchingService::class)->computeMatching($quizResult->id);

        $this->assertSame(QuizResultStatus::Completed, $quizResult->fresh()->status);
        $scoresByParty = collect($result['ranked_scores'])->keyBy('party_id');
        // PS : accord parfait sur les deux questions -> 100%.
        $this->assertSame(100.0, $scoresByParty[$ps->id]['score']);
        // MR : Q2 ignorée (pas de position), seule Q1 compte -> accord parfait
        // sur la seule question exploitable, donc 100% aussi (pas null, pas
        // faussé par l'absence de donnée sur Q2).
        $this->assertSame(100.0, $scoresByParty[$mr->id]['score']);
    }

    public function test_retry_resets_a_failed_quiz_and_recomputes(): void
    {
        $quizResult = $this->makeQuizResult();
        $quizResult->update(['status' => QuizResultStatus::Failed]);

        $response = $this->postJson("/api/quiz/{$quizResult->uuid}/retry", [
            'session_token' => $quizResult->session_token,
        ]);

        $response->assertStatus(200)->assertJsonStructure(['quiz_uuid', 'ranked_scores']);
        $this->assertSame(QuizResultStatus::Completed, $quizResult->fresh()->status);
    }

    public function test_retry_refuses_a_quiz_that_is_not_failed(): void
    {
        $quizResult = $this->makeQuizResult();
        // status = pending par défaut : jamais "en échec".

        $this->postJson("/api/quiz/{$quizResult->uuid}/retry", [
            'session_token' => $quizResult->session_token,
        ])->assertStatus(409);
    }

    /**
     * ResultRepository::persistResults() faisait un simple
     * `ResultPartyScore::create()` par parti, sans upsert. Un recalcul sur
     * un quiz_result_id ayant déjà des lignes result_party_scores
     * persistées (reprise ciblée d'un profil peu fiable, ou "Relancer le
     * calcul" après un premier succès suivi d'un échec) violait alors la
     * contrainte unique (quiz_result_id, party_id) dès la première ligne en
     * conflit, faisant échouer toute la transaction avec une erreur générique.
     * Reproduit ici au niveau du service, sans passer par les routes HTTP :
     * deux appels directs et successifs à computeMatching() sur le même
     * quiz_result_id (remis à 'pending' entre les deux, comme le fait
     * réellement QuizController::resumeSkipped()).
     */
    public function test_recomputing_after_a_successful_completion_upserts_scores_without_duplicate_key_violation(): void
    {
        $quizResult = $this->makeQuizResult();

        $first = app(MatchingService::class)->computeMatching($quizResult->id);
        $this->assertDatabaseCount('result_party_scores', 1);

        // Remise à 'pending' : exactement ce que fait QuizController::
        // resumeSkipped() pour un profil "too_few" avant de permettre un
        // second calcul.
        $quizResult->fresh()->update(['status' => QuizResultStatus::Pending]);

        $second = app(MatchingService::class)->computeMatching($quizResult->id);

        $this->assertSame(QuizResultStatus::Completed, $quizResult->fresh()->status);
        // Toujours une seule ligne par (quiz_result_id, party_id) : le second
        // calcul a REMPLACÉ la ligne existante, jamais ajouté une seconde
        // ligne en doublon ni laissé cohabiter une ancienne valeur.
        $this->assertDatabaseCount('result_party_scores', 1);
        $this->assertSame($first['ranked_scores'][0]['party_id'], $second['ranked_scores'][0]['party_id']);
    }

    /**
     * "Relancer le calcul" emprunte le même chemin de
     * code (QuizController::retry -> MatchingService::computeMatching) et
     * échouait donc de la même façon, de manière répétée, dès qu'un premier
     * calcul avait déjà réussi avant un échec ultérieur (ex. après une
     * reprise ciblée suivie d'un incident quelconque). Simule ce scénario
     * réel : un premier calcul réussit (lignes persistées), le statut est
     * ensuite forcé à 'failed' (comme le ferait CleanupStaleComputingQuizzes
     * ou tout échec applicatif après un succès précédent), puis /retry doit
     * réussir sans jamais buter sur les anciennes lignes.
     */
    public function test_retry_succeeds_even_when_prior_result_party_scores_already_exist(): void
    {
        $quizResult = $this->makeQuizResult();
        app(MatchingService::class)->computeMatching($quizResult->id);
        $this->assertDatabaseCount('result_party_scores', 1);

        $quizResult->fresh()->update(['status' => QuizResultStatus::Failed]);

        $response = $this->postJson("/api/quiz/{$quizResult->uuid}/retry", [
            'session_token' => $quizResult->session_token,
        ]);

        $response->assertStatus(200)->assertJsonStructure(['quiz_uuid', 'ranked_scores']);
        $this->assertSame(QuizResultStatus::Completed, $quizResult->fresh()->status);
        $this->assertDatabaseCount('result_party_scores', 1);
    }
}
