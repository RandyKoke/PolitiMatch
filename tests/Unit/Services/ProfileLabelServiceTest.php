<?php

namespace Tests\Unit\Services;

use App\Models\Answer;
use App\Models\GuestSession;
use App\Models\Question;
use App\Models\QuizResult;
use App\Models\Theme;
use App\Services\ProfileLabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProfileLabelServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProfileLabelService $service;

    /** @var array<string, Question> une question par thème, weight=1 */
    private array $questionByTheme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProfileLabelService;

        $this->questionByTheme = collect(config('profile_labels.themes'))
            ->mapWithKeys(function (string $themeName) {
                $theme = Theme::create(['name' => $themeName]);
                $question = Question::create([
                    'theme_id' => $theme->id, 'label' => "Q-{$themeName}",
                    'weight' => 1, 'position_order' => 1,
                ]);

                return [$themeName => $question];
            })
            ->all();
    }

    /**
     * Une seule question par thème (weight=1) : répondre X donne un score de
     * thème = X/2 (numérateur X×1, dénominateur 2×1). X=2 → 1.0, X=-2 → -1.0,
     * X=0 → 0.0. Simplifie le calibrage des seuils dans chaque test.
     *
     * @param  array<string, int>  $scoreByTheme  seuls les thèmes listés sont répondus
     */
    private function answersFor(array $scoreByTheme): Collection
    {
        $guestSession = GuestSession::create(['expires_at' => now()->addDay()]);
        $quizResult = QuizResult::create(['session_token' => $guestSession->session_token]);

        foreach ($scoreByTheme as $themeName => $score) {
            Answer::create([
                'quiz_result_id' => $quizResult->id,
                'question_id' => $this->questionByTheme[$themeName]->id,
                'user_score' => $score,
                'was_skipped' => false,
                'answered_at' => now(),
            ]);
        }

        return Answer::with('question.theme')->where('quiz_result_id', $quizResult->id)->get();
    }

    /**
     * Vrai qu'au moins une des variantes connues (une par formulation
     * possible pour ce thème/cette direction/ce gabarit) apparaît dans le
     * texte : la variante exacte choisie dépend de la graine (rotation
     * déterministe), donc un test ne peut pas présumer laquelle
     * sans reproduire le calcul de ProfileLabelService::pickVariant() lui-même.
     *
     * @param  array<int, string>  $possibleSubstrings
     */
    private function assertContainsOneOf(array $possibleSubstrings, string $haystack): void
    {
        $matched = collect($possibleSubstrings)->contains(fn (string $needle) => str_contains($haystack, $needle));
        $this->assertTrue($matched, "Aucune des variantes attendues n'a été trouvée dans : {$haystack}");
    }

    public function test_theme_scores_are_null_for_themes_with_no_answered_question(): void
    {
        $answers = $this->answersFor(['Économie' => 2]);

        $scores = $this->service->computeThemeScores($answers);

        $this->assertSame(1.0, $scores['Économie']);
        $this->assertNull($scores['Environnement']);
        $this->assertNull($scores['Social']);
        $this->assertNull($scores['Immigration']);
        $this->assertNull($scores['Société']);
    }

    /**
     * Persona "manuel" construit pour cibler précisément ce libellé (cf.
     * cahier des charges §10.3, exemple donné dans le prompt) : Économie et
     * Social nettement positifs, Société neutre. Environnement/Immigration
     * volontairement non répondus — leur absence (score null) fait
     * automatiquement échouer les règles "Écologiste" évaluées avant, sans
     * cas particulier à gérer dans le code.
     */
    public function test_protection_sociale_avant_tout_label_for_a_matching_persona(): void
    {
        $answers = $this->answersFor(['Économie' => 2, 'Social' => 2, 'Société' => 0]);

        $profile = $this->service->generate($answers, 'seed-a');

        $this->assertSame('Protection sociale avant tout', $profile['label']);
    }

    public function test_ecologiste_et_solidaire_label_for_a_matching_persona(): void
    {
        $answers = $this->answersFor(['Environnement' => 2, 'Social' => 2]);

        $profile = $this->service->generate($answers, 'seed-b');

        $this->assertSame('Écologiste et solidaire', $profile['label']);
    }

    /**
     * Immigration volontairement non répondue : "Liberté individuelle,
     * contrôle assumé" (qui exige Immigration <= -0.2) est évalué en premier
     * dans la config mais échoue faute de score, laissant "Liberté
     * économique avant tout" l'emporter.
     */
    public function test_liberte_economique_avant_tout_label_for_a_matching_persona(): void
    {
        $answers = $this->answersFor(['Économie' => -2, 'Société' => 0]);

        $profile = $this->service->generate($answers, 'seed-c');

        $this->assertSame('Liberté économique avant tout', $profile['label']);
    }

    /**
     * Persona volontairement contradictoire (interventionniste sur
     * l'économie mais restrictif sur l'immigration) : ne doit correspondre à
     * aucune règle spécifique, le libellé de repli neutre est le résultat
     * attendu, pas une étiquette forcée ou caricaturale.
     */
    public function test_mixed_signal_persona_falls_back_to_the_neutral_label_instead_of_a_forced_one(): void
    {
        $answers = $this->answersFor(['Économie' => 2, 'Immigration' => -2]);

        $profile = $this->service->generate($answers, 'seed-d');

        $this->assertSame(config('profile_labels.fallback.label'), $profile['label']);
    }

    public function test_insufficient_data_label_when_fewer_than_two_themes_have_a_score(): void
    {
        $answers = $this->answersFor(['Économie' => 2]);

        $profile = $this->service->generate($answers, 'seed-e');

        $this->assertSame(config('profile_labels.insufficient_data.label'), $profile['label']);
    }

    public function test_description_flags_missing_themes_when_confidence_is_low(): void
    {
        $answers = $this->answersFor(['Économie' => 2, 'Social' => 2, 'Société' => 0]);

        $profile = $this->service->generate($answers, 'seed-f');

        $this->assertStringContainsString('Environnement, Immigration', $profile['description']);
    }

    public function test_description_does_not_flag_missing_themes_when_all_are_answered(): void
    {
        $answers = $this->answersFor([
            'Économie' => 2, 'Environnement' => 0, 'Social' => 2, 'Immigration' => 0, 'Société' => 0,
        ]);

        $profile = $this->service->generate($answers, 'seed-g');

        $this->assertStringNotContainsString('interpréter avec prudence', $profile['description']);
    }

    /**
     * Garde-fou de neutralité : aucun libellé ni description configuré ne
     * doit contenir de terme péjoratif ou
     * caricatural, même si quelqu'un ajoute une règle ou une variante plus
     * tard sans repasser par cette vérification. Couvre les libellés
     * statiques ET toutes les variantes de la description personnalisée
     * (`theme_phrases`, `group_templates`) — le garde-fou s'applique au
     * texte généré dynamiquement, pas seulement au texte fixe.
     */
    public function test_no_configured_text_contains_a_pejorative_term(): void
    {
        $forbidden = ['extrême', 'extremiste', 'radical', 'dangereux', 'sectaire'];

        foreach ($this->allConfiguredTexts() as $text) {
            foreach ($forbidden as $word) {
                $this->assertStringNotContainsStringIgnoringCase($word, $text);
            }
        }
    }

    /**
     * Aucun texte configuré (statique ou variante dynamique) ne doit
     * s'adresser au
     * lecteur au vouvoiement, cible principale 16-25 ans oblige. `\b` évite
     * les faux positifs sur des mots qui contiendraient la sous-chaîne sans
     * être le pronom/possessif lui-même (aucun cas réel ici, mais plus sûr).
     */
    public function test_all_configured_text_uses_tutoiement_not_vouvoiement(): void
    {
        foreach ($this->allConfiguredTexts() as $text) {
            $this->assertDoesNotMatchRegularExpression('/\b(vous|votre|vos)\b/i', $text, "Vouvoiement trouvé dans : {$text}");
        }
    }

    /**
     * @return Collection<int, string>
     */
    private function allConfiguredTexts(): Collection
    {
        $themePhraseTexts = collect(config('profile_labels.narrative.theme_phrases'))
            ->flatMap(fn (array $directions) => collect($directions)->flatMap(fn (array $variants) => $variants));

        $groupTemplateTexts = collect(config('profile_labels.narrative.group_templates'))
            ->flatMap(fn (array $variants) => $variants);

        return collect(config('profile_labels.rules'))
            ->push(config('profile_labels.fallback'))
            ->push(config('profile_labels.insufficient_data'))
            ->flatMap(fn (array $entry) => [$entry['label'], $entry['description']])
            ->push(config('profile_labels.low_confidence_suffix'))
            ->merge($themePhraseTexts)
            ->merge($groupTemplateTexts);
    }

    /**
     * Deux personas qui tombent sur le MÊME libellé ("Au cas par cas") mais
     * dont les scores par thème diffèrent réellement (Social nettement
     * positif pour l'un, nettement négatif pour l'autre) doivent recevoir
     * des descriptions différentes,
     * jamais un texte générique recopié à l'identique.
     */
    public function test_same_label_but_different_theme_scores_produce_different_descriptions(): void
    {
        // Économie/Société neutres dans les deux cas (→ "Au cas par cas" dans
        // les deux cas), seul le Social diffère nettement (+1.0 vs -1.0) —
        // aucune autre règle placée avant "Au cas par cas" dans la config ne
        // peut s'y substituer (toutes exigent Économie/Société/Environnement
        // hors de leur zone neutre ici).
        $centriste = $this->answersFor(['Économie' => 0, 'Société' => 0, 'Social' => 2]);
        $centristeAutre = $this->answersFor(['Économie' => 0, 'Société' => 0, 'Social' => -2]);

        $profileA = $this->service->generate($centriste, 'même-graine');
        $profileB = $this->service->generate($centristeAutre, 'même-graine');

        $this->assertSame('Au cas par cas', $profileA['label']);
        $this->assertSame('Au cas par cas', $profileB['label']);
        $this->assertNotSame($profileA['description'], $profileB['description']);
    }

    /**
     * La thématique la plus marquée (ici Environnement, score 1.0) doit être
     * décrite avec sa vraie position (priorité à la transition écologique),
     * pas un renvoi abstrait du type "tu as une position marquée". Les 3
     * variantes possibles pour Environnement/positive décrivent toutes la
     * même réalité factuelle avec des mots différents, l'une d'elles (par
     * design, "parfois elle n'est même pas nommée explicitement") ne cite
     * même pas le mot "Environnement". Le test vérifie donc le contenu réel
     * plutôt que la présence littérale du nom
     * du thème, qui n'est délibérément pas garantie sur chaque variante.
     */
    public function test_description_names_the_most_marked_theme_and_its_actual_position(): void
    {
        $answers = $this->answersFor(['Économie' => 0, 'Environnement' => 2, 'Société' => 0]);

        $profile = $this->service->generate($answers, 'seed-h');

        $this->assertContainsOneOf(['transition écologique', 'climat'], $profile['description']);
    }

    /**
     * Aucune thématique ne dépasse le seuil "modéré" (tous les scores sont
     * proches de zéro) : la description doit reconnaître honnêtement cet
     * équilibre plutôt que de forcer un contraste artificiel entre une
     * thématique "la plus marquée" qui ne l'est pas vraiment et une autre.
     */
    public function test_all_neutral_scores_produce_an_honest_balanced_sentence_not_a_forced_contrast(): void
    {
        $answers = $this->answersFor(['Économie' => 0, 'Société' => 0, 'Environnement' => 0, 'Social' => 0]);

        $profile = $this->service->generate($answers, 'seed-i');

        $this->assertContainsOneOf(
            ['globalement équilibrées', 'ne ressort vraiment', 'restent équilibrées sur tous les grands thèmes'],
            $profile['description'],
        );
    }

    /**
     * Quasi-égalité entre les deux thématiques les plus marquées (score
     * 0.5 pour Environnement et Économie, exactement à égalité) : les deux
     * doivent être citées ensemble comme "leaders", pas une choisie
     * arbitrairement comme "la plus marquée" au détriment de l'autre.
     */
    public function test_near_tied_top_themes_are_presented_together(): void
    {
        $answers = $this->answersFor(['Environnement' => 1, 'Économie' => 1, 'Société' => 0]);

        $profile = $this->service->generate($answers, 'seed-j');

        // Contenu réel des deux thématiques à égalité (pas forcément leur
        // nom littéral — cf. test précédent — mais le sujet qu'elles
        // recouvrent).
        $this->assertContainsOneOf(['transition écologique', 'climat'], $profile['description']);
        $this->assertContainsOneOf(["rôle actif de l'État", 'grandes fortunes', 'Économie'], $profile['description']);
        $this->assertContainsOneOf(
            ['intensité comparable', 'se démarquent à égalité', 'même intensité'],
            $profile['description'],
        );
    }

    /**
     * Rotation déterministe : la MÊME graine (en pratique l'UUID du
     * quiz_result) doit toujours produire EXACTEMENT le même texte, un
     * utilisateur qui recharge sa page de résultat ne doit jamais voir une
     * formulation différente d'un chargement à l'autre.
     */
    public function test_generate_is_deterministic_for_the_same_seed(): void
    {
        $answers = $this->answersFor(['Économie' => 2, 'Environnement' => 2, 'Social' => 0]);

        $first = $this->service->generate($answers, 'toujours-la-même-graine');
        $second = $this->service->generate($answers, 'toujours-la-même-graine');

        $this->assertSame($first['description'], $second['description']);
    }

    /**
     * À l'inverse, des graines différentes doivent pouvoir sélectionner des
     * formulations différentes pour un même profil — sinon la "rotation"
     * n'en serait pas une. Testé sur un échantillon de graines plutôt que
     * sur un couple précis : la sélection dépend d'un hachage (crc32), pas
     * garanti de changer entre deux graines consécutives prises au hasard.
     */
    public function test_different_seeds_can_select_different_phrasing_variants(): void
    {
        $answers = $this->answersFor(['Économie' => 2, 'Société' => 0]);

        $descriptions = collect(range(1, 20))
            ->map(fn (int $i) => $this->service->generate($answers, "graine-{$i}")['description'])
            ->unique();

        $this->assertGreaterThan(1, $descriptions->count());
    }

    /**
     * Le gabarit répété "Ta position est également marquée sur X : ..." (un
     * patron unique réutilisé pour chaque thématique citée, source d'un
     * effet de liste mécanique) ne doit jamais apparaître.
     */
    public function test_the_old_mechanical_repeated_template_is_gone(): void
    {
        $answers = $this->answersFor(['Économie' => 2, 'Environnement' => 2, 'Social' => 2, 'Immigration' => 2]);

        $profile = $this->service->generate($answers, 'seed-k');

        $this->assertStringNotContainsString('Ta position est également marquée sur', $profile['description']);
    }
}
