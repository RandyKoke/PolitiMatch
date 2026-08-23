<?php

namespace Tests\Feature\Seeders;

use App\Models\Party;
use App\Models\PartyPosition;
use App\Models\Question;
use App\Models\Theme;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpertContentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_loads_the_expected_volume_of_content(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, Theme::count());
        $this->assertSame(6, Party::count());
        $this->assertSame(30, Question::count());
        $this->assertSame(180, PartyPosition::count());
    }

    public function test_seeder_is_idempotent_when_run_twice(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, Theme::count());
        $this->assertSame(6, Party::count());
        $this->assertSame(30, Question::count());
        $this->assertSame(180, PartyPosition::count());
    }

    /**
     * Vérifie que le contenu transcrit correspond exactement au document
     * expert sur un cas connu (Q7, sortie du nucléaire).
     */
    public function test_seeded_content_matches_the_expert_document_on_a_known_example(): void
    {
        $this->seed(DatabaseSeeder::class);

        $question = Question::where('label', 'like', 'La Belgique devrait sortir définitivement%')->firstOrFail();
        $this->assertSame(3, $question->weight);
        // 'aucun' (hors axes) : le nucléaire est une question
        // énergétique/technologique, ni économique ni sociétale au sens du
        // graphique 2D.
        $this->assertSame('aucun', $question->axe_ideologique->value);

        $ecolo = Party::where('abbreviation', 'ECOLO')->firstOrFail();
        $mr = Party::where('abbreviation', 'MR')->firstOrFail();

        $ecoloPosition = PartyPosition::where('question_id', $question->id)->where('party_id', $ecolo->id)->firstOrFail();
        $mrPosition = PartyPosition::where('question_id', $question->id)->where('party_id', $mr->id)->firstOrFail();

        $this->assertSame(2, $ecoloPosition->score);
        $this->assertSame(-2, $mrPosition->score);
        $this->assertTrue($ecoloPosition->validated_by_expert);
    }

    /**
     * Q26 a été reformulée et sa grille de scores corrigée après validation
     * explicite de l'expert politique. Ce test vérifie que la grille validée
     * est bien celle en base, et empêche une régression vers l'ancienne
     * formulation ou les anciens scores.
     */
    public function test_q26_has_the_reformulated_wording_and_expert_validated_scores(): void
    {
        $this->seed(DatabaseSeeder::class);

        $question = Question::where('label', 'Les droits des personnes LGBTQIA+ doivent encore être renforcés par de nouvelles mesures législatives et politiques.')->firstOrFail();

        $this->assertSame(1, $question->weight);
        $this->assertSame('societal', $question->axe_ideologique->value);
        $this->assertSame('Société', $question->theme->name);

        $expectedScores = [
            'PS' => 2,
            'ECOLO' => 2,
            'PTB' => 2,
            'ENGAGES' => 2,
            'DEFI' => 1,
            'MR' => -1,
        ];

        foreach ($expectedScores as $abbreviation => $expectedScore) {
            $party = Party::where('abbreviation', $abbreviation)->firstOrFail();
            $position = PartyPosition::where('question_id', $question->id)->where('party_id', $party->id)->firstOrFail();

            $this->assertSame($expectedScore, $position->score, "Score inattendu pour {$abbreviation} sur Q26.");
        }
    }

    /**
     * Les 30 explications pédagogiques ("Pourquoi cette question ?")
     * rédigées par l'expert politique, vérifiées indépendamment
     * (correspondance exacte des libellés confirmée avant intégration) puis
     * intégrées dans expert_content.php. Ce test verrouille le fait que le
     * seeding produit
     * bien un contenu non vide pour les 30 questions actives — jamais du
     * texte inventé (le champ reste `?? null` dans QuestionSeeder pour
     * toute question qui n'en aurait pas), et jamais un doublon de contenu
     * générique répété (chaque explication doit être distincte des 29
     * autres, signe qu'il s'agit bien de 30 textes réellement rédigés).
     */
    public function test_all_active_questions_have_a_non_empty_distinct_explanation_after_seeding(): void
    {
        $this->seed(DatabaseSeeder::class);

        $questions = Question::where('is_active', true)->get(['id', 'explanation']);

        $this->assertSame(30, $questions->count());

        foreach ($questions as $question) {
            $this->assertNotNull($question->explanation, "explanation NULL pour la question #{$question->id}.");
            $this->assertNotSame('', trim($question->explanation), "explanation vide pour la question #{$question->id}.");
        }

        $this->assertSame(30, $questions->pluck('explanation')->unique()->count());
    }

    /**
     * Module Fiches Partis (relance) : les 6 partis actifs ont reçu une
     * description étoffée (560 à 670 caractères, cf. fichier fourni par
     * l'expert politique) et un slogan officiel de campagne 2024, jamais
     * fournis auparavant pour le slogan (colonne absente du schéma avant
     * cette relance). Verrouille la non-régression du contenu comme pour
     * les explications pédagogiques ci-dessus : jamais vide, jamais un
     * doublon générique répété d'un parti à l'autre.
     */
    public function test_all_active_parties_have_a_non_empty_distinct_slogan_and_a_substantial_description(): void
    {
        $this->seed(DatabaseSeeder::class);

        $parties = Party::where('is_active', true)->get(['id', 'name', 'slogan', 'description']);

        $this->assertSame(6, $parties->count());

        foreach ($parties as $party) {
            $this->assertNotNull($party->slogan, "slogan NULL pour {$party->name}.");
            $this->assertNotSame('', trim($party->slogan), "slogan vide pour {$party->name}.");
            $this->assertGreaterThanOrEqual(500, mb_strlen($party->description), "description trop courte pour {$party->name}.");
        }

        $this->assertSame(6, $parties->pluck('slogan')->unique()->count());
        $this->assertSame(6, $parties->pluck('description')->unique()->count());
    }
}
