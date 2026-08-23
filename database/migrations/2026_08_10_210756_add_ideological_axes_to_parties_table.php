<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration additive (nullable, ne touche à aucune table/colonne
     * existante) : position idéologique d'un parti sur les deux mêmes axes
     * que l'utilisateur (économique/sociétal), dénormalisée ici plutôt que
     * recalculée à chaque requête — elle ne dépend que de party_positions
     * (donnée statique de l'expert), jamais des réponses d'un utilisateur.
     * Calculée par la commande `party:calculate-axes`
     * (App\Console\Commands\CalculatePartyIdeologicalAxes), appelée par
     * PartyPositionSeeder : le modèle de données du cahier des charges ne
     * prévoyait pas ce calcul, ajouté ici pour dériver la position
     * idéologique de chaque parti à partir de ses positions réelles plutôt
     * que de la saisir manuellement.
     */
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->decimal('ideological_x', 5, 2)->nullable()->after('is_active');
            $table->decimal('ideological_y', 5, 2)->nullable()->after('ideological_x');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn(['ideological_x', 'ideological_y']);
        });
    }
};
