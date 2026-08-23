<?php

namespace Database\Seeders;

use App\Models\Party;
use App\Models\PartyPosition;
use App\Models\Question;
use Illuminate\Database\Seeder;

class PartyPositionSeeder extends Seeder
{
    /**
     * updateOrCreate sur (party_id, question_id) : correspond exactement à
     * la contrainte unique en base — ré-exécutable sans doublon, garantie
     * au niveau applicatif ET base de données.
     */
    public function run(): void
    {
        $data = require database_path('seeders/data/expert_content.php');

        foreach ($data['questions'] as $question) {
            $questionModel = Question::where('label', $question['label'])->firstOrFail();

            foreach ($question['positions'] as $partyAbbreviation => [$score, $justification, $source]) {
                $party = Party::where('abbreviation', $partyAbbreviation)->firstOrFail();

                PartyPosition::updateOrCreate(
                    ['party_id' => $party->id, 'question_id' => $questionModel->id],
                    [
                        'score' => $score,
                        'justification' => $justification,
                        'source_reference' => $source,
                        'validated_by_expert' => true,
                    ],
                );
            }
        }
    }
}
