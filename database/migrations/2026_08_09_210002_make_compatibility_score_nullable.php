<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * compatibility_score doit pouvoir être NULL : sémantique "données
     * insuffisantes" quand toutes les questions ont été passées pour un
     * parti donné (0.0 signifierait à tort un désaccord total). Cf. spec
     * technique de l'algorithme de matching §2.1.
     */
    public function up(): void
    {
        Schema::table('result_party_scores', function (Blueprint $table) {
            $table->decimal('compatibility_score', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('result_party_scores', function (Blueprint $table) {
            $table->decimal('compatibility_score', 5, 2)->nullable(false)->change();
        });
    }
};
