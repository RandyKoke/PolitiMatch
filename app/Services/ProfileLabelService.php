<?php

namespace App\Services;

use App\Models\Answer;
use Illuminate\Support\Collection;

class ProfileLabelService
{
    // En dessous de ce nombre de thématiques avec un score exploitable, la
    // combinaison de règles n'a plus de sens (trop peu de signal) : on
    // renvoie directement le libellé "prudent" de repli plutôt que de
    // risquer un profil qui semblerait confiant sans l'être.
    private const MIN_THEMES_WITH_SCORE = 2;

    /**
     * @param  Collection<int, Answer>  $answers  chargées avec question.theme
     * @param  string  $variantSeed  graine déterministe pour le choix des
     *                               formulations dynamiques, en pratique l'UUID du
     *                               quiz_result appelant, pour qu'un même utilisateur revoie
     *                               toujours le même texte en rechargeant sa page de résultat (cf.
     *                               MatchingService::computeMatching()), sans avoir à stocker quelle
     *                               variante a été choisie. Un vrai hasard (mt_rand) aurait le même
     *                               effet visible pour l'utilisateur UNE FOIS le texte persisté en
     *                               base (jamais recalculé au chargement), mais casserait la
     *                               reproductibilité des tests et d'un recalcul via /retry — une
     *                               graine dérivée d'une donnée déjà stable est préférable ici.
     * @return array{label: string, description: string}
     */
    public function generate(Collection $answers, string $variantSeed): array
    {
        $themeScores = $this->computeThemeScores($answers);
        $missingThemes = collect($themeScores)->filter(fn (?float $score) => $score === null)->keys();
        $availableCount = count($themeScores) - $missingThemes->count();

        if ($availableCount < self::MIN_THEMES_WITH_SCORE) {
            $fallback = config('profile_labels.insufficient_data');

            return ['label' => $fallback['label'], 'description' => $fallback['description']];
        }

        $rule = $this->matchRule($themeScores);
        $description = $rule['description'];

        // Description personnalisée : complète le texte générique du
        // libellé par des phrases construites à partir des vrais scores par
        // thème, jamais
        // un texte identique d'un profil à l'autre à libellé égal, et
        // jamais la même structure de phrase répétée mécaniquement d'une
        // thématique à l'autre (cf. ProfileLabelServiceTest).
        $narrative = $this->buildNarrative($themeScores, $variantSeed);
        if ($narrative !== null) {
            $description .= ' '.$narrative;
        }

        if ($missingThemes->isNotEmpty()) {
            $description .= ' '.sprintf(
                config('profile_labels.low_confidence_suffix'),
                $missingThemes->implode(', '),
            );
        }

        return ['label' => $rule['label'], 'description' => $description];
    }

    /**
     * Construit 1 à plusieurs phrases décrivant concrètement les positions
     * de l'utilisateur, thème par thème, à partir de `config('profile_labels.
     * narrative')` (phrases complètes par thème + gabarits de liaison,
     * éditables sans toucher à ce fichier). Toujours fondé sur les scores
     * réels passés en paramètre : deux profils avec le même libellé mais des
     * scores différents produisent nécessairement des phrases différentes.
     *
     * @param  array<string, float|null>  $themeScores
     */
    private function buildNarrative(array $themeScores, string $variantSeed): ?string
    {
        $available = collect($themeScores)->filter(fn (?float $score) => $score !== null);

        if ($available->count() < self::MIN_THEMES_WITH_SCORE) {
            return null;
        }

        $config = config('profile_labels.narrative');
        $markedThreshold = $config['thresholds']['marked'];
        $moderateThreshold = $config['thresholds']['moderate'];
        $tieEpsilon = $config['thresholds']['tie_epsilon'];
        $groupTemplates = $config['group_templates'];

        $maxMagnitude = $available->map(fn (float $score) => abs($score))->max();

        // Aucune thématique n'atteint même le seuil "modéré" : plutôt que de
        // forcer un contraste entre une thématique "la plus marquée" et une
        // "la moins marquée" qui n'existe pas vraiment dans les réponses,
        // reconnaître honnêtement cet équilibre.
        if ($maxMagnitude < $moderateThreshold) {
            $variant = $this->pickVariant($groupTemplates['all_balanced'], $variantSeed, 'all_balanced');

            return sprintf($variant, $this->joinThemeList($available->keys()->all()));
        }

        // Quasi-égalité au sommet : toutes les thématiques à moins de
        // `tie_epsilon` du score maximal sont présentées ensemble plutôt que
        // d'en élire une arbitrairement "la plus marquée" (contient au moins
        // la thématique du score maximal lui-même).
        $leaders = $available->filter(fn (float $score) => $maxMagnitude - abs($score) <= $tieEpsilon);
        $others = $available->diffKeys($leaders);

        // Toute autre thématique encore "marquée" (pas seulement celle(s)
        // en tête) reste
        // explicitement citée, pas silencieusement absorbée dans le groupe
        // "modéré".
        $additionalMarked = $others->filter(fn (float $score) => abs($score) >= $markedThreshold)
            ->sortByDesc(fn (float $score) => abs($score));

        $sentences = [];

        if ($leaders->count() > 1) {
            $sentences[] = $this->pickVariant($groupTemplates['tie_intro'], $variantSeed, 'tie_intro');
        }

        // Chaque thématique individuellement citée (celle(s) en tête, puis
        // toute autre encore marquée) pioche sa PROPRE phrase complète dans
        // theme_phrases, jamais le même gabarit répété d'une thématique à
        // l'autre. Un connecteur (lui aussi varié) n'introduit que les thématiques
        // qui suivent la ou les premières déjà mentionnées.
        $individuallyMentioned = $leaders->merge($additionalMarked);
        $index = 0;
        foreach ($individuallyMentioned as $themeName => $score) {
            $sentence = $this->themeSentence($themeName, $score, $variantSeed);

            if ($index > 0 && ! $leaders->has($themeName)) {
                $connector = $this->pickVariant($groupTemplates['connector'], $variantSeed, "connector:{$themeName}");
                $sentence = $connector.' '.$sentence;
            }

            $sentences[] = $sentence;
            $index++;
        }

        $moderate = $others->filter(fn (float $score) => abs($score) >= $moderateThreshold && abs($score) < $markedThreshold);
        if ($moderate->isNotEmpty()) {
            $key = $moderate->count() === 1 ? 'moderate_singular' : 'moderate_plural';
            $variant = $this->pickVariant($groupTemplates[$key], $variantSeed, $key);
            $sentences[] = sprintf($variant, $this->joinThemeList($moderate->keys()->all()));
        }

        $neutral = $others->filter(fn (float $score) => abs($score) < $moderateThreshold);
        if ($neutral->isNotEmpty()) {
            $key = $neutral->count() === 1 ? 'neutral_singular' : 'neutral_plural';
            $variant = $this->pickVariant($groupTemplates[$key], $variantSeed, $key);
            $sentences[] = sprintf($variant, $this->joinThemeList($neutral->keys()->all()));
        }

        return implode(' ', $sentences);
    }

    /**
     * Une phrase complète et autonome (pas un fragment à insérer dans un
     * gabarit) décrivant la position réelle de l'utilisateur sur ce thème.
     */
    private function themeSentence(string $themeName, float $score, string $variantSeed): string
    {
        $moderateThreshold = config('profile_labels.narrative.thresholds.moderate');
        $direction = match (true) {
            $score >= $moderateThreshold => 'positive',
            $score <= -$moderateThreshold => 'negative',
            default => 'neutral',
        };

        $variants = config("profile_labels.narrative.theme_phrases.{$themeName}.{$direction}");

        return $this->pickVariant($variants, $variantSeed, "{$themeName}:{$direction}");
    }

    /**
     * Choix déterministe (jamais un vrai hasard, cf. generate()) d'une
     * variante parmi plusieurs — le même $variantSeed + le même $context
     * choisissent toujours la même variante, mais deux contextes différents
     * (deux thématiques, ou la même thématique dans deux quiz différents)
     * n'ont aucune raison de tomber sur le même index.
     *
     * @param  array<int, string>  $variants
     */
    private function pickVariant(array $variants, string $variantSeed, string $context): string
    {
        $index = crc32($variantSeed.'|'.$context) % count($variants);

        return $variants[$index];
    }

    /**
     * Comme joinList(), mais pour une liste de noms de thème insérée dans un
     * gabarit de liaison (group_templates) : chaque thème est d'abord
     * remplacé par sa forme avec article (config('profile_labels.
     * theme_with_article')), pour qu'un thème n'apparaisse jamais nu dans la
     * phrase générée ("sur l'Économie", jamais "sur Économie").
     *
     * @param  array<int, string>  $themeNames
     */
    private function joinThemeList(array $themeNames): string
    {
        $withArticle = collect($themeNames)
            ->map(fn (string $themeName) => config("profile_labels.theme_with_article.{$themeName}"))
            ->all();

        return $this->joinList($withArticle);
    }

    /**
     * "A" / "A et B" / "A, B et C" — jamais une simple virgule avant le
     * dernier élément (lisibilité en français).
     *
     * @param  array<int, string>  $items
     */
    private function joinList(array $items): string
    {
        if (count($items) === 1) {
            return $items[0];
        }

        $last = array_pop($items);

        return implode(', ', $items).' et '.$last;
    }

    /**
     * Score pondéré normalisé par thématique, même convention que
     * PoliticalAxisCalculator (Σ(user_score×weight) / Σ(2×weight), réponses
     * passées exclues) mais groupé par thème plutôt que par axe_ideologique —
     * les deux dimensions sont indépendantes et se recoupent partiellement :
     * la thématique "Environnement", par exemple, est très majoritairement
     * classée 'aucun' côté axe_ideologique (hors des deux axes du graphique)
     * mais garde son propre score ici, nécessaire pour un
     * libellé du type "Écologiste" — ce score par thème ne doit jamais être
     * confondu avec axis_x/axis_y ni recalculé à partir d'eux.
     * null si aucune réponse exploitable pour la thématique (jamais 0.0, qui
     * signifierait à tort une position neutre affirmée).
     *
     * @param  Collection<int, Answer>  $answers
     * @return array<string, float|null> score par nom de thème
     */
    public function computeThemeScores(Collection $answers): array
    {
        $byTheme = $answers
            ->filter(fn (Answer $answer) => ! $answer->was_skipped)
            ->groupBy(fn (Answer $answer) => $answer->question->theme->name);

        return collect(config('profile_labels.themes'))
            ->mapWithKeys(function (string $themeName) use ($byTheme) {
                $themeAnswers = $byTheme->get($themeName, collect());

                $numerator = $themeAnswers->sum(fn (Answer $a) => $a->user_score * $a->question->weight);
                $denominator = $themeAnswers->sum(fn (Answer $a) => 2 * $a->question->weight);

                return [$themeName => $denominator === 0 ? null : round($numerator / $denominator, 2)];
            })
            ->all();
    }

    /**
     * Évalue les règles déclaratives (config/profile_labels.php) dans
     * l'ordre : la première dont toutes les conditions sont satisfaites
     * l'emporte. Une thématique manquante fait automatiquement échouer
     * toute condition qui la référence (pas de cas particulier à gérer).
     *
     * @param  array<string, float|null>  $themeScores
     * @return array{label: string, description: string}
     */
    private function matchRule(array $themeScores): array
    {
        foreach (config('profile_labels.rules') as $rule) {
            if ($this->allConditionsMatch($rule['conditions'], $themeScores)) {
                return $rule;
            }
        }

        return config('profile_labels.fallback');
    }

    private function allConditionsMatch(array $conditions, array $themeScores): bool
    {
        foreach ($conditions as $condition) {
            $score = $themeScores[$condition['theme']] ?? null;

            if ($score === null || ! $this->conditionMatches($score, $condition)) {
                return false;
            }
        }

        return true;
    }

    private function conditionMatches(float $score, array $condition): bool
    {
        return match ($condition['operator']) {
            '>=' => $score >= $condition['value'],
            '<=' => $score <= $condition['value'],
            'between' => $score >= $condition['value'][0] && $score <= $condition['value'][1],
        };
    }
}
