<?php

namespace App\Enums;

// Nom de classe distinct du nom de la colonne DB sous-jacente, qui
// s'appelle `axe_ideologique`.
enum AxisType: string
{
    case Economique = 'economique';
    case Societal = 'societal';
    // Question idéologiquement "impure" (écologie, défense, laïcité,
    // institutions) : exclue du calcul des axes, mais toujours utilisée
    // normalement pour le matching (ScoreCalculator).
    case Aucun = 'aucun';
}
