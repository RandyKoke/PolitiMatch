<?php

namespace App\Services;

use App\Enums\AxisType;
use App\Models\Answer;
use App\Models\PartyPosition;
use Illuminate\Support\Collection;

class PoliticalAxisCalculator
{
    /**
     * Axes idéologiques (spec technique §2.2), indépendants l'un de l'autre :
     * chacun peut être null si toutes ses questions ont été passées.
     *
     * @param  Collection<int, Answer>  $answers
     * @return array{axis_x: float|null, axis_y: float|null}
     */
    public function calculate(Collection $answers): array
    {
        return [
            'axis_x' => $this->calculateAxis($answers, AxisType::Economique),
            'axis_y' => $this->calculateAxis($answers, AxisType::Societal),
        ];
    }

    private function calculateAxis(Collection $answers, AxisType $axisType): ?float
    {
        $numerator = 0;
        $denominator = 0;

        foreach ($answers as $answer) {
            if ($answer->was_skipped || $answer->question->axe_ideologique !== $axisType) {
                continue;
            }

            $weight = $answer->question->weight;
            $numerator += $answer->user_score * $weight;
            // Facteur 2 (valeur absolue max de user_score), et non 4 (distance
            // max entre deux réponses) : ici on normalise une valeur brute, pas
            // un écart. Utiliser 4 comprimerait le résultat dans [-0.5 ; 0.5]
            // au lieu de [-1.0 ; 1.0] (bug historique v2, corrigé en v3).
            $denominator += 2 * $weight;
        }

        if ($denominator === 0) {
            return null;
        }

        return round($numerator / $denominator, 2);
    }

    /**
     * Même formule, appliquée à `party_score` plutôt qu'à `user_score` — la
     * position idéologique d'un parti sur les deux mêmes axes que
     * l'utilisateur, pour le graphique 2D (frontend prompt 3, complément
     * demandé explicitement par l'utilisateur : jamais de coordonnée
     * inventée, uniquement les données déjà validées par l'expert politique
     * dans party_positions). Délibérément une méthode distincte plutôt
     * qu'une factorisation avec calculateAxis() : `party_positions` couvre
     * toutes les questions actives par construction (une ligne par
     * (parti, question), cf. contrainte unique en base), il n'y a donc pas
     * de notion de `was_skipped` à filtrer ici — les deux méthodes
     * n'opèrent pas exactement sur la même forme de données, une
     * abstraction commune ajouterait de l'indirection pour peu de gain.
     *
     * @param  Collection<int, PartyPosition>  $positions  toutes les positions d'UN SEUL parti, avec la relation `question` chargée
     * @return array{axis_x: float|null, axis_y: float|null}
     */
    public function calculateForParty(Collection $positions): array
    {
        return [
            'axis_x' => $this->calculatePartyAxis($positions, AxisType::Economique),
            'axis_y' => $this->calculatePartyAxis($positions, AxisType::Societal),
        ];
    }

    private function calculatePartyAxis(Collection $positions, AxisType $axisType): ?float
    {
        $numerator = 0;
        $denominator = 0;

        foreach ($positions as $position) {
            if ($position->question->axe_ideologique !== $axisType) {
                continue;
            }

            $weight = $position->question->weight;
            $numerator += $position->score * $weight;
            $denominator += 2 * $weight;
        }

        if ($denominator === 0) {
            return null;
        }

        return round($numerator / $denominator, 2);
    }
}
