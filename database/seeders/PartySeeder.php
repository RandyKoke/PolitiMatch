<?php

namespace Database\Seeders;

use App\Models\Party;
use Illuminate\Database\Seeder;

class PartySeeder extends Seeder
{
    /**
     * updateOrCreate sur le nom (déjà unique en base) : ré-exécutable sans
     * doublon.
     */
    public function run(): void
    {
        $data = require database_path('seeders/data/expert_content.php');

        foreach ($data['parties'] as $party) {
            Party::updateOrCreate(
                ['name' => $party['name']],
                [
                    'abbreviation' => $party['abbreviation'],
                    'color_hex' => $party['color_hex'],
                    'description' => $party['description'],
                    'slogan' => $party['slogan'] ?? null,
                    'language_community' => $party['language_community'],
                    'is_active' => true,
                ],
            );
        }
    }
}
