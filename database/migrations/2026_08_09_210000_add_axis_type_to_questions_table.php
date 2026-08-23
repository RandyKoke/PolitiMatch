<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajout requis par la spécification technique de l'algorithme de matching
     * (calcul des axes idéologiques) — absent du diagramme de classes initial.
     * Nullable : une question peut ne contribuer à aucun des deux axes
     * (ex. Q30 sur le vote obligatoire, plutôt "démocratie" que économique/sociétal).
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->enum('axis_type', ['economique', 'societal'])->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('axis_type');
        });
    }
};
