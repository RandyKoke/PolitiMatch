<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Theme;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    /**
     * updateOrCreate sur position_order (clé naturelle stable) plutôt que
     * sur label : le libellé d'une question peut être révisé après coup
     * (ex. Q26, reformulée le 2026-08-09 suite à un retour de l'expert
     * politique) - indexer sur le label casserait alors l'idempotence en
     * créant une ligne en doublon au lieu de mettre à jour la question
     * existante. position_order suit l'ordre fixe du questionnaire tel que
     * soumis à l'expert (Q1 à Q30) et ne change pas lors d'une simple
     * reformulation.
     *
     * explanation : contenu pédagogique du bouton "Pourquoi cette question ?",
     * rédigé séparément par l'expert politique et vérifié indépendamment
     * (correspondance exacte des libellés confirmée avant intégration),
     * distinct de la justification par parti qui, elle, va dans
     * party_positions. `?? null` conservé pour
     * ne jamais planter si une question venait à ne pas encore avoir ce
     * contenu (aucun champ comblé arbitrairement, cohérent avec la consigne
     * initiale).
     */
    public function run(): void
    {
        $data = require database_path('seeders/data/expert_content.php');

        foreach ($data['questions'] as $index => $question) {
            $theme = Theme::where('name', $question['theme'])->firstOrFail();

            Question::updateOrCreate(
                ['position_order' => $index + 1],
                [
                    'theme_id' => $theme->id,
                    'label' => $question['label'],
                    'explanation' => $question['explanation'] ?? null,
                    'weight' => $question['weight'],
                    'axe_ideologique' => $question['axe_ideologique'],
                    'is_active' => true,
                ],
            );
        }
    }
}
