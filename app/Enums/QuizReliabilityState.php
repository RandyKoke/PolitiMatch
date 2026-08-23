<?php

namespace App\Enums;

/**
 * État de fiabilité d'un QuizResult Completed, basé UNIQUEMENT sur le
 * nombre de réponses réellement données (was_skipped = false), distinct de
 * ProfileLabelService::MIN_THEMES_WITH_SCORE (diversité
 * thématique, mécanisme préexistant conservé tel quel : un profil peut
 * manquer de diversité thématique même avec de nombreuses réponses réelles,
 * c'est un problème différent). Cf. QuizReliabilityService pour le seuil.
 */
enum QuizReliabilityState: string
{
    case Empty = 'empty';
    case TooFew = 'too_few';
    case Partial = 'partial';
    case Full = 'full';

    /**
     * Empty/TooFew : aucun résultat exploitable ne doit être affiché/
     * partagé/comparé. Partial/Full : le résultat s'affiche normalement
     * (Partial ajoute seulement une mention de transparence non bloquante).
     */
    public function isBlocked(): bool
    {
        return $this === self::Empty || $this === self::TooFew;
    }
}
