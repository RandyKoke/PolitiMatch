<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ordre imposé par les dépendances de clé étrangère : les thèmes et
        // partis doivent exister avant les questions et positions.
        $this->call([
            ThemeSeeder::class,
            PartySeeder::class,
            QuestionSeeder::class,
            PartyPositionSeeder::class,
        ]);

        // Dérivé de party_positions, doit donc s'exécuter après ce seeder.
        // Pas un "vrai" seeder (aucune donnée nouvelle saisie ici, uniquement
        // un calcul sur des données déjà en base), d'où la commande Artisan
        // dédiée plutôt qu'une classe Seeder de plus.
        Artisan::call('party:calculate-axes');

        // User::factory(10)->create();

        // Idempotent comme les seeders de contenu ci-dessus : évite un
        // doublon si `db:seed` est relancé sans passer par `migrate:fresh`.
        if (! User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'username' => 'testuser',
                'email' => 'test@example.com',
            ]);
        }
    }
}
