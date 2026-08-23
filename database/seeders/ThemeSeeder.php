<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

class ThemeSeeder extends Seeder
{
    /**
     * updateOrCreate sur le nom (déjà unique en base) : ré-exécutable sans
     * doublon.
     */
    public function run(): void
    {
        $data = require database_path('seeders/data/expert_content.php');

        foreach ($data['themes'] as $theme) {
            Theme::updateOrCreate(
                ['name' => $theme['name']],
                ['description' => $theme['description']],
            );
        }
    }
}
