<?php

namespace Tests\Feature\Party;

use App\Models\GuestSession;
use App\Models\Party;
use App\Models\QuizResult;
use App\Models\ResultPartyScore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_index_returns_only_active_parties_sorted_by_name(): void
    {
        Party::where('abbreviation', 'PTB')->update(['is_active' => false]);

        $response = $this->getJson('/api/parties');

        $response->assertStatus(200)->assertJsonCount(5, 'parties');
        $names = collect($response->json('parties'))->pluck('name');
        $this->assertFalse($names->contains('PTB'));
        // Ordre attendu selon le tri PostgreSQL (sensible à la locale, donc
        // "Écolo" se classe avec les E — pas après Z comme le ferait un tri
        // PHP naïf sur les octets UTF-8) : comparé à une valeur figée, pas à
        // sort() côté PHP, qui donnerait un ordre différent et invaliderait
        // ce test sans refléter un vrai bug applicatif.
        $this->assertSame(['DéFI', 'Écolo', 'Les Engagés', 'MR', 'PS'], $names->all());
    }

    public function test_index_response_has_the_expected_fields(): void
    {
        $response = $this->getJson('/api/parties');

        $response->assertStatus(200)->assertJsonStructure([
            'parties' => [['id', 'name', 'abbreviation', 'color_hex', 'logo_url', 'description', 'slogan', 'language_community']],
        ]);
    }

    public function test_show_returns_party_detail_with_notable_positions(): void
    {
        $ecolo = Party::where('abbreviation', 'ECOLO')->firstOrFail();

        $response = $this->getJson("/api/parties/{$ecolo->id}");

        $response->assertStatus(200)
            ->assertJsonPath('party.name', 'Écolo')
            ->assertJsonCount(3, 'notable_positions')
            ->assertJsonStructure([
                'notable_positions' => [['question_label', 'score', 'justification', 'source_reference']],
            ]);
    }

    /**
     * Diagnostic préalable à cette relance : PartyController::show() ne
     * transmettait ni language_community, ni ideological_x/y, ni (avant
     * l'ajout de la colonne) aucun slogan, alors que ces champs existent en
     * base et sont déjà exploités ailleurs (endpoint de liste, graphique de
     * positionnement). Verrouille la correction : les quatre champs sont
     * désormais bien présents dans la réponse, avec de vraies valeurs.
     */
    public function test_show_transmits_slogan_language_community_and_ideological_axes(): void
    {
        $ecolo = Party::where('abbreviation', 'ECOLO')->firstOrFail();

        $response = $this->getJson("/api/parties/{$ecolo->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['party' => ['slogan', 'language_community', 'ideological_x', 'ideological_y']])
            ->assertJsonPath('party.slogan', $ecolo->slogan)
            ->assertJsonPath('party.language_community', 'FR');
        $this->assertNotNull($response->json('party.slogan'));
        $this->assertNotNull($response->json('party.ideological_x'));
        $this->assertNotNull($response->json('party.ideological_y'));
    }

    public function test_show_returns_404_for_an_inactive_party(): void
    {
        $ptb = Party::where('abbreviation', 'PTB')->firstOrFail();
        $ptb->update(['is_active' => false]);

        $this->getJson("/api/parties/{$ptb->id}")->assertStatus(404);
    }

    public function test_show_returns_404_for_an_unknown_party(): void
    {
        $this->getJson('/api/parties/999999')->assertStatus(404);
    }

    /**
     * Un parti désactivé reste accessible depuis un ancien résultat qui le
     * référence réellement (ResultPartyScore),
     * via le paramètre ?quiz={uuid} déjà posé par ResultsView/CompareView
     * sur leurs liens vers une fiche parti.
     */
    public function test_show_allows_an_inactive_party_when_genuinely_referenced_by_the_given_quiz_history(): void
    {
        $ptb = Party::where('abbreviation', 'PTB')->firstOrFail();
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token, 'completed_at' => now()]);
        ResultPartyScore::create([
            'quiz_result_id' => $quizResult->id, 'party_id' => $ptb->id,
            'compatibility_score' => 42, 'rank' => 3, 'calculated_at' => now(),
        ]);
        $ptb->update(['is_active' => false]);

        $response = $this->getJson("/api/parties/{$ptb->id}?quiz={$quizResult->uuid}");

        $response->assertStatus(200)
            ->assertJsonPath('party.name', 'PTB')
            ->assertJsonPath('party.is_active', false);
    }

    public function test_show_still_returns_404_for_an_inactive_party_when_the_quiz_never_actually_scored_it(): void
    {
        $ptb = Party::where('abbreviation', 'PTB')->firstOrFail();
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        // Quiz réel, mais qui n'a jamais scoré ce parti précis (aucun
        // ResultPartyScore créé) : le paramètre ?quiz= à lui seul ne doit
        // jamais suffire, seul un lien de données réel doit débloquer l'accès.
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token, 'completed_at' => now()]);
        $ptb->update(['is_active' => false]);

        $this->getJson("/api/parties/{$ptb->id}?quiz={$quizResult->uuid}")->assertStatus(404);
    }

    public function test_show_returns_404_for_an_inactive_party_with_an_unknown_quiz_uuid(): void
    {
        $ptb = Party::where('abbreviation', 'PTB')->firstOrFail();
        $ptb->update(['is_active' => false]);

        $this->getJson("/api/parties/{$ptb->id}?quiz=00000000-0000-0000-0000-000000000000")->assertStatus(404);
    }

    /**
     * L'index public (grille "tous les partis actifs") n'est jamais concerné
     * par ce contournement, quel que soit ?quiz= : is_active reste le seul
     * critère pour la liste générale, cf. test_index_returns_only_active_parties_sorted_by_name.
     */
    public function test_active_parties_are_completely_unaffected_by_the_quiz_parameter(): void
    {
        $ecolo = Party::where('abbreviation', 'ECOLO')->firstOrFail();

        $this->getJson("/api/parties/{$ecolo->id}")->assertStatus(200);
        $this->getJson("/api/parties/{$ecolo->id}?quiz=00000000-0000-0000-0000-000000000000")->assertStatus(200);
    }
}
