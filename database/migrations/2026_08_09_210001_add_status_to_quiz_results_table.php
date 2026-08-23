<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Machine à états requise par la spécification technique de l'algorithme
     * de matching (verrou optimiste, reprise sur échec) — absente du
     * diagramme de classes initial, qui ne modélisait pas encore le calcul
     * asynchrone des résultats.
     */
    public function up(): void
    {
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->enum('status', ['pending', 'computing', 'completed', 'failed'])->default('pending');
            // Nécessaire au cron de nettoyage (quiz bloqués en 'computing').
            $table->timestamp('updated_at')->nullable();
            $table->index('status');
        });

        // completed_at ne peut plus être NOT NULL : un QuizResult existe dès le
        // démarrage du quiz (status='pending', avant toute réponse), bien avant
        // d'être complété — contrairement à ce que supposait le schéma initial.
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_results', function (Blueprint $table) {
            $table->dropColumn(['status', 'updated_at']);
        });

        Schema::table('quiz_results', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable(false)->change();
        });
    }
};
