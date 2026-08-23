<?php

namespace Tests\Feature\Party;

use App\Console\Commands\CalculatePartyIdeologicalAxes;
use App\Models\Party;
use App\Models\PartyPosition;
use App\Models\Question;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PartyIdeologicalAxesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // DatabaseSeeder appelle déjà `party:calculate-axes` après
        // PartyPositionSeeder : ce seed vérifie donc le pipeline complet,
        // pas seulement la commande isolée.
        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeding_populates_ideological_axes_for_every_active_party(): void
    {
        $parties = Party::where('is_active', true)->get();

        $this->assertGreaterThan(0, $parties->count());
        foreach ($parties as $party) {
            $this->assertNotNull($party->ideological_x, "{$party->abbreviation}: ideological_x ne devrait pas être null");
            $this->assertNotNull($party->ideological_y, "{$party->abbreviation}: ideological_y ne devrait pas être null");
        }
    }

    /**
     * Valeurs vérifiées à la main à partir des données réelles de l'expert
     * (database/seeders/data/expert_content.php) : Σ(party_score × weight) /
     * Σ(2 × weight), filtré par `axe_ideologique`, chaque question classée
     * une par une sur la seule base de sa pureté idéologique (cf.
     * PolitiMatch-Tableau-Axes-Ideologiques.pdf).
     */
    public function test_matches_hand_calculated_values_from_real_expert_data(): void
    {
        $mr = Party::where('abbreviation', 'MR')->firstOrFail();
        $ptb = Party::where('abbreviation', 'PTB')->firstOrFail();
        $ecolo = Party::where('abbreviation', 'ECOLO')->firstOrFail();
        $ps = Party::where('abbreviation', 'PS')->firstOrFail();

        $this->assertEquals(-0.5, (float) $mr->ideological_y);
        $this->assertEquals(0.43, (float) $ptb->ideological_x);
        $this->assertEquals(0.73, (float) $ecolo->ideological_y);
        $this->assertEquals(0.42, (float) $ps->ideological_x);
    }

    /**
     * Sous le libellé du graphique ("Libéral ← → Interventionniste", négatif
     * = libéral, positif = interventionniste), le PTB, le plus
     * interventionniste des six partis, doit se situer nettement du côté
     * positif.
     */
    public function test_ptb_is_clearly_on_the_interventionist_side_of_the_economic_axis(): void
    {
        $ptb = Party::where('abbreviation', 'PTB')->firstOrFail();

        $this->assertGreaterThan(0.3, (float) $ptb->ideological_x);
    }

    /**
     * Sur l'axe sociétal ("Conservateur ← → Progressiste"), MR et Écolo
     * doivent être dans l'ordre attendu : MR nettement négatif
     * (conservateur), Écolo nettement positif (progressiste).
     */
    public function test_mr_and_ecolo_are_correctly_ordered_on_the_societal_axis(): void
    {
        $mr = Party::where('abbreviation', 'MR')->firstOrFail();
        $ecolo = Party::where('abbreviation', 'ECOLO')->firstOrFail();

        $this->assertLessThan(0, (float) $mr->ideological_y);
        $this->assertGreaterThan(0, (float) $ecolo->ideological_y);
        $this->assertLessThan((float) $ecolo->ideological_y, (float) $mr->ideological_y);
    }

    public function test_get_parties_exposes_ideological_x_and_y(): void
    {
        $response = $this->getJson('/api/parties');

        $response->assertStatus(200)->assertJsonStructure([
            'parties' => [['id', 'name', 'ideological_x', 'ideological_y']],
        ]);
        $party = collect($response->json('parties'))->firstWhere('abbreviation', 'MR');
        $this->assertNotNull($party['ideological_x']);
        $this->assertNotNull($party['ideological_y']);
    }

    /**
     * La commande est réexécutable (ex. après une mise à jour du contenu
     * politique par l'expert, cf. cahier des charges §6.4) : un second
     * appel doit recalculer, pas juste laisser les anciennes valeurs.
     */
    public function test_command_is_idempotent_and_recomputes_on_a_second_run(): void
    {
        $party = Party::create(['name' => 'Parti Recalc', 'abbreviation' => 'PR', 'language_community' => 'FR']);
        $question = Question::where('axe_ideologique', 'economique')->firstOrFail();
        PartyPosition::create([
            'party_id' => $party->id, 'question_id' => $question->id,
            'score' => 2, 'justification' => 'x', 'source_reference' => 'x',
        ]);

        Artisan::call(CalculatePartyIdeologicalAxes::class);
        $party->refresh();
        $firstRun = $party->ideological_x;

        PartyPosition::where('party_id', $party->id)->update(['score' => -2]);
        Artisan::call(CalculatePartyIdeologicalAxes::class);
        $party->refresh();
        $secondRun = $party->ideological_x;

        $this->assertNotEquals((float) $firstRun, (float) $secondRun);
    }

    /**
     * Garde-fou explicite pour la classification elle-même (pas seulement
     * le calcul) : les comptes doivent rester 14/8/8, exactement la
     * répartition validée par l'expert (PolitiMatch-Tableau-Axes-
     * Ideologiques.pdf) — une régression ici (ex. un import qui perd la
     * classification d'une question) serait silencieuse sans ce test.
     */
    public function test_question_classification_matches_the_expert_validated_counts(): void
    {
        $this->assertSame(14, Question::where('axe_ideologique', 'economique')->count());
        $this->assertSame(8, Question::where('axe_ideologique', 'societal')->count());
        $this->assertSame(8, Question::where('axe_ideologique', 'aucun')->count());
    }
}
