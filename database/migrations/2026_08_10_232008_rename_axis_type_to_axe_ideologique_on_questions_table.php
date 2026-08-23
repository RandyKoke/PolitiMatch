<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration additive (ne touche à aucune migration existante) qui
     * remplace `axis_type` par `axe_ideologique` : même rôle (classer une
     * question pour le calcul des deux axes du graphique 2D), mais avec une
     * troisième valeur explicite 'aucun' (au lieu du NULL implicite
     * précédent) et une classification revue question par question par
     * l'expert politique, plus fine que la classification par thématique
     * entière de l'ancien `axis_type`, qui faussait le positionnement du
     * PTB/MR/Écolo sur le graphique. Ajout puis suppression de l'ancienne
     * colonne plutôt qu'un
     * `renameColumn` : sur PostgreSQL, `enum()` est implémenté par une
     * contrainte CHECK nommée d'après la colonne — recréer proprement la
     * colonne évite tout risque de contrainte orpheline après un
     * renommage.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->enum('axe_ideologique', ['economique', 'societal', 'aucun'])->nullable()->after('weight');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('axis_type');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->enum('axis_type', ['economique', 'societal'])->nullable();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('axe_ideologique');
        });
    }
};
